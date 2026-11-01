import { Head, Link, usePage } from '@inertiajs/react';
import { Clock, Ticket, TriangleAlert } from 'lucide-react';
import { create as buyPlan } from '@/actions/App/Http/Controllers/Studio/BuyPlanController';
import { index as myPlanIndex } from '@/actions/App/Http/Controllers/Studio/MyPlanController';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { formatClp } from '@/lib/money';
import type { CurrentStudio, PlanSummary } from '@/types';

type Purchase = {
    id: number;
    name: string;
    credits: number | null;
    creditsLeft: number | null;
    dailyLimit: number | null;
    boughtOn: string;
    /** The day it starts, in words: for a plan waiting its turn. */
    startsOn: string;
    lastDay: string;
    /** Its payment was voided, which voids the plan. */
    isCancelled: boolean;
    /** The classes it had left when it expired. */
    expiredCredits: number;
};

type Movement = {
    id: number;
    date: string;
    reason: string;
    delta: number;
    planName: string;
    className: string | null;
    classDate: string | null;
};

type Props = {
    summary: PlanSummary | null;
    /** The plan she paid by transfer, waiting for the studio. */
    pendingPayment: {
        planName: string | null;
        amount: number;
        sentOn: string;
        receiptUrl: string;
    } | null;
    /** Her last receipt, if the studio rejected it. */
    rejectedPayment: { planName: string | null; reason: string | null } | null;
    /** A Webpay payment she has not finished, or Transbank has not settled. */
    webpayPending: { planName: string | null; amount: number } | null;
    /** Her last Webpay payment, which did not go through. */
    failedPayment: { planName: string | null; reason: string | null } | null;
    /** The studio takes transfers and she has none waiting. */
    canBuy: boolean;
    valid: Purchase[];
    /** Bought ahead: each starts when the one before ends. */
    queued: Purchase[];
    past: Purchase[];
    movements: Movement[];
};

function classesLeftLabel(count: number): string {
    return count === 1 ? 'Te queda 1 clase' : `Te quedan ${count} clases`;
}

function ValidPlan({ purchase }: { purchase: Purchase }) {
    const used =
        purchase.credits === null
            ? 0
            : purchase.credits - (purchase.creditsLeft ?? 0);

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <CardTitle>
                        <h2 className="break-words">{purchase.name}</h2>
                    </CardTitle>
                    <Badge variant="secondary">Vigente</Badge>
                </div>
                <CardDescription className="first-letter:uppercase">
                    Vale hasta el {purchase.lastDay}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {purchase.credits === null ? (
                    <p className="text-sm">
                        Clases ilimitadas
                        {purchase.dailyLimit !== null &&
                            `, hasta ${purchase.dailyLimit} al día`}
                        .
                    </p>
                ) : (
                    <>
                        <p className="flex items-baseline justify-between gap-4 text-sm">
                            <span>
                                {classesLeftLabel(purchase.creditsLeft ?? 0)}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {used} de {purchase.credits} usadas
                            </span>
                        </p>
                        <div
                            className="h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-label={`${used} de ${purchase.credits} clases usadas`}
                            aria-valuemin={0}
                            aria-valuemax={purchase.credits}
                            aria-valuenow={used}
                        >
                            <div
                                className="h-full rounded-full bg-primary"
                                style={{
                                    width: `${(used / purchase.credits) * 100}%`,
                                }}
                            />
                        </div>
                        {purchase.dailyLimit !== null && (
                            <p className="text-sm text-muted-foreground">
                                Hasta {purchase.dailyLimit} al día.
                            </p>
                        )}
                    </>
                )}
                <p className="text-sm text-muted-foreground">
                    Comprado el {purchase.boughtOn}.
                </p>
            </CardContent>
        </Card>
    );
}

