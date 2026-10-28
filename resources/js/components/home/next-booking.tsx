import { Link } from '@inertiajs/react';
import { index as bookIndex } from '@/actions/App/Http/Controllers/Studio/BookingController';
import { index as myBookingsIndex } from '@/actions/App/Http/Controllers/Studio/MyBookingsController';
import type { NextBookingSummary } from '@/components/home/types';
import { Button } from '@/components/ui/button';

function waitingLabel(count: number): string {
    return count === 1
        ? 'Estás en la lista de espera de 1 clase.'
        : `Estás en la lista de espera de ${count} clases.`;
}

/** The student's next class, or a way to book one. */
export default function NextBooking({
    slug,
    booking,
    waitingCount,
}: {
    slug: string;
    booking: NextBookingSummary | null;
    waitingCount: number;
}) {
    return (
        <div className="grid gap-3">
            {booking === null ? (
                <>
                    <p className="text-sm text-muted-foreground">
                        Todavía no tienes clases reservadas.
                    </p>
                    <Button className="justify-self-start" asChild>
                        <Link href={bookIndex(slug)}>Reservar una clase</Link>
                    </Button>
                </>
            ) : (
                <div className="grid gap-0.5">
                    <p className="text-sm text-muted-foreground first-letter:uppercase">
                        {booking.isToday ? 'Hoy' : booking.date} ·{' '}
                        <span className="tabular-nums">
                            {booking.startsAt}–{booking.endsAt}
                        </span>
                    </p>
                    <p className="text-lg font-semibold">
                        {booking.classTypeName}
                    </p>
                    {booking.instructorName && (
                        <p className="text-sm text-muted-foreground">
                            Con {booking.instructorName}
                        </p>
                    )}
                    <p className="mt-2 text-sm">
                        {booking.cancelsOnTime
                            ? `Puedes cancelar gratis hasta ${booking.freeCancellationUntil}.`
                            : 'Ya pasó el plazo de cancelación gratis.'}
                    </p>
                </div>
            )}
            {waitingCount > 0 && (
                <p className="text-sm text-muted-foreground">
                    {waitingLabel(waitingCount)}{' '}
                    <Link
                        href={myBookingsIndex(slug)}
                        className="text-foreground underline underline-offset-4"
                    >
                        Ver mis reservas
                    </Link>
                </p>
            )}
        </div>
    );
}
