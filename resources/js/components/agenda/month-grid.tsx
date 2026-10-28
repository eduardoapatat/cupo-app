import { Link } from '@inertiajs/react';
import type {
    AgendaMonth,
    CalendarSession,
    MonthDay,
} from '@/components/agenda/types';
import { cn } from '@/lib/utils';

/** Classes listed in a day before «+N más». */
const VISIBLE_SESSIONS = 3;

const WEEKDAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

function SessionRow({
    href,
    session,
    isOpen,
}: {
    href: string;
    session: CalendarSession;
    isOpen: boolean;
}) {
    const isFull = session.bookedCount >= session.capacity;

    return (
        <Link
            href={href}
            only={['session']}
            preserveState
            preserveScroll
            data-session-id={session.id}
            aria-current={isOpen ? 'true' : undefined}
            aria-label={`${session.classTypeName} a las ${session.startsAt}, ${
                session.isCancelled
                    ? 'cancelada'
                    : `${session.bookedCount} de ${session.capacity} cupos`
            }`}
            className={cn(
                'flex items-baseline gap-1.5 rounded-md border px-1.5 py-0.5 text-xs transition-colors hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                // Dashed until it has an instructor: it cannot be booked yet.
                session.instructorName === null && 'border-dashed',
                isOpen && 'border-foreground',
                (session.isCancelled || session.hasStarted) &&
                    'text-muted-foreground',
            )}
        >
            <span
                className={cn(
                    'min-w-0 flex-1 truncate',
                    session.isCancelled && 'line-through',
                )}
            >
                <span className="font-medium tabular-nums">
                    {session.startsAt}
                </span>{' '}
                {session.classTypeName}
            </span>
            {!session.isCancelled && (
                <span
                    className={cn(
                        'shrink-0 tabular-nums',
                        isFull ? 'font-medium' : 'text-muted-foreground',
                    )}
                >
                    {isFull
                        ? 'Llena'
                        : `${session.bookedCount}/${session.capacity}`}
                </span>
            )}
        </Link>
    );
}

function sessionsLabel(count: number): string {
    return count === 1 ? '1 clase' : `${count} clases`;
}

function DayCell({
    day,
    openSessionId,
    sessionHref,
    onOpenDay,
}: {
    day: MonthDay;
    openSessionId: number | null;
    sessionHref: (session: CalendarSession) => string;
    onOpenDay: (date: string) => void;
}) {
    const hidden = day.sessions.length - VISIBLE_SESSIONS;
    const visible =
        hidden > 0 ? day.sessions.slice(0, VISIBLE_SESSIONS - 1) : day.sessions;
    const more = day.sessions.length - visible.length;
    const scheduled = day.sessions.filter(
        (session) => !session.isCancelled,
    ).length;

    return (
        <div
            className={cn(
                'flex min-h-20 min-w-0 flex-col gap-1 border-t border-l p-1 md:min-h-28',
                !day.inMonth && 'bg-muted/40',
                day.isToday && 'bg-muted/60',
            )}
        >
            <button
                type="button"
                onClick={() => onOpenDay(day.date)}
                aria-label={`Ver el día ${day.dayOfMonth}, ${sessionsLabel(scheduled)}`}
                className={cn(
                    'inline-flex size-7 items-center justify-center self-end rounded-full text-sm tabular-nums transition-colors hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none md:self-start',
                    !day.inMonth && 'text-muted-foreground',
                    day.isToday &&
                        'bg-foreground font-medium text-background hover:bg-foreground/90',
                )}
            >
                {day.dayOfMonth}
            </button>

            {/* Narrow screens show how many classes there are; a tap opens the day. */}
            {scheduled > 0 && (
                <button
                    type="button"
                    onClick={() => onOpenDay(day.date)}
                    className="flex items-center justify-center gap-1 text-xs text-muted-foreground tabular-nums md:hidden"
                    aria-hidden="true"
                    tabIndex={-1}
                >
                    <span className="size-1.5 rounded-full bg-foreground" />
                    {scheduled}
                </button>
            )}

            <div className="hidden min-w-0 flex-col gap-1 md:flex">
                {visible.map((session) => (
                    <SessionRow
                        key={session.id}
                        href={sessionHref(session)}
                        session={session}
                        isOpen={openSessionId === session.id}
                    />
                ))}
                {more > 0 && (
                    <button
                        type="button"
                        onClick={() => onOpenDay(day.date)}
                        className="rounded-md px-1.5 py-0.5 text-left text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        +{more} más
                    </button>
                )}
            </div>
        </div>
    );
}

/**
 * The month as whole weeks from Monday to Sunday. Each day lists its
 * classes (up to three, then «+N más»); a day number opens the day view.
 */
export default function MonthGrid({
    month,
    openSessionId,
    sessionHref,
    onOpenDay,
}: {
    month: AgendaMonth;
    openSessionId: number | null;
    sessionHref: (session: CalendarSession) => string;
    onOpenDay: (date: string) => void;
}) {
    return (
        <div className="overflow-hidden rounded-xl border">
            <div className="grid grid-cols-7 bg-background" aria-hidden="true">
                {WEEKDAYS.map((weekday, index) => (
                    <div
                        key={weekday}
                        className={cn(
                            'py-2 text-center text-xs font-medium text-muted-foreground',
                            index > 0 && 'border-l',
                        )}
                    >
                        {weekday}
                    </div>
                ))}
            </div>
            {/* The first column has no left border, so the cells meet the frame. */}
            <div className="-ml-px grid grid-cols-7">
                {month.weeks.flat().map((day) => (
                    <DayCell
                        key={day.date}
                        day={day}
                        openSessionId={openSessionId}
                        sessionHref={sessionHref}
                        onOpenDay={onOpenDay}
                    />
                ))}
            </div>
        </div>
    );
}
