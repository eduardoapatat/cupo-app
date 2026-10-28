import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { es } from 'react-day-picker/locale';
import type {
    AgendaFocus,
    AgendaMonth,
    AgendaToday,
    AgendaView,
    AgendaWeek,
} from '@/components/agenda/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

/** A date of the URL (AAAA-MM-DD) as a local date for the calendar, and back. */
function parseDate(date: string): Date {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function formatDate(date: Date): string {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

function addDays(date: string, days: number): string {
    const next = parseDate(date);
    next.setDate(next.getDate() + days);

    return formatDate(next);
}

function classCount(count: number): string {
    return count === 1 ? '1 clase' : `${count} clases`;
}

/**
 * Where the Agenda stands: today's date as a fixed reference, the month and
 * the range with arrows, a calendar to jump to any date, the view and «Hoy».
 */
export default function AgendaHeader({
    today,
    week,
    month,
    focus,
    view,
    dayCount,
    goTo,
}: {
    today: AgendaToday;
    week: AgendaWeek;
    /** Only in the month view. */
    month: AgendaMonth | null;
    focus: AgendaFocus;
    view: AgendaView;
    /** Classes of the focused day, for the day view's badge. */
    dayCount: number;
    goTo: (date: string, view: AgendaView) => void;
}) {
    const [pickerOpen, setPickerOpen] = useState(false);
    const isDay = view === 'dia';
    const shownMonth = view === 'mes' ? month : null;
    const showsToday = shownMonth
        ? today.date.slice(0, 7) === shownMonth.start.slice(0, 7)
        : isDay
          ? focus.date === today.date
          : today.date >= week.start && today.date <= addDays(week.start, 6);
    const previous = shownMonth
        ? shownMonth.previous
        : isDay
          ? addDays(focus.date, -1)
          : week.previous;
    const next = shownMonth
        ? shownMonth.next
        : isDay
          ? addDays(focus.date, 1)
          : week.next;
    const unit = shownMonth ? 'Mes' : isDay ? 'Día' : 'Semana';
    const rangeLabel = shownMonth
        ? shownMonth.rangeLabel
        : isDay
          ? focus.label
          : week.label;
    const count = shownMonth
        ? shownMonth.sessionCount
        : isDay
          ? dayCount
          : week.sessionCount;

    return (
        <header className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-4">
                <div
                    className="flex w-14 shrink-0 flex-col items-center overflow-hidden rounded-lg border text-center"
                    aria-label={`Hoy es ${today.weekday.toLowerCase()} ${today.dayOfMonth}`}
                >
                    <span className="w-full bg-foreground py-0.5 text-[0.625rem] font-semibold tracking-wide text-background">
                        {today.weekday}
                    </span>
                    <span className="py-1 text-lg font-semibold tabular-nums">
                        {today.dayOfMonth}
                    </span>
                </div>

                <div className="grid gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {shownMonth ? shownMonth.label : week.monthLabel}
                        </h1>
                        <Badge variant="secondary" className="tabular-nums">
                            {classCount(count)}
                        </Badge>
                    </div>
                    <nav
                        aria-label={
                            shownMonth ? 'Meses' : isDay ? 'Días' : 'Semanas'
                        }
                        className="flex items-center gap-1"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            aria-label={`${unit} anterior`}
                            onClick={() => goTo(previous, view)}
                        >
                            <ChevronLeft />
                        </Button>
                        <span
                            className="min-w-32 text-center text-sm text-muted-foreground first-letter:uppercase"
                            aria-live="polite"
                        >
                            {rangeLabel}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            aria-label={`${unit} siguiente`}
                            onClick={() => goTo(next, view)}
                        >
                            <ChevronRight />
                        </Button>
                        <Popover open={pickerOpen} onOpenChange={setPickerOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7"
                                    aria-label="Ir a una fecha"
                                >
                                    <CalendarDays />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                className="w-auto p-0"
                                align="start"
                            >
                                <Calendar
                                    mode="single"
                                    locale={es}
                                    weekStartsOn={1}
                                    selected={parseDate(focus.date)}
                                    defaultMonth={parseDate(focus.date)}
                                    onSelect={(date) => {
                                        if (date) {
                                            setPickerOpen(false);
                                            goTo(formatDate(date), view);
                                        }
                                    }}
                                />
                            </PopoverContent>
                        </Popover>
                    </nav>
                </div>
            </div>

            <div className="flex items-center gap-2">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={view}
                    onValueChange={(next) => {
                        if (
                            next === 'dia' ||
                            next === 'semana' ||
                            next === 'mes'
                        ) {
                            goTo(focus.date, next);
                        }
                    }}
                    aria-label="Vista"
                >
                    <ToggleGroupItem value="dia" className="px-3">
                        Día
                    </ToggleGroupItem>
                    <ToggleGroupItem value="semana" className="px-3">
                        Semana
                    </ToggleGroupItem>
                    <ToggleGroupItem value="mes" className="px-3">
                        Mes
                    </ToggleGroupItem>
                </ToggleGroup>
                <Button
                    variant="outline"
                    disabled={showsToday}
                    onClick={() => goTo(today.date, view)}
                >
                    Hoy
                </Button>
            </div>
        </header>
    );
}
