/**
 * A payment as the lists of «Pagos», «Caja» and the student's profile show
 * it (see PresentsPayments).
 */
export type PaymentRow = {
    id: number;
    date: string;
    time: string;
    studentName: string;
    planName: string | null;
    method: string;
    amount: number;
    status: 'approved' | 'voided';
    statusLabel: string;
    /** Voided once its register was closed: it stays in its frozen totals. */
    voidedAfterClosing: boolean;
};
