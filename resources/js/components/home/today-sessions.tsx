import { Link } from '@inertiajs/react';
import { show as agendaShow } from '@/actions/App/Http/Controllers/Studio/AgendaController';
import type { HomeSession } from '@/components/home/types';
import { cn } from '@/lib/utils';

/** Booked spots of today's classes that still happen, over their capacity. */
export function occupancy(sessions: HomeSession[]): {
    booked: number;
    capacity: number;
} {
    return sessions
        .filter((session) => !session.isCancelled)
        .reduce(
            (total, session) => ({
                booked: total.booked + session.bookedCount,
                capacity: total.capacity + session.capacity,
            }),
            { booked: 0, capacity: 0 },
        );
}

/**
 * Today's classes, each with how full it is and a link to its panel in
 * the Agenda.
 */
export default function TodaySessions({
    slug,
    sessions,
}: {
    slug: string;
    sessions: HomeSession[];
}) {
    if (sessions.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">Hoy no hay clases.</p>
        );
    }

    return (
        <ul className="-mx-2 grid">
            {sessions.map((session) => {
                const ratio =
                    session.capacity === 0
                        ? 0
                        : session.bookedCount / session.capacity;

                return (
                    <li key={session.id}>
                        <Link
                            href={agendaShow([slug, session.id])}
                            className={cn(
                                'grid grid-cols-[3rem_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5 rounded-md px-2 py-2 text-sm transition-colors hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                (session.isCancelled || session.hasStarted) &&
                                    'text-muted-foreground',
                            )}
                        >
                            <span className="font-medium tabular-nums">
                                {session.startsAt}
                            </span>
                            <span className="min-w-0">
                                <span
                                    className={cn(
                                        'block truncate font-medium',
                                        session.isCancelled && 'line-through',
                                    )}
                                >
                                    {session.classTypeName}
                                </span>
                                <span className="block truncate text-muted-foreground">
                                    {session.instructorName ?? 'Sin instructor'}
                                </span>
                            </span>
                            <span className="text-right tabular-nums">
                                {session.isCancelled
                                    ? 'Cancelada'
                                    : `${session.bookedCount}/${session.capacity}`}
                            </span>
                            {!session.isCancelled && (
                                <span
                                    className="col-span-2 col-start-2 h-1.5 overflow-hidden rounded-full bg-muted"
                                    aria-hidden="true"
                                >
                                    <span
                                        className="block h-full rounded-full bg-foreground"
                                        style={{
                                            width: `${Math.min(ratio, 1) * 100}%`,
                                        }}
                                    />
                                </span>
                            )}
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}
