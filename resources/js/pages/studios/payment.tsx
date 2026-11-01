import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import {
    index as paymentsIndex,
    show as paymentShow,
} from '@/actions/App/Http/Controllers/Studio/PaymentController';
import {
    approve as approvePayment,
    reject as rejectPayment,
} from '@/actions/App/Http/Controllers/Studio/PaymentReviewController';
import { store as voidPayment } from '@/actions/App/Http/Controllers/Studio/PaymentVoidController';
import { show as studentShow } from '@/actions/App/Http/Controllers/Studio/StudentController';
import InputError from '@/components/input-error';
import PaymentStatusBadge from '@/components/payments/payment-status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { formatClp } from '@/lib/money';
import { dashboard } from '@/routes';
import type { CurrentStudio, PaymentRow } from '@/types';

type PaymentDetail = PaymentRow & {
    takenAt: string;
    receivedBy: string | null;
    /** Her membership, to open her profile; null if she left the studio. */
    studentProfileId: number | null;
    register: { personName: string; day: string; isClosed: boolean } | null;
    /** The transfer receipt, served only to whoever may see it. */
    receipt: { url: string; isPdf: boolean } | null;
    reviewed: { by: string | null; at: string } | null;
    rejectionReason: string | null;
    /** Paid online: what Transbank answered. */
    webpay: {
        buyOrder: string;
        authorizationCode: string | null;
        cardLastDigits: string | null;
        failureReason: string | null;
    } | null;
    voided: { by: string | null; at: string; reason: string | null } | null;
    plan: {
        name: string;
        listPrice: number | null;
        priceReason: string | null;
        isUnlimited: boolean;
        credits: number | null;
        creditsLeft: number | null;
        lastDay: string;
        isCancelled: boolean;
        upcomingBookings: number;
    } | null;
};

type Props = {
    payment: PaymentDetail;
    /** A receipt from the app waiting to be approved or rejected. */
    canReview: boolean;
    canVoid: boolean;
    /** Why she cannot void this one, when she could void others. */
    voidDenied: string | null;
};

/** «Se quitan las 6 clases que le quedan y se cancelan sus 2 reservas». */
function voidEffect(payment: PaymentDetail): string {
    const plan = payment.plan;

    if (!plan) {
        return '';
    }

    const classes = plan.isUnlimited
        ? `Su plan ${plan.name} deja de servir`
        : plan.creditsLeft === 1
          ? 'Se quita la clase que le queda'
          : `Se quitan las ${plan.creditsLeft ?? 0} clases que le quedan`;
    const bookings =
        plan.upcomingBookings === 0
            ? ''
            : plan.upcomingBookings === 1
              ? ' y se cancela su reserva que viene, que libera su cupo'
              : ` y se cancelan sus ${plan.upcomingBookings} reservas que vienen, que liberan sus cupos`;

    return `${classes}${bookings}. Las clases que ya tomó quedan en su historial.`;
}

/** Approve it at once, or reject it saying why: she reads the reason. */
function ReviewActions({
    slug,
    payment,
}: {
    slug: string;
    payment: PaymentDetail;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="flex flex-wrap gap-2">
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogTrigger asChild>
                    <Button variant="outline">Rechazar</Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-md">
                    <DialogTitle>Rechazar el comprobante</DialogTitle>
                    <DialogDescription>
                        {payment.studentName} verá el motivo en «Mi plan» y
                        podrá enviar otro comprobante.
                    </DialogDescription>
                    <Form
                        {...rejectPayment.form([slug, payment.id])}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        onError={(errors) =>
                            focusFirstError(errors, {
                                reason: 'reject-reason',
                            })
                        }
                        className="grid gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="reject-reason">
                                        Por qué
                                    </Label>
                                    <Input
                                        id="reject-reason"
                                        {...fieldErrorProps(
                                            'reject-reason',
                                            errors.reason,
                                        )}
                                        name="reason"
                                        required
                                        maxLength={200}
                                        placeholder="El monto no coincide con el plan"
                                    />
                                    <InputError
                                        id="reject-reason-error"
                                        message={errors.reason}
                                    />
                                </div>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button variant="outline">
                                            Volver
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Rechazar
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
            <Form
                {...approvePayment.form([slug, payment.id])}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Aprobar
                    </Button>
                )}
            </Form>
        </div>
    );
}

