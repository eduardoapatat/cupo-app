import type { CalendarDay } from '@/components/agenda/types';
import { cn } from '@/lib/utils';

/**
 * The days of the week in the day view, like the strip of «Reservar»: a
 * tap shows that day.
 */
export default function DayStrip({
    days,
    focusDate,
    onSelect,
}: {
    days: CalendarDay[];
    focusDate: string;
    onSelect: (date: string) => void;
}) {
    return (
        <nav
            aria-label="Días de la semana"
            className="-mx-4 overflow-x-auto px-4 pb-1"
        >
            <ul className="flex gap-2">
                {days.map((day) => {
                    const isSelected = day.date === focusDate;

                    return (
                        <li key={day.date} className="flex-1">
                            <button
                                type="button"
                                onClick={() => onSelect(day.date)}
                                aria-current={isSelected ? 'date' : undefined}
                                aria-label={`${day.longLabel}, ${
                                    day.sessions.length === 1
                                        ? '1 clase'
                                        : `${day.sessions.length} clases`
                                }`}
                                className={cn(
                                    'flex w-full min-w-12 flex-col items-center gap-0.5 rounded-lg border py-2 text-sm transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                    isSelected
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'hover:bg-muted',
                                    !isSelected &&
                                        day.sessions.length === 0 &&
                                        'text-muted-foreground',
                                )}
                            >
                                <span className="text-xs">
                                    {day.isToday ? 'Hoy' : day.weekday}
                                </span>
                                <span className="text-base font-semibold tabular-nums">
                                    {day.dayOfMonth}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
