import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import { index as bookIndex } from '@/actions/App/Http/Controllers/Studio/BookingController';
import {
    destroy as cancelBooking,
    index as myBookingsIndex,
} from '@/actions/App/Http/Controllers/Studio/MyBookingsController';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import type { CurrentStudio } from '@/types';

type BookingSummary = {
    id: number;
    date: string;
    startsAt: string;
    classTypeName: string;
    instructorName: string | null;
};

type UpcomingBooking = BookingSummary & {
    freeCancellationUntil: string;
    cancelsOnTime: boolean;
};

type PastBooking = BookingSummary & { status: string };

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    upcoming: UpcomingBooking[];
    history: Paginated<PastBooking>;
};

function BookingLine({ booking }: { booking: BookingSummary }) {
    return (
        <div className="grid min-w-0 gap-0.5">
            <p className="text-sm text-muted-foreground first-letter:uppercase">
                {booking.date} · {booking.startsAt}
            </p>
            <h3 className="font-semibold">{booking.classTypeName}</h3>
            {booking.instructorName && (
                <p className="text-sm text-muted-foreground">
                    {booking.instructorName}
                </p>
            )}
        </div>
    );
}

export default function MyBookings({ upcoming, history }: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    return (
        <>
            <Head title="Mis reservas" />

            <div className="flex max-w-2xl flex-col gap-6 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Mis reservas"
                    description="Tus próximas clases y las que ya pasaron."
                />

                <Tabs defaultValue="upcoming" className="gap-4">
                    <TabsList variant="line">
                        <TabsTrigger value="upcoming">
                            Próximas{' '}
                            <span className="text-muted-foreground tabular-nums">
                                ({upcoming.length})
                            </span>
                        </TabsTrigger>
                        <TabsTrigger value="history">Historial</TabsTrigger>
                    </TabsList>

                    <TabsContent value="upcoming">
                        {upcoming.length === 0 ? (
                            <div className="grid justify-items-center gap-3 rounded-xl border border-dashed px-6 py-10 text-center">
                                <CalendarCheck
                                    className="size-6 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <p className="font-medium">
                                    No tienes clases reservadas
                                </p>
                                <Button asChild>
                                    <Link href={bookIndex(studio.slug)}>
                                        Reservar una clase
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <ul className="grid gap-3">
                                {upcoming.map((booking) => (
                                    <li
                                        key={booking.id}
                                        className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 rounded-xl border p-4"
                                    >
                                        <div className="grid gap-1">
                                            <BookingLine booking={booking} />
                                            <p className="text-sm">
                                                {booking.cancelsOnTime
                                                    ? `Puedes cancelar gratis hasta ${booking.freeCancellationUntil}.`
                                                    : 'Ya pasó el plazo de cancelación gratis.'}
                                            </p>
                                        </div>
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="outline">
                                                    Cancelar
                                                </Button>
                                            }
                                            title={`¿Cancelar ${booking.classTypeName} de las ${booking.startsAt}?`}
                                            description={
                                                booking.cancelsOnTime
                                                    ? 'Estás a tiempo: no pierdes la clase.'
                                                    : 'Ya pasó el plazo de cancelación gratis: la clase cuenta como usada.'
                                            }
                                            action={cancelBooking.form([
                                                studio.slug,
                                                booking.id,
                                            ])}
                                            confirmLabel="Cancelar la reserva"
                                            dismissLabel="Volver"
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </TabsContent>

                    <TabsContent value="history" className="grid gap-4">
                        {history.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Aquí verás las clases a las que fuiste y las que
                                cancelaste.
                            </p>
                        ) : (
                            <ul className="divide-y rounded-xl border">
                                {history.data.map((booking) => (
                                    <li
                                        key={booking.id}
                                        className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 p-4"
                                    >
                                        <BookingLine booking={booking} />
                                        <Badge variant="secondary">
                                            {booking.status}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {history.last_page > 1 && (
                            <nav
                                aria-label="Páginas del historial"
                                className="flex items-center justify-between gap-4 text-sm"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!history.prev_page_url}
                                    asChild={!!history.prev_page_url}
                                >
                                    {history.prev_page_url ? (
                                        <Link
                                            href={history.prev_page_url}
                                            only={['history']}
                                            preserveState
                                            preserveScroll
                                        >
                                            Anteriores
                                        </Link>
                                    ) : (
                                        'Anteriores'
                                    )}
                                </Button>
                                <span className="text-muted-foreground tabular-nums">
                                    Página {history.current_page} de{' '}
                                    {history.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!history.next_page_url}
                                    asChild={!!history.next_page_url}
                                >
                                    {history.next_page_url ? (
                                        <Link
                                            href={history.next_page_url}
                                            only={['history']}
                                            preserveState
                                            preserveScroll
                                        >
                                            Más antiguas
                                        </Link>
                                    ) : (
                                        'Más antiguas'
                                    )}
                                </Button>
                            </nav>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

MyBookings.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [
                  {
                      title: 'Mis reservas',
                      href: myBookingsIndex(props.studio.slug),
                  },
              ]
            : []),
    ],
});
