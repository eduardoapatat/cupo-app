import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { useState } from 'react';
import {
    close as closeRegister,
    index as cashRegisterIndex,
    store as openRegister,
} from '@/actions/App/Http/Controllers/Studio/CashRegisterController';
import { show as paymentShow } from '@/actions/App/Http/Controllers/Studio/PaymentController';
import InputError from '@/components/input-error';
import PaymentStatusBadge from '@/components/payments/payment-status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { formatClp } from '@/lib/money';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CurrentStudio, PaymentRow } from '@/types';

type Totals = { cash: number; card: number; transfer: number };

type Register = {
    id: number;
    personName: string;
    day: string;
    isToday: boolean;
    openedAt: string;
    closedAt: string | null;
    isClosed: boolean;
    openingCash: number;
    totals: Totals;
    expectedCash: number;
    countedCash: number | null;
    /** Counted minus expected: negative when cash is missing. Null while open. */
    difference: number | null;
    closingNote: string | null;
};

type Props = {
    register: (Register & { payments: PaymentRow[] }) | null;
    history: Register[];
    /** Every register of the team, for the owner only. */
    team: Register[] | null;
};

/** «Cuadra», «Falta $5.000» or «Sobra $2.000». */
function differenceLabel(difference: number): string {
    if (difference === 0) {
        return 'Cuadra';
    }

    return difference < 0
        ? `Falta ${formatClp(-difference)}`
        : `Sobra ${formatClp(difference)}`;
}

