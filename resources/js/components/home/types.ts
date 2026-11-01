import type { Attendee } from '@/components/attendance-list';
import type { PaymentRow } from '@/types';

/** A class of today as the Inicio shows it. */
export type HomeSession = {
    id: number;
    startsAt: string;
    endsAt: string;
    classTypeName: string;
    instructorName: string | null;
    bookedCount: number;
    capacity: number;
    isCancelled: boolean;
    hasStarted: boolean;
};

/** The class on now, or the next one today, with who comes. */
export type Arrival = HomeSession & { isOn: boolean; attendees: Attendee[] };

/** The student's next booked class. */
export type NextBookingSummary = HomeSession & {
    date: string;
    isToday: boolean;
    freeCancellationUntil: string;
    cancelsOnTime: boolean;
};

/** Transfer receipts waiting, and the oldest three. */
export type ReceiptsToReviewSummary = {
    count: number;
    oldest: PaymentRow[];
};

/** Her cash register today, as the front desk's Inicio shows it. */
export type CashRegisterSummary =
    | { state: 'none' }
    | {
          state: 'open' | 'closed' | 'stale';
          day: string;
          openedAt: string;
          totals: { cash: number; card: number; transfer: number };
          /** Counted minus expected, once closed. */
          difference: number | null;
      };

/** A register the owner should look at. */
export type RegisterAlert = {
    id: number;
    personName: string;
    day: string;
    difference: number | null;
    note: string | null;
};

/** What the owner should look at today. */
export type OwnerAlerts = {
    receiptsToReview: number;
    openRegisters: RegisterAlert[];
    registersWithDifference: RegisterAlert[];
    voidedToday: {
        id: number;
        studentName: string;
        amount: number;
        voidedBy: string | null;
        reason: string | null;
    }[];
};

/** The month's income so far, and the same stretch of the month before. */
export type MonthIncomeSummary = {
    total: number;
    previous: number;
    byMethod: { method: string; label: string; total: number }[];
};