export default function MyPlan({
    summary,
    pendingPayment,
    rejectedPayment,
    webpayPending,
    failedPayment,
    canBuy,
    valid,
    queued,
    past,
    movements,
}: Props) {
    const { studio } = usePage().props;
    const slug = studio?.slug ?? '';

    return (
        <>
            <Head title="Mi plan" />

            <div className="flex max-w-3xl flex-col gap-6 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        className="mb-0"
                        title="Mi plan"
                        description="Cuántas clases te quedan, hasta cuándo valen y en qué las usaste."
                    />
                    {canBuy &&
                        rejectedPayment === null &&
                        failedPayment === null && (
                            <Button asChild variant="outline">
                                <Link href={buyPlan(slug)}>Comprar plan</Link>
                            </Button>
                        )}
                </div>

                {summary?.expiresSoon && (
                    <Alert>
                        <Clock />
                        <AlertTitle className="line-clamp-none">
                            Tu plan vence el {summary.lastDay}
                        </AlertTitle>
                        <AlertDescription className="grid gap-3">
                            <p>
                                {summary.isUnlimited
                                    ? 'Renuévalo para seguir reservando.'
                                    : `Te ${summary.classesLeft === 1 ? 'queda 1 clase' : `quedan ${summary.classesLeft} clases`}: las que no uses hasta ese día vencen. Renuévalo para seguir reservando.`}
                            </p>
                            {canBuy && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="w-fit"
                                >
                                    <Link href={buyPlan(slug)}>Renovar</Link>
                                </Button>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {pendingPayment && (
                    <Alert>
                        <Clock />
                        <AlertTitle className="line-clamp-none">
                            Tu pago de {pendingPayment.planName} está por
                            validar
                        </AlertTitle>
                        <AlertDescription>
                            <p>
                                Enviaste el comprobante de{' '}
                                {formatClp(pendingPayment.amount)} el{' '}
                                {pendingPayment.sentOn}. Tu plan parte cuando el
                                estudio lo valide.
                            </p>
                            <a
                                href={pendingPayment.receiptUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="underline underline-offset-4"
                            >
                                Ver comprobante
                            </a>
                        </AlertDescription>
                    </Alert>
                )}

                {webpayPending && (
                    <Alert>
                        <Clock />
                        <AlertTitle className="line-clamp-none">
                            Tu pago de {webpayPending.planName} con Webpay no ha
                            terminado
                        </AlertTitle>
                        <AlertDescription>
                            Si pagaste {formatClp(webpayPending.amount)}, lo
                            confirmamos con Webpay en unos minutos y tu plan
                            aparece aquí. Si no, no se cobra nada.
                        </AlertDescription>
                    </Alert>
                )}

                {failedPayment && (
                    <Alert>
                        <TriangleAlert />
                        <AlertTitle className="line-clamp-none">
                            Tu pago de {failedPayment.planName} con Webpay no se
                            completó
                        </AlertTitle>
                        <AlertDescription className="grid gap-3">
                            <p>
                                {failedPayment.reason
                                    ? `${failedPayment.reason} No se cobró nada.`
                                    : 'No se cobró nada.'}
                            </p>
                            {canBuy && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="w-fit"
                                >
                                    <Link href={buyPlan(slug)}>
                                        Intentar de nuevo
                                    </Link>
                                </Button>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {rejectedPayment && (
                    <Alert>
                        <TriangleAlert />
                        <AlertTitle className="line-clamp-none">
                            El estudio rechazó tu comprobante de{' '}
                            {rejectedPayment.planName}
                        </AlertTitle>
                        <AlertDescription className="grid gap-3">
                            {rejectedPayment.reason && (
                                <p>«{rejectedPayment.reason}»</p>
                            )}
                            {canBuy && (
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="w-fit"
                                >
                                    <Link href={buyPlan(slug)}>
                                        Subir otro comprobante
                                    </Link>
                                </Button>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {summary === null ? (
                    <div className="grid justify-items-center gap-2 rounded-xl border border-dashed px-6 py-10 text-center">
                        <Ticket
                            className="size-6 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="font-medium">No tienes un plan vigente</p>
                        <p className="text-sm text-muted-foreground">
                            {canBuy
                                ? 'Cómpralo aquí con Webpay, o en el mesón del estudio, para reservar tus clases.'
                                : 'Cómpralo en el mesón del estudio para reservar tus clases.'}
                        </p>
                    </div>
                ) : (
                    <dl className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1 rounded-xl border p-4">
                            <dt className="text-sm text-muted-foreground">
                                Clases que te quedan
                            </dt>
                            <dd className="text-3xl font-semibold tabular-nums">
                                {summary.isUnlimited
                                    ? 'Ilimitadas'
                                    : summary.classesLeft}
                            </dd>
                        </div>
                        <div className="grid gap-1 rounded-xl border p-4">
                            <dt className="text-sm text-muted-foreground">
                                Vale hasta
                            </dt>
                            <dd className="grid gap-0.5">
                                <span className="font-semibold first-letter:uppercase">
                                    {summary.lastDay}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {summary.daysLeft === 1
                                        ? 'Último día'
                                        : `Quedan ${summary.daysLeft} días`}
                                </span>
                                {summary.next && (
                                    <span className="text-sm">
                                        Después sigue {summary.next.name}, desde
                                        el {summary.next.startsOn}.
                                    </span>
                                )}
                            </dd>
                        </div>
                    </dl>
                )}

                {valid.length > 0 && (
                    <section
                        aria-label="Planes vigentes"
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        {valid.map((purchase) => (
                            <ValidPlan key={purchase.id} purchase={purchase} />
                        ))}
                    </section>
                )}

                <section aria-labelledby="movements" className="grid gap-3">
                    <h2 id="movements" className="text-sm font-medium">
                        Movimientos
                    </h2>
                    {movements.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Aquí verás cada clase que reserves y las que te
                            devuelvan.
                        </p>
                    ) : (
                        <div className="overflow-hidden rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Clase</TableHead>
                                        <TableHead>Motivo</TableHead>
                                        <TableHead className="text-right">
                                            Clases
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {movements.map((movement) => (
                                        <TableRow key={movement.id}>
                                            <TableCell className="whitespace-normal">
                                                <span className="block font-medium">
                                                    {movement.className ??
                                                        movement.planName}
                                                </span>
                                                <span className="block text-muted-foreground first-letter:uppercase">
                                                    {movement.classDate ??
                                                        movement.date}
                                                </span>
                                            </TableCell>
                                            <TableCell className="whitespace-normal">
                                                {movement.reason}
                                            </TableCell>
                                            <TableCell
                                                className={cn(
                                                    'text-right font-medium tabular-nums',
                                                    movement.delta < 0 &&
                                                        'text-muted-foreground',
                                                )}
                                            >
                                                {movement.delta > 0
                                                    ? `+${movement.delta}`
                                                    : `−${Math.abs(movement.delta)}`}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </section>

                {queued.length > 0 && (
                    <section aria-labelledby="queued" className="grid gap-3">
                        <h2 id="queued" className="text-sm font-medium">
                            En espera
                        </h2>
                        <ul className="grid gap-3 sm:grid-cols-2">
                            {queued.map((purchase) => (
                                <li
                                    key={purchase.id}
                                    className="grid gap-1 rounded-xl border border-dashed p-4 text-sm"
                                >
                                    <span className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {purchase.name}
                                        </span>
                                        <Badge variant="outline">
                                            En espera
                                        </Badge>
                                    </span>
                                    <span className="first-letter:uppercase">
                                        Parte el {purchase.startsOn}, cuando
                                        termine tu plan actual.
                                    </span>
                                    <span className="text-muted-foreground">
                                        {purchase.credits === null
                                            ? 'Clases ilimitadas'
                                            : `${purchase.credits} clases`}{' '}
                                        · vale hasta el {purchase.lastDay}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {past.length > 0 && (
                    <section aria-labelledby="past" className="grid gap-3">
                        <h2 id="past" className="text-sm font-medium">
                            Planes anteriores
                        </h2>
                        <ul className="divide-y rounded-xl border">
                            {past.map((purchase) => (
                                <li
                                    key={purchase.id}
                                    className="flex flex-wrap items-center justify-between gap-2 p-4 text-sm"
                                >
                                    <span className="font-medium">
                                        {purchase.name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {purchase.isCancelled
                                            ? 'Anulado'
                                            : `Venció el ${purchase.lastDay}`}
                                        {purchase.expiredCredits > 0 &&
                                            ` · ${purchase.expiredCredits === 1 ? '1 clase vencida' : `${purchase.expiredCredits} clases vencidas`} sin usar`}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

MyPlan.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Mi plan', href: myPlanIndex(props.studio.slug) }]
            : []),
    ],
});
