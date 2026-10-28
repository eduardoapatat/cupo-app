/** The Agenda's two views, as they go in the URL (?vista=). */
export type AgendaView = 'dia' | 'semana';

export type AgendaWeek = {
    start: string;
    label: string;
    monthLabel: string;
    previous: string;
    next: string;
    sessionCount: number;
};

export type AgendaToday = {
    date: string;
    weekday: string;
    dayOfMonth: number;
};

export type AgendaFocus = { date: string; label: string };

/** A class as the calendar draws it. Minutes count from local midnight. */
export type CalendarSession = {
    id: number;
    startsAt: string;
    endsAt: string;
    startMinutes: number;
    durationMinutes: number;
    classTypeName: string;
    instructorName: string | null;
    bookedCount: number;
    capacity: number;
    isCancelled: boolean;
    hasStarted: boolean;
};

export type CalendarDay = {
    date: string;
    weekday: string;
    dayOfMonth: number;
    longLabel: string;
    isToday: boolean;
    sessions: CalendarSession[];
};
