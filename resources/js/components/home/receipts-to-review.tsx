import { Link } from '@inertiajs/react';
import {
    index as paymentsIndex,
    show as paymentShow,
} from '@/actions/App/Http/Controllers/Studio/PaymentController';
import type { ReceiptsToReviewSummary } from '@/components/home/types';
import { formatClp } from '@/lib/money';

/** The transfers waiting, oldest first, each one a step from approving it. */
export default function ReceiptsToReview({
    slug,
    receipts,
}: {
    slug: string;
    receipts: ReceiptsToReviewSummary;
}) {
    if (receipts.count === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No hay comprobantes por validar.
            </p>
        );
    }

    return (
        <div className="grid gap-3">
            <ul className="divide-y rounded-lg border">
                {receipts.oldest.map((payment) => (
                    <li key={payment.id}>
                        <Link
                            href={paymentShow([slug, payment.id])}
                            className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm transition-colors hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <span className="grid min-w-0">
                                <span className="truncate font-medium">
                                    {payment.studentName}
                                </span>
                                <span className="truncate text-muted-foreground">
                                    {payment.planName} · {payment.date}
                                </span>
                            </span>
                            <span className="font-medium tabular-nums">
                                {formatClp(payment.amount)}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
            <Link
                href={paymentsIndex(slug)}
                className="text-sm underline underline-offset-4"
            >
                {receipts.count === 1
                    ? 'Ver el comprobante en Pagos'
                    : `Ver los ${receipts.count} en Pagos`}
            </Link>
        </div>
    );
}
