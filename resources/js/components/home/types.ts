import type { Attendee } from '@/components/attendance-list';

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