function VoidDialog({
    slug,
    payment,
}: {
    slug: string;
    payment: PaymentDetail;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Anular</Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>
                    ¿Anular {formatClp(payment.amount)} de {payment.studentName}
                    ?
                </DialogTitle>
                <DialogDescription>{voidEffect(payment)}</DialogDescription>
                <Form
                    {...voidPayment.form([slug, payment.id])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) =>
                        focusFirstError(errors, { reason: 'void-reason' })
                    }
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="void-reason">Por qué</Label>
                                <Input
                                    id="void-reason"
                                    {...fieldErrorProps(
                                        'void-reason',
                                        errors.reason,
                                    )}
                                    name="reason"
                                    required
                                    maxLength={200}
                                    placeholder="Se vendió el plan equivocado"
                                />
                                <InputError
                                    id="void-reason-error"
                                    message={errors.reason}
                                />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="outline">Volver</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Anular pago
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DetailRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium break-words">{children}</dd>
        </div>
    );
}

export default function Payment({
    payment,
    canReview,
    canVoid,
    voidDenied,
}: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    const plan = payment.plan;
    const priceChanged =
        plan !== null &&
        plan.listPrice !== null &&
        plan.listPrice !== payment.amount;

    return (
        <>
            <Head title={`Pago de ${payment.studentName}`} />

            <div className="flex max-w-5xl flex-col gap-6 px-4 py-6">
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="-ml-2 w-fit"
                >
                    <Link href={paymentsIndex(studio.slug)}>
                        <ArrowLeft />
                        Pagos
                    </Link>
                </Button>

                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="grid gap-2">
                        <PaymentStatusBadge payment={payment} />
                        <h1 className="text-xl font-semibold tracking-tight">
                            Pago de {payment.studentName}
                        </h1>
                        <p className="text-sm text-muted-foreground first-letter:uppercase">
                            {payment.takenAt}
                        </p>
                    </div>
                    {canReview && (
                        <ReviewActions slug={studio.slug} payment={payment} />
                    )}
                    {canVoid && (
                        <VoidDialog slug={studio.slug} payment={payment} />
                    )}
                </header>

                {voidDenied && (
                    <p className="text-sm text-muted-foreground">
                        {voidDenied}
                    </p>
                )}

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <section
                        aria-labelledby="payment-details"
                        className="grid gap-6"
                    >
                        <h2 id="payment-details" className="sr-only">
                            Detalle
                        </h2>
                        <dl className="grid gap-5 rounded-xl border p-5 sm:grid-cols-2">
                            <DetailRow label="Alumna">
                                {payment.studentProfileId ? (
                                    <Link
                                        href={studentShow.url(
                                            [
                                                studio.slug,
                                                payment.studentProfileId,
                                            ],
                                            { query: { pestana: 'plan' } },
                                        )}
                                        className="underline underline-offset-4"
                                    >
                                        {payment.studentName}
                                    </Link>
                                ) : (
                                    payment.studentName
                                )}
                            </DetailRow>
                            <DetailRow label="Plan">
                                {payment.planName ?? 'Sin plan'}
                            </DetailRow>
                            <DetailRow label="Medio de pago">
                                {payment.method}
                            </DetailRow>
                            {payment.webpay ? (
                                <>
                                    <DetailRow label="Tarjeta">
                                        {payment.webpay.cardLastDigits
                                            ? `Terminada en ${payment.webpay.cardLastDigits}`
                                            : 'Sin datos'}
                                        {payment.webpay.authorizationCode &&
                                            ` · autorización ${payment.webpay.authorizationCode}`}
                                    </DetailRow>
                                    <DetailRow label="Orden de compra">
                                        <span className="font-mono text-sm">
                                            {payment.webpay.buyOrder}
                                        </span>
                                    </DetailRow>
                                    {payment.webpay.failureReason && (
                                        <DetailRow label="Por qué no se completó">
                                            {payment.webpay.failureReason}
                                        </DetailRow>
                                    )}
                                    <DetailRow label="Caja">
                                        Ninguna: se pagó en línea con Webpay
                                    </DetailRow>
                                </>
                            ) : (
                                <>
                                    <DetailRow label="Cobró">
                                        {payment.receivedBy ??
                                            (payment.status === 'to_review'
                                                ? 'Todavía nadie'
                                                : 'Nadie')}
                                    </DetailRow>
                                    <DetailRow label="Caja">
                                        {payment.register
                                            ? `De ${payment.register.personName}, ${payment.register.day} · ${payment.register.isClosed ? 'cerrada' : 'abierta'}`
                                            : 'Ninguna: llegó por transferencia desde la app'}
                                    </DetailRow>
                                </>
                            )}
                            {plan && (
                                <DetailRow label="Clases">
                                    {plan.isCancelled
                                        ? 'Plan anulado'
                                        : `${plan.isUnlimited ? 'Ilimitadas' : `${plan.creditsLeft ?? 0} de ${plan.credits ?? 0}`} · vale hasta el ${plan.lastDay}`}
                                </DetailRow>
                            )}
                        </dl>

                        {payment.receipt && (
                            <section
                                aria-labelledby="payment-receipt"
                                className="grid gap-3 rounded-xl border p-5"
                            >
                                <h2
                                    id="payment-receipt"
                                    className="text-sm font-medium"
                                >
                                    Comprobante
                                </h2>
                                {payment.receipt.isPdf ? (
                                    <a
                                        href={payment.receipt.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-sm underline underline-offset-4"
                                    >
                                        Abrir el PDF
                                    </a>
                                ) : (
                                    <a
                                        href={payment.receipt.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="justify-self-start"
                                    >
                                        <img
                                            src={payment.receipt.url}
                                            alt={`Comprobante de ${payment.studentName}`}
                                            className="max-h-96 rounded-lg border object-contain"
                                        />
                                    </a>
                                )}
                            </section>
                        )}

                        {payment.reviewed && (
                            <section
                                aria-labelledby="payment-review"
                                className="grid gap-2 rounded-xl border p-5"
                            >
                                <h2
                                    id="payment-review"
                                    className="text-sm font-medium"
                                >
                                    {payment.rejectionReason !== null
                                        ? 'Rechazo'
                                        : 'Validación'}
                                </h2>
                                <p className="text-sm first-letter:uppercase">
                                    {payment.reviewed.at}, por{' '}
                                    {payment.reviewed.by ??
                                        'alguien que ya no está en el equipo'}
                                    .
                                </p>
                                {payment.rejectionReason && (
                                    <p className="text-sm text-muted-foreground">
                                        «{payment.rejectionReason}»
                                    </p>
                                )}
                            </section>
                        )}

                        {payment.voided && (
                            <section
                                aria-labelledby="payment-trail"
                                className="grid gap-2 rounded-xl border p-5"
                            >
                                <h2
                                    id="payment-trail"
                                    className="text-sm font-medium"
                                >
                                    Anulación
                                </h2>
                                <p className="text-sm first-letter:uppercase">
                                    {payment.voided.at}, por{' '}
                                    {payment.voided.by ??
                                        'alguien que ya no está en el equipo'}
                                    .
                                </p>
                                {payment.voided.reason && (
                                    <p className="text-sm text-muted-foreground">
                                        «{payment.voided.reason}»
                                    </p>
                                )}
                                {payment.voidedAfterClosing && (
                                    <p className="text-sm text-muted-foreground">
                                        Su caja ya estaba cerrada, así que sigue
                                        en sus totales.
                                    </p>
                                )}
                            </section>
                        )}
                    </section>

                    <aside className="grid gap-4 rounded-xl border p-5">
                        <div className="grid gap-1">
                            <p className="text-sm text-muted-foreground">
                                Monto
                            </p>
                            <p className="text-3xl font-semibold tabular-nums">
                                {formatClp(payment.amount)}
                            </p>
                        </div>
                        {priceChanged && plan && (
                            <p className="text-sm text-muted-foreground">
                                {payment.amount === 0
                                    ? 'Regalado'
                                    : 'Precio cambiado'}{' '}
                                (lista: {formatClp(plan.listPrice ?? 0)})
                                {plan.priceReason && `: «${plan.priceReason}»`}
                            </p>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

Payment.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [
                  { title: 'Pagos', href: paymentsIndex(props.studio.slug) },
                  {
                      title: props.payment.studentName,
                      href: paymentShow([props.studio.slug, props.payment.id]),
                  },
              ]
            : []),
    ],
});
