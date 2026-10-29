import { Head } from '@inertiajs/react';
import { Ticket } from 'lucide-react';
import { index as myPlanIndex } from '@/actions/App/Http/Controllers/Studio/MyPlanController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
import type { CurrentStudio, PlanSummary } from '@/types';

type Purchase = {
    id: number;
    name: string;
    credits: number | null;
    creditsLeft: number | null;
    dailyLimit: number | null;
    boughtOn: string;
    lastDay: string;
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
    valid: Purchase[];
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
                                className="h-full rounded-full bg-foreground"
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

export default function MyPlan({ summary, valid, past, movements }: Props) {
    return (
        <>
            <Head title="Mi plan" />

            <div className="flex max-w-3xl flex-col gap-6 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Mi plan"
                    description="Cuántas clases te quedan, hasta cuándo valen y en qué las usaste."
                />

                {summary === null ? (
                    <div className="grid justify-items-center gap-2 rounded-xl border border-dashed px-6 py-10 text-center">
                        <Ticket
                            className="size-6 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="font-medium">No tienes un plan vigente</p>
                        <p className="text-sm text-muted-foreground">
                            Cómpralo en el mesón del estudio para reservar tus
                            clases.
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
                                        Venció el {purchase.lastDay}
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
