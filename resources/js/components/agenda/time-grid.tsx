import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type {
    AgendaView,
    CalendarDay,
    CalendarSession,
} from '@/components/agenda/types';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** How tall one hour is, in rem. */
const HOUR_REM = 4.5;

/** The studios' time zone (config('studio.timezone')), for the «now» line. */
const STUDIO_TIME_ZONE = 'America/Santiago';

/** When a week has no classes, the grid still shows a usual working day. */
const EMPTY_RANGE = { start: 7 * 60, end: 21 * 60 };

/**
 * The hours the grid covers: from an hour before the week's first class to
 * an hour after its last one, on whole hours. The whole week sets it, so
 * switching days in the day view keeps the same hours.
 */
function gridRange(days: CalendarDay[]): { start: number; end: number } {
    const sessions = days.flatMap((day) => day.sessions);

    if (sessions.length === 0) {
        return EMPTY_RANGE;
    }

    const first = Math.min(...sessions.map((session) => session.startMinutes));
    const last = Math.max(
        ...sessions.map(
            (session) => session.startMinutes + session.durationMinutes,
        ),
    );

    return {
        start: Math.max(0, Math.floor(first / 60) * 60 - 60),
        end: Math.min(24 * 60, Math.ceil(last / 60) * 60 + 60),
    };
}

function toRem(minutes: number): number {
    return (minutes / 60) * HOUR_REM;
}

/** Minutes since midnight in Santiago, updated every minute; null until mounted. */
function useMinutesNow(): number | null {
    const [minutes, setMinutes] = useState<number | null>(null);

    useEffect(() => {
        const format = new Intl.DateTimeFormat('en-GB', {
            timeZone: STUDIO_TIME_ZONE,
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        });
        const update = () => {
            const [hours, mins] = format.format(new Date()).split(':');
            setMinutes(Number(hours) * 60 + Number(mins));
        };

        update();
        const timer = window.setInterval(update, 60_000);

        return () => window.clearInterval(timer);
    }, []);

    return minutes;
}

function SessionBlock({
    href,
    session,
    gridStart,
    isOpen,
    dayLabel,
}: {
    href: string;
    session: CalendarSession;
    gridStart: number;
    isOpen: boolean;
    dayLabel: string;
}) {
    const isFull = session.bookedCount >= session.capacity;
    const isShort = session.durationMinutes < 40;
    const occupancy = session.isCancelled
        ? 'Cancelada'
        : isFull
          ? 'Llena'
          : `${session.bookedCount}/${session.capacity}`;

    return (
        <Link
            href={href}
            only={['session']}
            preserveState
            preserveScroll
            data-session-id={session.id}
            aria-current={isOpen ? 'true' : undefined}
            aria-label={`${session.classTypeName} del ${dayLabel} de ${session.startsAt} a ${session.endsAt}, ${
                session.isCancelled
                    ? 'cancelada'
                    : `${session.bookedCount} de ${session.capacity} cupos`
            }`}
            style={{
                top: `${toRem(session.startMinutes - gridStart)}rem`,
                height: `${Math.max(toRem(session.durationMinutes), 1.5)}rem`,
            }}
            className={cn(
                buttonVariants({ variant: 'outline' }),
                'absolute inset-x-1 h-auto flex-col items-stretch justify-start gap-0 overflow-hidden px-2 py-1 text-left text-xs font-normal whitespace-normal',
                // Dashed until it has an instructor: it cannot be booked yet.
                session.instructorName === null && 'border-dashed',
                isOpen && 'z-10 border-foreground',
                (session.isCancelled || session.hasStarted) &&
                    'text-muted-foreground',
            )}
        >
            <span className="flex items-baseline justify-between gap-2">
                <span
                    className={cn(
                        'truncate font-medium',
                        session.isCancelled && 'line-through',
                    )}
                >
                    <span className="tabular-nums">{session.startsAt}</span>{' '}
                    {isShort && session.classTypeName}
                </span>
                <span
                    className={cn(
                        'shrink-0 tabular-nums',
                        isFull && !session.isCancelled
                            ? 'font-medium'
                            : 'text-muted-foreground',
                    )}
                >
                    {occupancy}
                </span>
            </span>
            {!isShort && (
                <>
                    <span
                        className={cn(
                            'truncate font-medium',
                            session.isCancelled && 'line-through',
                        )}
                    >
                        {session.classTypeName}
                    </span>
                    <span className="truncate text-muted-foreground">
                        {session.instructorName ?? 'Sin instructor'}
                    </span>
                </>
            )}
        </Link>
    );
}

/**
 * The week (or one day) as a calendar: hours down the side, one column per
 * day and each class as a block at its time, as tall as it lasts. Classes
 * never overlap (the studio has one room), so every block takes the whole
 * width of its day.
 */
