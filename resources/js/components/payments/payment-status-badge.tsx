import { Badge } from '@/components/ui/badge';
import type { PaymentRow } from '@/types';

/**
 * The status of a payment. Achromatic: a voided one is outlined, never red,
 * because voiding is a correction, not an error.
 */
export default function PaymentStatusBadge({
    payment,
}: {
    payment: Pick<PaymentRow, 'status' | 'statusLabel' | 'voidedAfterClosing'>;
}) {
    return (
        <Badge
            variant={payment.status === 'approved' ? 'secondary' : 'outline'}
        >
            {payment.voidedAfterClosing
                ? 'Anulado después del cierre'
                : payment.statusLabel}
        </Badge>
    );
}
