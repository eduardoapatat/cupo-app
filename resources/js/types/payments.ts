/**
 * A payment as the lists of «Pagos», «Caja» and the student's profile show
 * it (see PresentsPayments).
 */
export type PaymentRow = {
    id: number;
    date: string;
    time: string;
    studentName: string;
    /** The plan bought, or the one she chose while it waits. */
    planName: string | null;
    method: string;
    amount: number;
    status:
        | 'to_review'
        | 'approved'
        | 'rejected'
        | 'voided'
        | 'pending'
        | 'failed';
    statusLabel: string;
    /** Voided once its register was closed: it stays in its frozen totals. */
    voidedAfterClosing: boolean;
};
