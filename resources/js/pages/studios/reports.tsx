import { Head, Link, router, usePage } from '@inertiajs/react';
import { Phone } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import ReportController from '@/actions/App/Http/Controllers/Studio/ReportController';
import { show as studentShow } from '@/actions/App/Http/Controllers/Studio/StudentController';
import Heading from '@/components/heading';
import { comparisonLabel } from '@/components/home/month-income';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatClp } from '@/lib/money';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CurrentStudio } from '@/types';

type Amount = { count: number; total: number };

type Props = {
    month: { value: string; label: string; isCurrent: boolean };
    months: { value: string; label: string }[];
    income: {
        total: number;
        previous: number;
        byMethod: { method: string; label: string; total: number }[];
        byDay: { date: string; label: string; total: number }[];
        byPlan: { name: string; sold: number; total: number }[];
        discounts: Amount;
        voids: Amount;
    };
    classes: {
        count: number;
        /** Booked spots over capacity, in %; null without classes. */
        occupancy: number | null;
        bySlot: { label: string; sessions: number; occupancy: number }[];
        attendance: {
            attended: number;
            noShow: number;
            cancelledLate: number;
            toMark: number;
        };
    };
    students: { withPlan: number; firstPlan: number; expiredClasses: number };
    expiringSoon: {
        id: number;
        studentName: string;
        phone: string | null;
        membershipId: number | null;
        planName: string;
        lastDay: string;
        creditsLeft: number | null;
    }[];
};

/* One series, in the ink of the achromatic system (DESIGN.md). */
const incomeConfig = {
    total: { label: 'Ingresos', color: 'var(--primary)' },
} satisfies ChartConfig;

/** A strip of figures; the 1px gaps draw the lines between them. */
function Figures({
    tiles,
}: {
    tiles: { label: string; value: string; hint?: string }[];
}) {
    return (
        <dl
            className={cn(
                'grid gap-px overflow-hidden rounded-xl border bg-border',
                tiles.length === 4
                    ? 'grid-cols-2 lg:grid-cols-4'
                    : 'sm:grid-cols-3',
            )}
        >
            {tiles.map((tile) => (
                <div
                    key={tile.label}
                    className="grid content-start gap-1 bg-background p-4"
                >
                    <dt className="text-sm text-muted-foreground">
                        {tile.label}
                    </dt>
                    <dd className="text-2xl font-semibold tabular-nums">
                        {tile.value}
                    </dd>
                    {tile.hint && (
                        <dd className="text-sm text-muted-foreground">
                            {tile.hint}
                        </dd>
                    )}
                </div>
            ))}
        </dl>
    );
}

function plural(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}

/** The money of each day, as bars; a table says the same to a reader. */
function IncomeByDay({ days }: { days: Props['income']['byDay'] }) {
    return (
        <>
            <ChartContainer
                config={incomeConfig}
                className="aspect-auto h-56 w-full"
                aria-hidden="true"
            >
                <BarChart data={days} margin={{ left: 0, right: 0, top: 8 }}>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="label"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        minTickGap={16}
                    />
                    <YAxis
                        width={72}
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={(value: number) => formatClp(value)}
                    />
                    <ChartTooltip
                        cursor={false}
                        content={
                            <ChartTooltipContent
                                hideIndicator
                                formatter={(value) => (
                                    <span className="font-medium tabular-nums">
                                        {formatClp(Number(value))}
                                    </span>
                                )}
                            />
                        }
                    />
                    <Bar
                        dataKey="total"
                        fill="var(--color-total)"
                        radius={[4, 4, 0, 0]}
                    />
                </BarChart>
            </ChartContainer>
            <table className="sr-only">
                <caption>Ingresos de cada día</caption>
                <tbody>
                    {days
                        .filter((day) => day.total > 0)
                        .map((day) => (
                            <tr key={day.date}>
                                <th scope="row">{day.label}</th>
                                <td>{formatClp(day.total)}</td>
                            </tr>
                        ))}
                </tbody>
            </table>
        </>
    );
}

/**
 * How full each time slot was, the fullest first: a label over its bar,
 * which reads at any width.
 */
