import { Link } from '@inertiajs/react';
import { index as cashRegisterIndex } from '@/actions/App/Http/Controllers/Studio/CashRegisterController';
import {
    index as paymentsIndex,
    show as paymentShow,
} from '@/actions/App/Http/Controllers/Studio/PaymentController';
import type { OwnerAlerts as Alerts } from '@/components/home/types';
import { formatClp } from '@/lib/money';

function differenceLabel(difference: number): string {
    return difference < 0
        ? `faltaron ${formatClp(-difference)}`
        : `sobraron ${formatClp(difference)}`;
}

/** Receipts waiting, registers left open or short, and today's voids. */
export default function OwnerAlerts({
    slug,
    alerts,
}: {
    slug: string;
    alerts: Alerts;
}) {
    const nothing =
        alerts.receiptsToReview === 0 &&
        alerts.openRegisters.length === 0 &&
        alerts.registersWithDifference.length === 0 &&
        alerts.voidedToday.length === 0;

    if (nothing) {
        return (
            <p className="text-sm text-muted-foreground">
                Todo en orden: no hay comprobantes esperando, cajas sin cerrar
                ni anulaciones hoy.
            </p>
        );
    }

    return (
        <ul className="grid gap-3 text-sm">
            {alerts.receiptsToReview > 0 && (
                <li>
                    <Link
                        href={paymentsIndex(slug)}
                        className="font-medium underline underline-offset-4"
                    >
                        {alerts.receiptsToReview === 1
                            ? '1 comprobante por validar'
                            : `${alerts.receiptsToReview} comprobantes por validar`}
                    </Link>
                </li>
            )}
            {alerts.openRegisters.map((register) => (
                <li key={`open-${register.id}`}>
                    <Link
                        href={cashRegisterIndex(slug)}
                        className="font-medium underline underline-offset-4"
                    >
                        Caja de {register.personName} sin cerrar
                    </Link>
                    <span className="block text-muted-foreground first-letter:uppercase">
                        {register.day}
                    </span>
                </li>
            ))}
            {alerts.registersWithDifference.map((register) => (
                <li key={`difference-${register.id}`}>
                    <Link
                        href={cashRegisterIndex(slug)}
                        className="font-medium underline underline-offset-4"
                    >
                        Caja de {register.personName}:{' '}
                        {differenceLabel(register.difference ?? 0)}
                    </Link>
                    <span className="block text-muted-foreground first-letter:uppercase">
                        {register.day}
                        {register.note && ` · «${register.note}»`}
                    </span>
                </li>
            ))}
            {alerts.voidedToday.map((payment) => (
                <li key={`voided-${payment.id}`}>
                    <Link
                        href={paymentShow([slug, payment.id])}
                        className="font-medium underline underline-offset-4"
                    >
                        Anulado hoy: {formatClp(payment.amount)} de{' '}
                        {payment.studentName}
                    </Link>
                    <span className="block text-muted-foreground">
                        {payment.voidedBy && `Por ${payment.voidedBy}`}
                        {payment.reason && ` · «${payment.reason}»`}
                    </span>
                </li>
            ))}
        </ul>
    );
}
