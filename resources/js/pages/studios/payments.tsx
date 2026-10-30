import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    index as paymentsIndex,
    show as paymentShow,
} from '@/actions/App/Http/Controllers/Studio/PaymentController';
import PaymentStatusBadge from '@/components/payments/payment-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import type { CurrentStudio, PaymentRow } from '@/types';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type StatusFilter = 'todos' | 'aprobados' | 'anulados';

type Props = {
    payments: Paginated<PaymentRow>;
    filters: { status: StatusFilter; search: string };
    stats: { today: number; month: number; voidedThisMonth: number };
};

const TABS: { value: StatusFilter; label: string }[] = [
    { value: 'todos', label: 'Todos' },
    { value: 'aprobados', label: 'Aprobados' },
    { value: 'anulados', label: 'Anulados' },
];

function paymentsUrl(slug: string, status: StatusFilter, search: string) {
    return paymentsIndex.url(slug, {
        query: {
            estado: status === 'todos' ? undefined : status,
            buscar: search === '' ? undefined : search,
        },
    });
}

/** The strip of totals, as in a payments dashboard. */
function StatsStrip({ stats }: { stats: Props['stats'] }) {
    const tiles = [
        { label: 'Cobrado hoy', value: formatClp(stats.today) },
        { label: 'Cobrado este mes', value: formatClp(stats.month) },
        {
            label: 'Anulados este mes',
            value: String(stats.voidedThisMonth),
        },
    ];

    return (
        <dl className="grid grid-cols-3 overflow-hidden rounded-xl border">
            {tiles.map((tile, index) => (
                <div
                    key={tile.label}
                    className={cn(
                        'grid content-start gap-1 p-3 sm:p-4',
                        index > 0 && 'border-l',
                    )}
                >
                    <dt className="text-xs text-muted-foreground sm:text-sm">
                        {tile.label}
                    </dt>
                    <dd className="text-lg font-semibold tabular-nums sm:text-2xl">
                        {tile.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export default function Payments({ payments, filters, stats }: Props) {
    const { studio } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const firstRender = useRef(true);
    const slug = studio?.slug ?? '';

    // Searches as she types, a moment after she stops.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                paymentsUrl(slug, filters.status, search.trim()),
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, slug, filters.status]);

    if (!studio) {
        return null;
    }

    return (
        <>
            <Head title="Pagos" />

            <div className="flex max-w-5xl flex-col gap-6 px-4 py-6">
                <header className="grid gap-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Pagos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Cada pago del estudio, con quién lo cobró y en qué caja
                        entró.
                    </p>
                </header>

                <StatsStrip stats={stats} />

                <div className="flex flex-wrap items-end justify-between gap-4 border-b">
                    <nav aria-label="Estado" className="-mb-px flex gap-1">
                        {TABS.map((tab) => (
                            <Link
                                key={tab.value}
                                href={paymentsUrl(
                                    slug,
                                    tab.value,
                                    filters.search,
                                )}
                                preserveScroll
                                aria-current={
                                    filters.status === tab.value
                                        ? 'page'
                                        : undefined
                                }
                                className={cn(
                                    'border-b-2 border-transparent px-2 pb-2 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                    filters.status === tab.value &&
                                        'border-foreground font-medium text-foreground',
                                )}
                            >
                                {tab.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="relative mb-2 w-full sm:w-64">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            type="search"
                            aria-label="Buscar por alumna"
                            placeholder="Buscar por alumna"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="pl-9"
                        />
                    </div>
                </div>

                {payments.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                        {filters.search !== ''
                            ? `No hay pagos de «${filters.search}».`
                            : 'Todavía no hay pagos aquí.'}
                    </p>
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Alumna</TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Medio
                                    </TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Estado
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Monto
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payments.data.map((payment) => (
                                    <TableRow
                                        key={payment.id}
                                        className="relative"
                                    >
                                        <TableCell className="tabular-nums">
                                            <span className="block">
                                                {payment.date}
                                            </span>
                                            <span className="block text-muted-foreground">
                                                {payment.time}
                                            </span>
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            {/* The whole row opens the payment. */}
                                            <Link
                                                href={paymentShow([
                                                    slug,
                                                    payment.id,
                                                ])}
                                                className="block font-medium after:absolute after:inset-0 focus-visible:outline-none after:focus-visible:ring-[3px] after:focus-visible:ring-ring/50 after:focus-visible:ring-inset"
                                            >
                                                {payment.studentName}
                                            </Link>
                                            <span className="block text-muted-foreground">
                                                {payment.planName}
                                            </span>
                                            <span className="mt-1 block sm:hidden">
                                                <PaymentStatusBadge
                                                    payment={payment}
                                                />
                                            </span>
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            {payment.method}
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            <PaymentStatusBadge
                                                payment={payment}
                                            />
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-right font-medium tabular-nums',
                                                payment.status === 'voided' &&
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
                )}

                {payments.last_page > 1 && (
                    <nav
                        aria-label="Páginas de pagos"
                        className="flex items-center justify-between gap-4 text-sm"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!payments.prev_page_url}
                            asChild={!!payments.prev_page_url}
                        >
                            {payments.prev_page_url ? (
                                <Link
                                    href={payments.prev_page_url}
                                    preserveScroll
                                >
                                    Más recientes
                                </Link>
                            ) : (
                                'Más recientes'
                            )}
                        </Button>
                        <span className="text-muted-foreground tabular-nums">
                            Página {payments.current_page} de{' '}
                            {payments.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!payments.next_page_url}
                            asChild={!!payments.next_page_url}
                        >
                            {payments.next_page_url ? (
                                <Link
                                    href={payments.next_page_url}
                                    preserveScroll
                                >
                                    Más antiguos
                                </Link>
                            ) : (
                                'Más antiguos'
                            )}
                        </Button>
                    </nav>
                )}
            </div>
        </>
    );
}

Payments.layout = (props: { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Pagos', href: paymentsIndex(props.studio.slug) }]
            : []),
    ],
});