function OccupancyBySlot({ slots }: { slots: Props['classes']['bySlot'] }) {
    return (
        <ul className="grid gap-3">
            {slots.map((slot) => (
                <li key={slot.label} className="grid gap-1.5">
                    <div className="flex justify-between gap-4 text-sm">
                        <span>{slot.label}</span>
                        <span className="shrink-0 tabular-nums">
                            {slot.occupancy}%{' '}
                            <span className="text-muted-foreground">
                                · {plural(slot.sessions, 'clase', 'clases')}
                            </span>
                        </span>
                    </div>
                    <div
                        className="h-2 overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <div
                            className="h-full rounded-full bg-primary"
                            style={{ width: `${slot.occupancy}%` }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

export default function Reports({
    month,
    months,
    income,
    classes,
    students,
    expiringSoon,
}: Props) {
    const { studio } = usePage().props;
    const slug = studio?.slug ?? '';
    const methods = income.byMethod.filter((method) => method.total > 0);
    const monthName = month.label.split(' ')[0];

    return (
        <>
            <Head title="Reportes" />

            <div className="flex max-w-6xl flex-col gap-8 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        className="mb-0"
                        title="Reportes"
                        description="Cómo va el estudio: la plata que entró, qué tan llenas van las clases y quién renueva."
                    />
                    <Select
                        value={month.value}
                        onValueChange={(value) =>
                            router.get(
                                ReportController.url(slug, {
                                    query: { mes: value },
                                }),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-48 first-letter:uppercase"
                            aria-label="Mes"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {months.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                    className="first-letter:uppercase"
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <section aria-labelledby="income" className="grid gap-4">
                    <h2 id="income" className="text-lg font-semibold">
                        Ingresos
                    </h2>
                    <Figures
                        tiles={[
                            {
                                label: month.isCurrent
                                    ? 'Entró este mes'
                                    : `Entró en ${monthName}`,
                                value: formatClp(income.total),
                                hint: month.isCurrent
                                    ? comparisonLabel(
                                          income.total,
                                          income.previous,
                                      )
                                    : `El mes anterior: ${formatClp(income.previous)}.`,
                            },
                            {
                                label: 'Descuentos dados',
                                value: formatClp(income.discounts.total),
                                hint: plural(
                                    income.discounts.count,
                                    'plan con descuento',
                                    'planes con descuento',
                                ),
                            },
                            {
                                label: 'Anulado',
                                value: formatClp(income.voids.total),
                                hint: plural(
                                    income.voids.count,
                                    'pago anulado',
                                    'pagos anulados',
                                ),
                            },
                        ]}
                    />
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <h3>Ingresos por día</h3>
                            </CardTitle>
                            <CardDescription>
                                Cada pago cuenta el día en que entró la plata.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {income.total === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No entró plata en este mes.
                                </p>
                            ) : (
                                <IncomeByDay days={income.byDay} />
                            )}
                        </CardContent>
                    </Card>
                    <div className="grid items-start gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    <h3>Por medio de pago</h3>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {methods.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Sin pagos en este mes.
                                    </p>
                                ) : (
                                    <dl className="grid gap-3">
                                        {methods.map((method) => {
                                            const share = Math.round(
                                                (method.total / income.total) *
                                                    100,
                                            );

                                            return (
                                                <div
                                                    key={method.method}
                                                    className="grid gap-1.5"
                                                >
                                                    <div className="flex justify-between gap-4 text-sm">
                                                        <dt>{method.label}</dt>
                                                        <dd className="tabular-nums">
                                                            {formatClp(
                                                                method.total,
                                                            )}{' '}
                                                            <span className="text-muted-foreground">
                                                                · {share}%
                                                            </span>
                                                        </dd>
                                                    </div>
                                                    <div
                                                        className="h-2 overflow-hidden rounded-full bg-muted"
                                                        aria-hidden="true"
                                                    >
                                                        <div
                                                            className="h-full rounded-full bg-primary"
                                                            style={{
                                                                width: `${share}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </dl>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    <h3>Por plan</h3>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {income.byPlan.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No se vendieron planes en este mes.
                                    </p>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Plan</TableHead>
                                                <TableHead className="text-right">
                                                    Vendidos
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Monto
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {income.byPlan.map((plan) => (
                                                <TableRow key={plan.name}>
                                                    <TableCell className="whitespace-normal">
                                                        {plan.name}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {plan.sold}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatClp(plan.total)}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section aria-labelledby="classes" className="grid gap-4">
                    <h2 id="classes" className="text-lg font-semibold">
                        Clases
                    </h2>
                    <Figures
                        tiles={[
                            {
                                label: 'Clases dadas',
                                value: String(classes.count),
                                hint:
                                    classes.occupancy === null
                                        ? undefined
                                        : `Llenas en un ${classes.occupancy}% en promedio.`,
                            },
                            {
                                label: 'Vinieron',
                                value: String(classes.attendance.attended),
                                hint:
                                    classes.attendance.toMark > 0
                                        ? `${plural(classes.attendance.toMark, 'asistencia', 'asistencias')} por marcar.`
                                        : undefined,
                            },
                            {
                                label: 'Faltaron',
                                value: String(classes.attendance.noShow),
                            },
                            {
                                label: 'Cancelaron tarde',
                                value: String(classes.attendance.cancelledLate),
                            },
                        ]}
                    />
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <h3>Ocupación por horario</h3>
                            </CardTitle>
                            <CardDescription>
                                Los cupos tomados sobre el total, de las clases
                                ya dadas. Las canceladas no cuentan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {classes.bySlot.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No hubo clases en este mes.
                                </p>
                            ) : (
                                <OccupancyBySlot slots={classes.bySlot} />
                            )}
                        </CardContent>
                    </Card>
                </section>

                <section aria-labelledby="students" className="grid gap-4">
                    <h2 id="students" className="text-lg font-semibold">
                        Alumnas
                    </h2>
                    <Figures
                        tiles={[
                            {
                                label: 'Con plan vigente hoy',
                                value: String(students.withPlan),
                            },
                            {
                                label: 'Compraron su primer plan',
                                value: String(students.firstPlan),
                                hint: `En ${monthName}.`,
                            },
                            {
                                label: 'Clases vencidas sin usar',
                                value: String(students.expiredClasses),
                                hint: `En ${monthName}.`,
                            },
                        ]}
                    />
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <h3>Planes que vencen esta semana</h3>
                            </CardTitle>
                            <CardDescription>
                                Quienes todavía no compran el siguiente:
                                llámalas para renovar.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {expiringSoon.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Ningún plan vence en los próximos 7 días.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Alumna</TableHead>
                                            <TableHead>Vence</TableHead>
                                            <TableHead className="text-right">
                                                Le quedan
                                            </TableHead>
                                            <TableHead>Teléfono</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {expiringSoon.map((purchase) => (
                                            <TableRow key={purchase.id}>
                                                <TableCell className="whitespace-normal">
                                                    {purchase.membershipId ===
                                                    null ? (
                                                        <span className="font-medium">
                                                            {
                                                                purchase.studentName
                                                            }
                                                        </span>
                                                    ) : (
                                                        <Link
                                                            href={studentShow(
                                                                {
                                                                    studio: slug,
                                                                    membership:
                                                                        purchase.membershipId,
                                                                },
                                                                {
                                                                    query: {
                                                                        pestana:
                                                                            'plan',
                                                                    },
                                                                },
                                                            )}
                                                            className="font-medium underline-offset-4 hover:underline"
                                                        >
                                                            {
                                                                purchase.studentName
                                                            }
                                                        </Link>
                                                    )}
                                                    <span className="block text-muted-foreground">
                                                        {purchase.planName}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="whitespace-normal first-letter:uppercase">
                                                    {purchase.lastDay}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {purchase.creditsLeft ===
                                                    null
                                                        ? 'Ilimitado'
                                                        : plural(
                                                              purchase.creditsLeft,
                                                              'clase',
                                                              'clases',
                                                          )}
                                                </TableCell>
                                                <TableCell>
                                                    {purchase.phone ? (
                                                        <a
                                                            href={`tel:${purchase.phone}`}
                                                            className="inline-flex items-center gap-1.5 tabular-nums underline-offset-4 hover:underline"
                                                        >
                                                            <Phone
                                                                className="size-3.5"
                                                                aria-hidden="true"
                                                            />
                                                            {purchase.phone}
                                                        </a>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            Sin teléfono
                                                        </span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}

Reports.layout = (props: { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Reportes', href: ReportController(props.studio.slug) }]
            : []),
    ],
});
