import { Head, usePage } from '@inertiajs/react';
import { CalendarX2, ChevronDown } from 'lucide-react';
import { index as mySessionsIndex } from '@/actions/App/Http/Controllers/Studio/MySessionsController';
import AttendanceList from '@/components/attendance-list';
import type { Attendee } from '@/components/attendance-list';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CurrentStudio } from '@/types';

type TeachingSession = {
    id: number;
    startsAt: string;
    endsAt: string;
    classTypeName: string;
    bookedCount: number;
    capacity: number;
    isCancelled: boolean;
    hasStarted: boolean;
    attendanceIsOpen: boolean;
    attendanceClosesLabel: string;
    attendees: Attendee[];
};

type Day = {
    date: string;
    label: string;
    isToday: boolean;
    isYesterday: boolean;
    sessions: TeachingSession[];
};

type Props = { days: Day[] };

function SessionCard({
    slug,
    session,
}: {
    slug: string;
    session: TeachingSession;
}) {
    // A class that started opens with its list, to mark who missed it.
    const marksAttendance = session.hasStarted && !session.isCancelled;

    return (
        <li className="rounded-xl border p-4">
            <Collapsible className="grid gap-3" defaultOpen={marksAttendance}>
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <div
                        className={cn(
                            'grid gap-0.5',
                            session.isCancelled && 'text-muted-foreground',
                        )}
                    >
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {session.startsAt}–{session.endsAt}
                        </p>
                        <h3
                            className={cn(
                                'font-semibold',
                                session.isCancelled && 'line-through',
                            )}
                        >
                            {session.classTypeName}
                        </h3>
                    </div>
                    {session.isCancelled ? (
                        <Badge variant="outline">Cancelada</Badge>
                    ) : (
                        <span className="text-sm tabular-nums">
                            {session.bookedCount} de {session.capacity}
                        </span>
                    )}
                </div>

                {!session.isCancelled && session.attendees.length > 0 && (
                    <>
                        <CollapsibleTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="group justify-start px-2"
                            >
                                <ChevronDown
                                    className="transition-transform group-data-[state=open]:rotate-180"
                                    aria-hidden="true"
                                />
                                {marksAttendance
                                    ? `Asistencia (${session.attendees.length})`
                                    : `Quiénes vienen (${session.attendees.length})`}
                            </Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="grid gap-1 px-2">
                            {marksAttendance && (
                                <p className="text-sm text-muted-foreground">
                                    {session.attendanceIsOpen
                                        ? `Marca a quien faltó, hasta ${session.attendanceClosesLabel}. El resto queda como presente.`
                                        : 'La asistencia ya cerró.'}
                                </p>
                            )}
                            <AttendanceList
                                slug={slug}
                                sessionId={session.id}
                                attendees={session.attendees}
                                canMark={session.attendanceIsOpen}
                            />
                        </CollapsibleContent>
                    </>
                )}
                {!session.isCancelled && session.attendees.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Nadie la ha reservado todavía.
                    </p>
                )}
            </Collapsible>
        </li>
    );
}

function dayTitle(day: Day): string {
    if (day.isToday) {
        return `Hoy, ${day.label}`;
    }

    return day.isYesterday ? `Ayer, ${day.label}` : day.label;
}

export default function MySessions({ days }: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    return (
        <>
            <Head title="Mis clases" />

            <div className="flex max-w-2xl flex-col gap-6 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Mis clases"
                    description="Las clases que das y quiénes vienen. Desde que empieza una clase, marca a quien faltó."
                />

                {days.length === 0 ? (
                    <div className="grid justify-items-center gap-2 rounded-xl border border-dashed px-6 py-10 text-center">
                        <CalendarX2
                            className="size-6 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="font-medium">
                            No tienes clases en los próximos días
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Cuando el estudio te asigne una, aparece aquí.
                        </p>
                    </div>
                ) : (
                    days.map((day) => (
                        <section
                            key={day.date}
                            aria-labelledby={`day-${day.date}`}
                            className="grid gap-3"
                        >
                            <h2
                                id={`day-${day.date}`}
                                className="text-sm font-medium first-letter:uppercase"
                            >
                                {dayTitle(day)}
                            </h2>
                            <ul className="grid gap-3">
                                {day.sessions.map((session) => (
                                    <SessionCard
                                        key={session.id}
                                        slug={studio.slug}
                                        session={session}
                                    />
                                ))}
                            </ul>
                        </section>
                    ))
                )}
            </div>
        </>
    );
}

MySessions.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [
                  {
                      title: 'Mis clases',
                      href: mySessionsIndex(props.studio.slug),
                  },
              ]
            : []),
    ],
});
