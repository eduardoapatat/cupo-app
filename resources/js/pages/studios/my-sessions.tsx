import { Head, usePage } from '@inertiajs/react';
import { CalendarX2 } from 'lucide-react';
import { index as mySessionsIndex } from '@/actions/App/Http/Controllers/Studio/MySessionsController';
import Heading from '@/components/heading';
import TeachingSessionCard from '@/components/teaching-session-card';
import type { TeachingSession } from '@/components/teaching-session-card';
import { dashboard } from '@/routes';
import type { CurrentStudio } from '@/types';

type Day = {
    date: string;
    label: string;
    isToday: boolean;
    isYesterday: boolean;
    sessions: TeachingSession[];
};

type Props = { days: Day[] };

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
                    description="Las clases que das y quiénes vienen. Desde que empieza una clase, marca a quien vino."
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
                                    <TeachingSessionCard
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