function OpenRegisterCard({ slug }: { slug: string }) {
    return (
        <section className="grid max-w-md gap-4 rounded-xl border p-6">
            <div className="grid gap-1">
                <h2 className="font-semibold">Abre tu caja</h2>
                <p className="text-sm text-muted-foreground">
                    Cuenta el efectivo con que partes. Lo que vendas hoy entra
                    en tu caja.
                </p>
            </div>
            <Form
                {...openRegister.form(slug)}
                onError={(errors) =>
                    focusFirstError(errors, { opening_cash: 'opening-cash' })
                }
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="opening-cash">
                                Efectivo inicial (pesos)
                            </Label>
                            <Input
                                id="opening-cash"
                                {...fieldErrorProps(
                                    'opening-cash',
                                    errors.opening_cash,
                                )}
                                name="opening_cash"
                                type="number"
                                inputMode="numeric"
                                min={0}
                                max={10000000}
                                step={1}
                                required
                                placeholder="20000"
                            />
                            <InputError
                                id="opening-cash-error"
                                message={errors.opening_cash}
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="justify-self-start"
                        >
                            {processing && <Spinner />}
                            Abrir caja
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}

function CloseRegisterDialog({
    slug,
    register,
}: {
    slug: string;
    register: Register;
}) {
    const [open, setOpen] = useState(false);
    const [counted, setCounted] = useState('');
    const difference =
        counted === '' ? null : Number(counted) - register.expectedCash;

    function onOpenChange(next: boolean) {
        if (next) {
            setCounted('');
        }

        setOpen(next);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>
                <Button>Cerrar caja</Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>
                    {register.isToday
                        ? 'Cerrar tu caja'
                        : `Cerrar la caja del ${register.day}`}
                </DialogTitle>
                <DialogDescription>
                    Cuenta el efectivo que hay en la caja. Después de cerrarla,
                    sus totales no cambian.
                </DialogDescription>
                <Form
                    {...closeRegister.form([slug, register.id])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) =>
                        focusFirstError(errors, {
                            counted_cash: 'counted-cash',
                            closing_note: 'closing-note',
                        })
                    }
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <dl className="grid gap-2 rounded-lg bg-muted/60 p-4 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Efectivo inicial
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatClp(register.openingCash)}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Cobros en efectivo
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatClp(register.totals.cash)}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4 border-t pt-2 font-medium">
                                    <dt>Debería haber</dt>
                                    <dd className="tabular-nums">
                                        {formatClp(register.expectedCash)}
                                    </dd>
                                </div>
                            </dl>

                            <div className="grid gap-2">
                                <Label htmlFor="counted-cash">
                                    Efectivo contado (pesos)
                                </Label>
                                <Input
                                    id="counted-cash"
                                    {...fieldErrorProps(
                                        'counted-cash',
                                        errors.counted_cash,
                                        'counted-cash-difference',
                                    )}
                                    name="counted_cash"
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    max={10000000}
                                    step={1}
                                    required
                                    value={counted}
                                    onChange={(event) =>
                                        setCounted(event.target.value)
                                    }
                                />
                                <p
                                    id="counted-cash-difference"
                                    aria-live="polite"
                                    className={cn(
                                        'text-sm font-medium tabular-nums',
                                        difference === null &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {difference === null
                                        ? 'Escribe lo que contaste.'
                                        : differenceLabel(difference)}
                                </p>
                                <InputError
                                    id="counted-cash-error"
                                    message={errors.counted_cash}
                                />
                            </div>

                            {difference !== null && difference !== 0 && (
                                <div className="grid gap-2">
                                    <Label htmlFor="closing-note">
                                        Por qué no cuadra
                                    </Label>
                                    <Input
                                        id="closing-note"
                                        {...fieldErrorProps(
                                            'closing-note',
                                            errors.closing_note,
                                        )}
                                        name="closing_note"
                                        required
                                        maxLength={300}
                                        placeholder="Di vuelto de más a una alumna"
                                    />
                                    <InputError
                                        id="closing-note-error"
                                        message={errors.closing_note}
                                    />
                                </div>
                            )}

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Cerrar caja
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** The strip of totals, as in a payments dashboard. */
function TotalsStrip({ register }: { register: Register }) {
    const tiles = [
        { label: 'Efectivo inicial', value: register.openingCash },
        { label: 'Efectivo', value: register.totals.cash },
        { label: 'Tarjeta', value: register.totals.card },
        { label: 'Transferencia', value: register.totals.transfer },
    ];

    return (
        <dl className="grid grid-cols-2 overflow-hidden rounded-xl border sm:grid-cols-4">
            {tiles.map((tile, index) => (
                <div
                    key={tile.label}
                    className={cn(
                        'grid gap-1 p-4',
                        index % 2 === 1 && 'border-l',
                        index >= 2 && 'border-t sm:border-t-0',
                        index === 2 && 'sm:border-l',
                    )}
                >
                    <dt className="text-sm text-muted-foreground">
                        {tile.label}
                    </dt>
                    <dd className="text-2xl font-semibold tabular-nums">
                        {formatClp(tile.value)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/** What was counted when it was closed. */
function ClosingSummary({ register }: { register: Register }) {
    if (!register.isClosed || register.difference === null) {
        return null;
    }

    return (
        <dl className="grid gap-2 rounded-xl border p-4 text-sm sm:max-w-md">
            <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Debería haber</dt>
                <dd className="tabular-nums">
                    {formatClp(register.expectedCash)}
                </dd>
            </div>
            <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Contado</dt>
                <dd className="tabular-nums">
                    {formatClp(register.countedCash ?? 0)}
                </dd>
            </div>
            <div className="flex justify-between gap-4 border-t pt-2 font-medium">
                <dt>Diferencia</dt>
                <dd className="tabular-nums">
                    {differenceLabel(register.difference)}
                </dd>
            </div>
            {register.closingNote && (
                <p className="text-muted-foreground">
                    «{register.closingNote}»
                </p>
            )}
        </dl>
    );
}

function PaymentsTable({
    slug,
    payments,
}: {
    slug: string;
    payments: PaymentRow[];
}) {
    if (payments.length === 0) {
        return (
            <p className="rounded-xl border border-dashed px-6 py-8 text-center text-sm text-muted-foreground">
                Todavía no hay cobros. Lo que vendas en «Vender plan» aparece
                aquí.
            </p>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Hora</TableHead>
                        <TableHead>Alumna</TableHead>
                        <TableHead>Medio</TableHead>
                        <TableHead className="text-right">Monto</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {payments.map((payment) => (
                        <TableRow key={payment.id} className="relative">
                            <TableCell className="tabular-nums">
                                {payment.time}
                            </TableCell>
                            <TableCell className="whitespace-normal">
                                {/* The whole row opens the payment. */}
                                <Link
                                    href={paymentShow([slug, payment.id])}
                                    className="block font-medium after:absolute after:inset-0 focus-visible:outline-none after:focus-visible:ring-[3px] after:focus-visible:ring-ring/50 after:focus-visible:ring-inset"
                                >
                                    {payment.studentName}
                                </Link>
                                <span className="block text-muted-foreground">
                                    {payment.planName}
                                </span>
                                {payment.status !== 'approved' && (
                                    <span className="mt-1 block">
                                        <PaymentStatusBadge payment={payment} />
                                    </span>
                                )}
                            </TableCell>
                            <TableCell>{payment.method}</TableCell>
                            <TableCell
                                className={cn(
                                    'text-right font-medium tabular-nums',
                                    payment.status !== 'approved' &&
                                        'text-muted-foreground line-through',
                                )}
                            >
                                {formatClp(payment.amount)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

/** Closed registers, one per row, with what they took in and the difference. */
function RegistersTable({
    registers,
    showPerson,
}: {
    registers: Register[];
    showPerson: boolean;
}) {
    return (
        <div className="overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Día</TableHead>
                        {showPerson && <TableHead>Quién</TableHead>}
                        <TableHead className="text-right">Cobrado</TableHead>
                        <TableHead className="text-right">Al cerrar</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {registers.map((register) => (
                        <TableRow key={register.id}>
                            <TableCell className="whitespace-normal first-letter:uppercase">
                                {register.day}
                            </TableCell>
                            {showPerson && (
                                <TableCell>{register.personName}</TableCell>
                            )}
                            <TableCell className="text-right tabular-nums">
                                {formatClp(
                                    register.totals.cash +
                                        register.totals.card +
                                        register.totals.transfer,
                                )}
                            </TableCell>
                            <TableCell className="text-right whitespace-normal">
                                {register.difference === null ? (
                                    <Badge variant="outline">Abierta</Badge>
                                ) : (
                                    <>
                                        <span
                                            className={cn(
                                                'block tabular-nums',
                                                register.difference !== 0 &&
                                                    'font-medium',
                                            )}
                                        >
                                            {differenceLabel(
                                                register.difference,
                                            )}
                                        </span>
                                        {register.closingNote && (
                                            <span className="block text-muted-foreground">
                                                «{register.closingNote}»
                                            </span>
                                        )}
                                    </>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

function MyRegister({
    slug,
    register,
    history,
}: {
    slug: string;
    register: Props['register'];
    history: Register[];
}) {
    return (
        <div className="grid gap-6">
            {register === null ? (
                <OpenRegisterCard slug={slug} />
            ) : (
                <>
                    {!register.isClosed && !register.isToday && (
                        <Alert>
                            <Wallet />
                            <AlertTitle>
                                Tu caja del {register.day} sigue abierta
                            </AlertTitle>
                            <AlertDescription>
                                Ciérrala contando el efectivo para abrir la de
                                hoy y volver a vender.
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Badge
                                variant={
                                    register.isClosed ? 'outline' : 'secondary'
                                }
                            >
                                {register.isClosed ? 'Cerrada' : 'Abierta'}
                            </Badge>
                            <span className="first-letter:uppercase">
                                {register.isClosed
                                    ? `${register.day}, de ${register.openedAt} a ${register.closedAt}`
                                    : `${register.isToday ? 'Desde' : `${register.day}, desde`} las ${register.openedAt}`}
                            </span>
                        </div>
                        {!register.isClosed && (
                            <CloseRegisterDialog
                                slug={slug}
                                register={register}
                            />
                        )}
                    </div>

                    <TotalsStrip register={register} />
                    <ClosingSummary register={register} />

                    <section
                        aria-labelledby="register-payments"
                        className="grid gap-3"
                    >
                        <h2
                            id="register-payments"
                            className="text-sm font-medium"
                        >
                            Cobros
                        </h2>
                        <PaymentsTable
                            slug={slug}
                            payments={register.payments}
                        />
                    </section>
                </>
            )}

            {history.length > 0 && (
                <section
                    aria-labelledby="register-history"
                    className="grid gap-3"
                >
                    <h2 id="register-history" className="text-sm font-medium">
                        Mis cajas anteriores
                    </h2>
                    <RegistersTable registers={history} showPerson={false} />
                </section>
            )}
        </div>
    );
}

export default function CashRegister({ register, history, team }: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    const mine = (
        <MyRegister slug={studio.slug} register={register} history={history} />
    );

    return (
        <>
            <Head title="Caja" />

            <div className="flex max-w-5xl flex-col gap-6 px-4 py-6">
                <header className="grid gap-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Caja
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Lo que cobras en el mesón, por medio de pago. Al cerrar,
                        cuentas el efectivo y ves si cuadra.
                    </p>
                </header>

                {team === null ? (
                    mine
                ) : (
                    <Tabs defaultValue="mine" className="gap-6">
                        <TabsList variant="line">
                            <TabsTrigger value="mine">Mi caja</TabsTrigger>
                            <TabsTrigger value="team">Equipo</TabsTrigger>
                        </TabsList>
                        <TabsContent value="mine">{mine}</TabsContent>
                        <TabsContent value="team" className="grid gap-3">
                            <p className="text-sm text-muted-foreground">
                                Las cajas de los últimos 14 días y las que
                                siguen abiertas.
                            </p>
                            {team.length === 0 ? (
                                <p className="rounded-xl border border-dashed px-6 py-8 text-center text-sm text-muted-foreground">
                                    Nadie ha abierto una caja estos días.
                                </p>
                            ) : (
                                <RegistersTable registers={team} showPerson />
                            )}
                        </TabsContent>
                    </Tabs>
                )}
            </div>
        </>
    );
}

CashRegister.layout = (props: { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Caja', href: cashRegisterIndex(props.studio.slug) }]
            : []),
    ],
});
