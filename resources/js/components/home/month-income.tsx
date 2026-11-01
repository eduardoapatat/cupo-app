import { Link } from '@inertiajs/react';
import ReportController from '@/actions/App/Http/Controllers/Studio/ReportController';
import type { MonthIncomeSummary } from '@/components/home/types';
import { formatClp } from '@/lib/money';

/**
 * «$40.000 a esta altura del mes pasado (+12 %)». The percentage goes only
 * when it says something: against a tiny month it would read +1600 %.
 */
export function comparisonLabel(total: number, previous: number): string {
    if (previous === 0) {
        return total === 0
            ? 'Sin ingresos todavía.'
            : 'El mes pasado, a esta altura, no había entrado nada.';
    }

    const change = Math.round(((total - previous) / previous) * 100);
    const shown =
        Math.abs(change) <= 200
            ? ` (${change > 0 ? '+' : change < 0 ? '−' : ''}${Math.abs(change)} %)`
            : '';

    return `${formatClp(previous)} a esta altura del mes pasado${shown}.`;
}

/** What came in this month, how it compares and by which method. */
export default function MonthIncome({
    slug,
    income,
}: {
    slug: string;
    income: MonthIncomeSummary;
}) {
    const methods = income.byMethod.filter((method) => method.total > 0);

    return (
        <div className="grid gap-3">
            <div className="grid gap-1">
                <p className="text-4xl font-semibold tabular-nums">
                    {formatClp(income.total)}
                </p>
                <p className="text-sm text-muted-foreground">
                    {comparisonLabel(income.total, income.previous)}
                </p>
            </div>
            {methods.length > 0 && (
                <dl className="grid gap-1 text-sm">
                    {methods.map((method) => (
                        <div
                            key={method.method}
                            className="flex justify-between gap-4"
                        >
                            <dt className="text-muted-foreground">
                                {method.label}
                            </dt>
                            <dd className="tabular-nums">
                                {formatClp(method.total)}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
            <Link
                href={ReportController(slug)}
                className="text-sm underline underline-offset-4"
            >
                Ver reportes
            </Link>
        </div>
    );
}
