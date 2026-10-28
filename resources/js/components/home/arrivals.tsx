import { Link } from '@inertiajs/react';
import { show as agendaShow } from '@/actions/App/Http/Controllers/Studio/AgendaController';
import type { Arrival } from '@/components/home/types';
import InitialsAvatar from '@/components/initials-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

/**
 * Who is arriving: the class on now, or else the next one today, with the
 * names of who has a place.
 */
export default function Arrivals({
    slug,
    arrival,
}: {
    slug: string;
    arrival: Arrival | null;
}) {
    if (arrival === null) {
        return (
            <p className="text-sm text-muted-foreground">
                No quedan clases hoy.
            </p>
        );
    }

    return (
        <div className="grid gap-3">
            <p className="text-sm">
                <span className="text-muted-foreground">
                    {arrival.isOn ? 'En curso' : 'Próxima'} ·{' '}
                </span>
                <span className="font-medium tabular-nums">
                    {arrival.startsAt}
                </span>{' '}
                <span className="font-medium">{arrival.classTypeName}</span>
                <span className="text-muted-foreground tabular-nums">
                    {' '}
                    · {arrival.bookedCount} de {arrival.capacity}
                </span>
            </p>
            {arrival.attendees.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Nadie la ha reservado.
                </p>
            ) : (
                <ul className="grid gap-2">
                    {arrival.attendees.map((attendee) => (
                        <li
                            key={attendee.id}
                            className="flex min-w-0 items-center gap-3 text-sm"
                        >
                            <InitialsAvatar name={attendee.name} />
                            <span className="truncate">{attendee.name}</span>
                            {attendee.isStaff && (
                                <Badge variant="outline">Equipo</Badge>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            <Button
                variant="outline"
                size="sm"
                className="justify-self-start"
                asChild
            >
                <Link href={agendaShow([slug, arrival.id])}>
                    Ver en la Agenda
                </Link>
            </Button>
        </div>
    );
}
