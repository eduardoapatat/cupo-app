import { Form, Head, Link, usePage } from '@inertiajs/react';
import { CalendarX2, Check } from 'lucide-react';
import { useEffect, useRef } from 'react';
import {
    index as bookIndex,
    store as bookSession,
} from '@/actions/App/Http/Controllers/Studio/BookingController';
import { destroy as cancelBooking } from '@/actions/App/Http/Controllers/Studio/MyBookingsController';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CurrentStudio } from '@/types';

type Day = {
    date: string;
    weekday: string;
    dayOfMonth: number;
    sessionCount: number;
};

type SessionState = 'open' | 'booked' | 'full' | 'closed' | 'no_instructor';

type BookableSession = {
    id: number;
    startsAt: string;
    durationMinutes: number;
    classTypeName: string;
    instructorName: string | null;
    spotsLeft: number;
    state: SessionState;
    bookingId: number | null;
    freeCancellationUntil: string;
    cancelsOnTime: boolean;
};

type Props = {
    days: Day[];
    selectedDay: { date: string; label: string };
    sessions: BookableSession[];
};

function spotsLabel(spotsLeft: number): string {
    return spotsLeft === 1 ? 'Queda 1 cupo' : `Quedan ${spotsLeft} cupos`;
}

/**
 * The days a student can book, as a strip she scrolls sideways. Each day
 * reloads only the classes below.
 */
function DayStrip({
    slug,
    days,
    selectedDate,
}: {
    slug: string;
    days: Day[];
    selectedDate: string;
}) {
    const selectedRef = useRef<HTMLAnchorElement>(null);

    // A day further ahead starts in view, not hidden past the edge.
    useEffect(() => {
        selectedRef.current?.scrollIntoView({
            block: 'nearest',
            inline: 'nearest',
        });
    }, [selectedDate]);

    return (
        <nav aria-label="Días" className="-mx-4 overflow-x-auto px-4 pb-1">
            <ul className="flex gap-2">
                {days.map((day) => {
                    const isSelected = day.date === selectedDate;

                    return (
                        <li key={day.date}>
                            <Link
                                ref={isSelected ? selectedRef : undefined}
                                href={bookIndex.url(slug, {
                                    query: { dia: day.date },
                                })}
                                only={['sessions', 'selectedDay']}
                                preserveScroll
                                preserveState
                                replace
                                aria-current={isSelected ? 'date' : undefined}
                                aria-label={`${day.weekday} ${day.dayOfMonth}, ${
                                    day.sessionCount === 0
                                        ? 'sin clases'
                                        : day.sessionCount === 1
                                          ? '1 clase'
                                          : `${day.sessionCount} clases`
                                }`}
                                className={cn(
                                    'flex w-14 flex-col items-center gap-0.5 rounded-lg border py-2 text-sm transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                    isSelected
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'hover:bg-muted',
                                    !isSelected &&
                                        day.sessionCount === 0 &&
                                        'text-muted-foreground',
                                )}
                            >
                                <span className="text-xs">{day.weekday}</span>
                                <span className="text-base font-semibold tabular-nums">
                                    {day.dayOfMonth}
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

function SessionAction({
    slug,
    session,
}: {
    slug: string;
    session: BookableSession;
}) {
    switch (session.state) {
        case 'open':
            return (
                <Form
                    {...bookSession.form([slug, session.id])}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Reservar
                        </Button>
                    )}
                </Form>
            );
        case 'booked':
            return session.bookingId === null ? null : (
                <ConfirmDialog
                    trigger={<Button variant="outline">Cancelar</Button>}
                    title={`¿Cancelar ${session.classTypeName} de las ${session.startsAt}?`}
                    description={
                        session.cancelsOnTime
                            ? 'Estás a tiempo: no pierdes la clase.'
                            : 'Ya pasó el plazo de cancelación gratis: la clase cuenta como usada.'
                    }
                    action={cancelBooking.form([slug, session.bookingId])}
                    confirmLabel="Cancelar la reserva"
                    dismissLabel="Volver"
                />
            );
        case 'full':
            return <Badge variant="secondary">Llena</Badge>;
        case 'closed':
            return <Badge variant="outline">Cerrada</Badge>;
        case 'no_instructor':
            return <Badge variant="outline">Sin instructor</Badge>;
    }
}

/** The second line of a class: who teaches it and what the student can expect. */
function sessionNote(session: BookableSession): string {
    const teacher = session.instructorName ?? 'Sin instructor';

    switch (session.state) {
        case 'open':
            return `${teacher} · ${spotsLabel(session.spotsLeft)}`;
        case 'booked':
            return session.cancelsOnTime
                ? `${teacher} · Puedes cancelar gratis hasta ${session.freeCancellationUntil}`
                : `${teacher} · Ya pasó el plazo de cancelación gratis`;
        case 'full':
            return `${teacher} · Lista de espera, pronto`;
        case 'closed':
            return `${teacher} · Las reservas ya cerraron`;
        case 'no_instructor':
            return 'Todavía no tiene instructor, así que no se puede reservar';
    }
}

export default function Book({ days, selectedDay, sessions }: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    return (
        <>
            <Head title="Reservar" />

            <div className="flex max-w-2xl flex-col gap-6 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Reservar"
                    description={`Las clases de los próximos ${days.length} días. Elige el día y reserva tu lugar.`}
                />

                <DayStrip
                    slug={studio.slug}
                    days={days}
                    selectedDate={selectedDay.date}
                />

                <section aria-labelledby="selected-day" className="grid gap-3">
                    <h2
                        id="selected-day"
                        className="text-sm font-medium first-letter:uppercase"
                    >
                        {selectedDay.label}
                    </h2>

                    {sessions.length === 0 ? (
                        <div className="grid justify-items-center gap-2 rounded-xl border border-dashed px-6 py-10 text-center">
                            <CalendarX2
                                className="size-6 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p className="font-medium">
                                No hay clases este día
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Prueba con otro día de la lista.
                            </p>
                        </div>
                    ) : (
                        <ul className="grid gap-3">
                            {sessions.map((session) => (
                                <li
                                    key={session.id}
                                    className={cn(
                                        'flex flex-wrap items-center justify-between gap-x-4 gap-y-3 rounded-xl border p-4',
                                        session.state === 'booked' &&
                                            'border-foreground',
                                    )}
                                >
                                    <div className="grid min-w-0 gap-0.5">
                                        <p className="flex items-center gap-2 text-sm text-muted-foreground tabular-nums">
                                            <span>
                                                {session.startsAt} ·{' '}
                                                {session.durationMinutes} min
                                            </span>
                                            {session.state === 'booked' && (
                                                <span className="flex items-center gap-1 font-medium text-foreground">
                                                    <Check
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Reservada
                                                </span>
                                            )}
                                        </p>
                                        <h3 className="font-semibold">
                                            {session.classTypeName}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {sessionNote(session)}
                                        </p>
                                    </div>
                                    <SessionAction
                                        slug={studio.slug}
                                        session={session}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

Book.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Reservar', href: bookIndex(props.studio.slug) }]
            : []),
    ],
});