export default function TimeGrid({
    days,
    shownDays,
    view,
    openSessionId,
    sessionHref,
}: {
    days: CalendarDay[];
    shownDays: CalendarDay[];
    view: AgendaView;
    openSessionId: number | null;
    sessionHref: (session: CalendarSession) => string;
}) {
    const range = gridRange(days);
    const height = toRem(range.end - range.start);
    const hours = Array.from(
        { length: (range.end - range.start) / 60 },
        (_, index) => range.start + index * 60,
    );
    const nowMinutes = useMinutesNow();
    const scrollRef = useRef<HTMLDivElement>(null);
    const today = shownDays.find((day) => day.isToday);
    const firstSession = shownDays
        .flatMap((day) => day.sessions)
        .map((session) => session.startMinutes)
        .sort((a, b) => a - b)[0];
    const isEmpty = shownDays.every((day) => day.sessions.length === 0);

    // Open at the current time today, or at the first class otherwise.
    const scrollTarget =
        today && nowMinutes !== null ? nowMinutes : (firstSession ?? null);

    useEffect(() => {
        const container = scrollRef.current;

        if (!container || scrollTarget === null) {
            return;
        }

        const remInPixels = parseFloat(
            getComputedStyle(document.documentElement).fontSize,
        );
        container.scrollTop = Math.max(
            0,
            (toRem(scrollTarget - range.start) - HOUR_REM) * remInPixels,
        );
        // Only when the shown days change (or the clock first arrives), not
        // every minute.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [shownDays.map((day) => day.date).join(), view, nowMinutes !== null]);

    return (
        <div
            ref={scrollRef}
            className="relative max-h-[calc(100svh-17rem)] min-h-96 overflow-auto rounded-xl border"
        >
            <div
                className={cn(
                    'grid',
                    view === 'semana'
                        ? 'min-w-[44rem] grid-cols-[3.5rem_repeat(7,minmax(0,1fr))]'
                        : 'grid-cols-[3.5rem_minmax(0,1fr)]',
                )}
            >
                {/* The day headers stay in sight while the hours scroll. */}
                <div className="sticky top-0 left-0 z-30 border-b bg-background" />
                {shownDays.map((day) => (
                    <div
                        key={day.date}
                        className="sticky top-0 z-20 flex items-center justify-center gap-1.5 border-b border-l bg-background py-2 text-sm"
                    >
                        <span
                            className={cn(
                                !day.isToday && 'text-muted-foreground',
                            )}
                        >
                            {day.weekday}
                        </span>
                        <span
                            className={cn(
                                'inline-flex size-7 items-center justify-center rounded-full font-medium tabular-nums',
                                day.isToday && 'bg-foreground text-background',
                            )}
                            aria-label={day.isToday ? 'hoy' : undefined}
                        >
                            {day.dayOfMonth}
                        </span>
                    </div>
                ))}

                <div
                    className="sticky left-0 z-10 bg-background"
                    style={{ height: `${height}rem` }}
                    aria-hidden="true"
                >
                    {hours.map((minute, index) => (
                        <span
                            key={minute}
                            className={cn(
                                'absolute right-2 text-xs text-muted-foreground tabular-nums',
                                index > 0 && '-translate-y-1/2',
                            )}
                            style={{
                                top: `${toRem(minute - range.start)}rem`,
                            }}
                        >
                            {String(minute / 60).padStart(2, '0')}:00
                        </span>
                    ))}
                </div>

                {shownDays.map((day) => (
                    <div
                        key={day.date}
                        className={cn(
                            'relative border-l',
                            day.isToday && 'bg-muted/40',
                        )}
                        style={{ height: `${height}rem` }}
                    >
                        {hours.map((minute) => (
                            <div key={minute} aria-hidden="true">
                                <div
                                    className="absolute inset-x-0 border-t"
                                    style={{
                                        top: `${toRem(minute - range.start)}rem`,
                                    }}
                                />
                                <div
                                    className="absolute inset-x-0 border-t border-dashed border-border/60"
                                    style={{
                                        top: `${toRem(minute - range.start + 30)}rem`,
                                    }}
                                />
                            </div>
                        ))}

                        {day.sessions.map((session) => (
                            <SessionBlock
                                key={session.id}
                                href={sessionHref(session)}
                                session={session}
                                gridStart={range.start}
                                isOpen={openSessionId === session.id}
                                dayLabel={day.longLabel}
                            />
                        ))}

                        {day.isToday &&
                            nowMinutes !== null &&
                            nowMinutes >= range.start &&
                            nowMinutes <= range.end && (
                                <div
                                    className="pointer-events-none absolute inset-x-0 z-20 border-t-2 border-foreground"
                                    style={{
                                        top: `${toRem(nowMinutes - range.start)}rem`,
                                    }}
                                    aria-hidden="true"
                                >
                                    <span className="absolute -top-[5px] -left-1 size-2 rounded-full bg-foreground" />
                                </div>
                            )}
                    </div>
                ))}
            </div>

            {isEmpty && (
                <p className="pointer-events-none absolute inset-x-0 top-1/2 text-center text-sm text-muted-foreground">
                    {view === 'semana'
                        ? 'No hay clases esta semana.'
                        : 'No hay clases este día.'}
                </p>
            )}
        </div>
    );
}
