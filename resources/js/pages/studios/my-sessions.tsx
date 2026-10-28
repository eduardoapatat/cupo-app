import { Head } from '@inertiajs/react';
import { CalendarX2, ChevronDown } from 'lucide-react';
import { index as mySessionsIndex } from '@/actions/App/Http/Controllers/Studio/MySessionsController';
import Heading from '@/components/heading';
import InitialsAvatar from '@/components/initials-avatar';
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
    attendees: string[];
};

type Day = {
    date: string;
    label: string;
    isToday: boolean;
    sessions: TeachingSession[];
};

type Props = { days: Day[] };

function SessionCard({ session }: { session: TeachingSession }) {
    return (
        <li className="rounded-xl border p-4">
            <Collapsible className="grid gap-3">
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
                                Quiénes vienen ({session.attendees.length})
                            </Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <ul className="grid gap-2 px-2">
                                {session.attendees.map((name, index) => (
                                    <li
                                        key={`${name}-${index}`}
                                        className="flex items-center gap-3"
                                    >
                                        <InitialsAvatar name={name} />
                                        <span>{name}</span>
                                    </li>
                                ))}
                            </ul>
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

export default function MySessions({ days }: Props) {
    return (
        <>
            <Head title="Mis clases" />

            <div className="flex max-w-2xl flex-col gap-6 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Mis clases"
                    description="Las clases que das desde hoy, y quiénes vienen a cada una."
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
                                {day.isToday ? `Hoy, ${day.label}` : day.label}
                            </h2>
                            <ul className="grid gap-3">
                                {day.sessions.map((session) => (
                                    <SessionCard
                                        key={session.id}
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
