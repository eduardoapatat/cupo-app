import { Link } from '@inertiajs/react';
import { index as cashRegisterIndex } from '@/actions/App/Http/Controllers/Studio/CashRegisterController';
import type { CashRegisterSummary as Summary } from '@/components/home/types';
import { Button } from '@/components/ui/button';
import { formatClp } from '@/lib/money';

/** «Cuadró», «Faltaron $5.000» or «Sobraron $2.000». */
function differenceLabel(difference: number): string {
    if (difference === 0) {
        return 'Cuadró';
    }

    return difference < 0
        ? `Faltaron ${formatClp(-difference)}`
        : `Sobraron ${formatClp(difference)}`;
}

/** Whether her register is open, and what it took in, per method. */
export default function CashRegisterSummary({
    slug,
    register,
}: {
    slug: string;
    register: Summary;
}) {
    if (register.state === 'none') {
        return (
            <div className="grid gap-3">
                <p className="text-sm text-muted-foreground">
                    Todavía no abres tu caja. Ábrela para vender.
                </p>
                <Button className="justify-self-start" asChild>
                    <Link href={cashRegisterIndex(slug)}>Abrir caja</Link>
                </Button>
            </div>
        );
    }

    if (register.state === 'stale') {
        return (
            <div className="grid gap-3">
                <p className="text-sm">
                    Tu caja del {register.day} sigue abierta. Ciérrala para
                    abrir la de hoy.
                </p>
                <Button className="justify-self-start" asChild>
                    <Link href={cashRegisterIndex(slug)}>Ir a Caja</Link>
                </Button>
            </div>
        );
    }

    const total =
        register.totals.cash + register.totals.card + register.totals.transfer;

    return (
        <div className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                {register.state === 'open'
                    ? `Abierta desde las ${register.openedAt}.`
                    : `Cerrada. ${differenceLabel(register.difference ?? 0)}.`}
            </p>
            <p className="text-4xl font-semibold tabular-nums">
                {formatClp(total)}
            </p>
            <dl className="grid grid-cols-3 gap-2 text-sm">
                {[
                    ['Efectivo', register.totals.cash],
                    ['Tarjeta', register.totals.card],
                    ['Transferencia', register.totals.transfer],
                ].map(([label, value]) => (
                    <div key={label} className="grid gap-0.5">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="font-medium tabular-nums">
                            {formatClp(Number(value))}
                        </dd>
                    </div>
                ))}
            </dl>
            <Link
                href={cashRegisterIndex(slug)}
                className="text-sm underline underline-offset-4"
            >
                Ver mi caja
            </Link>
        </div>
    );
}
