import { Badge } from '@/components/ui/badge';
import type { PaymentRow } from '@/types';

/**
 * The status of a payment. Achromatic: one to review is filled, to stand
 * out; a rejected or voided one is outlined, never red, because it is a
 * correction, not an error.
 */
export default function PaymentStatusBadge({
    payment,
}: {
    payment: Pick<PaymentRow, 'status' | 'statusLabel' | 'voidedAfterClosing'>;
}) {
    return (
        <Badge
            variant={
                payment.status === 'to_review'
                    ? 'default'
                    : payment.status === 'approved'
                      ? 'secondary'
                      : 'outline'
            }
        >
            {payment.voidedAfterClosing
                ? 'Anulado después del cierre'
                : payment.statusLabel}
        </Badge>
    );
}
