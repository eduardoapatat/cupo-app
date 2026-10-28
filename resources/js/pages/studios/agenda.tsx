import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Search, UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    destroy as cancelAgendaBooking,
    store as storeAgendaBooking,
} from '@/actions/App/Http/Controllers/Studio/AgendaBookingController';
import {
    index as agendaIndex,
    show as agendaShow,
    students as agendaStudents,
} from '@/actions/App/Http/Controllers/Studio/AgendaController';
import { store as cancelSession } from '@/actions/App/Http/Controllers/Studio/SessionCancellationController';
import { update as substituteInstructor } from '@/actions/App/Http/Controllers/Studio/SessionInstructorController';
import InitialsAvatar from '@/components/initials-avatar';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CurrentStudio, Option } from '@/types';

type Week = {
    start: string;
    label: string;
    previous: string;
    next: string;
    isCurrent: boolean;
};

type SessionCard = {
    id: number;
    startsAt: string;
    classTypeName: string;
    instructorName: string | null;
    bookedCount: number;
    capacity: number;
    isCancelled: boolean;
    hasStarted: boolean;
};

type Day = {
    date: string;
    label: string;
    longLabel: string;
    isToday: boolean;
    sessions: SessionCard[];
};

type Attendee = { id: number; name: string; isStaff: boolean };

type SessionDetail = {
    id: number;
    classTypeName: string;
    date: string;
    startsAt: string;
    endsAt: string;
    instructorId: string | null;
    instructorName: string | null;
    bookedCount: number;
    capacity: number;
    isCancelled: boolean;
    cancellationReason: string | null;
    hasStarted: boolean;
    cancelsOnTime: boolean;
    attendees: Attendee[];
    teamOptions: Option[];
    teachers: Option[];
    can: { returnClass: boolean; substitute: boolean; cancel: boolean };
};

type Props = {
    week: Week;
    days: Day[];
    session: SessionDetail | null;
};

type FoundStudent = {
    id: number;
    name: string;
    phone: string | null;
    alreadyBooked: boolean;
};

/** Only the week and the panel reload; the rest of the page stays. */
const WEEK_RELOAD = ['week', 'days', 'session'];

function weekUrl(slug: string, start: string): string {
    return agendaIndex.url(slug, { query: { semana: start } });
}

function occupancy(session: { bookedCount: number; capacity: number }) {
    return `${session.bookedCount}/${session.capacity}`;
}

function SessionLink({
    slug,
    weekStart,
    session,
    isOpen,
    dayLabel,
}: {
    slug: string;
    weekStart: string;
    session: SessionCard;
    isOpen: boolean;
    dayLabel: string;
}) {
    const isFull = session.bookedCount >= session.capacity;

    return (
        <Link
            href={agendaShow.url([slug, session.id], {
                query: { semana: weekStart },
            })}
            only={['session']}
            preserveState
            preserveScroll
            data-session-id={session.id}
            aria-current={isOpen ? 'true' : undefined}
            aria-label={`${session.classTypeName} del ${dayLabel} a las ${session.startsAt}, ${
                session.isCancelled
                    ? 'cancelada'
                    : `${session.bookedCount} de ${session.capacity} cupos`
            }`}
            className={cn(
                buttonVariants({ variant: 'outline' }),
                'h-auto w-full flex-col items-stretch gap-0.5 px-3 py-2 text-left font-normal whitespace-normal',
                // Dashed until it has an instructor: it cannot be booked yet.
                session.instructorName === null && 'border-dashed',
                isOpen && 'border-foreground',
                (session.isCancelled || session.hasStarted) &&
                    'text-muted-foreground',
            )}
        >
            <span className="flex items-baseline justify-between gap-2">
                <span
                    className={cn(
                        'font-medium tabular-nums',
                        session.isCancelled && 'line-through',
                    )}
                >
                    {session.startsAt}
                </span>
                {session.isCancelled ? (
                    <span className="text-xs">Cancelada</span>
                ) : (
                    <span
                        className={cn(
                            'text-xs tabular-nums',
                            isFull ? 'font-medium' : 'text-muted-foreground',
                        )}
                    >
                        {isFull ? 'Llena' : occupancy(session)}
                    </span>
                )}
            </span>
            <span className={cn(session.isCancelled && 'line-through')}>
                {session.classTypeName}
            </span>
            <span className="text-muted-foreground">
                {session.instructorName ?? 'Sin instructor'}
            </span>
        </Link>
    );
}

/**
 * Book a student of the studio: search by name, email or phone, and book
 * from the results.
 */
function BookStudentDialog({
    slug,
    session,
}: {
    slug: string;
    session: SessionDetail;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [students, setStudents] = useState<FoundStudent[] | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(
                agendaStudents.url([slug, session.id], {
                    query: { buscar: search.trim() || undefined },
                }),
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            )
                .then((response) => response.json())
                .then((data: { students: FoundStudent[] }) =>
                    setStudents(data.students),
                )
                .catch(() => {});
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, search, slug, session.id]);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setSearch('');
                    setStudents(null);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button>
                    <UserPlus />
                    Reservar por una alumna
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Reservar por una alumna</DialogTitle>
                <DialogDescription>
                    {session.classTypeName}, {session.date} a las{' '}
                    {session.startsAt}.
                </DialogDescription>

                <div className="relative">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        type="text"
                        enterKeyHint="search"
                        aria-label="Buscar alumna"
                        placeholder="Nombre, celular o correo"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="pl-9"
                        autoFocus
                    />
                </div>

                <div
                    aria-live="polite"
                    className="grid max-h-72 overflow-y-auto"
                >
                    {students === null ? (
                        <p className="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                            <Spinner /> Buscando…
                        </p>
                    ) : students.length === 0 ? (
                        <p className="py-4 text-sm text-muted-foreground">
                            No encontramos alumnas con esa búsqueda.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {students.map((student) => (
                                <li
                                    key={student.id}
                                    className="flex items-center justify-between gap-3 py-2"
                                >
                                    <span className="flex min-w-0 items-center gap-3">
                                        <InitialsAvatar name={student.name} />
                                        <span className="grid min-w-0 leading-tight">
                                            <span className="truncate font-medium">
                                                {student.name}
                                            </span>
                                            {student.phone && (
                                                <span className="truncate text-sm text-muted-foreground">
                                                    {student.phone}
                                                </span>
                                            )}
                                        </span>
                                    </span>
                                    {student.alreadyBooked ? (
                                        <Badge variant="secondary">
                                            Ya está
                                        </Badge>
                                    ) : (
                                        <Form
                                            {...storeAgendaBooking.form([
                                                slug,
                                                session.id,
                                            ])}
                                            options={{ preserveScroll: true }}
                                            onSuccess={() => setOpen(false)}
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="user_id"
                                                        value={student.id}
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="kind"
                                                        value="student"
                                                    />
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={processing}
                                                        aria-label={`Reservar a ${student.name}`}
                                                    >
                                                        {processing && (
                                                            <Spinner />
                                                        )}
                                                        Reservar
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function AddStaffForm({
    slug,
    session,
}: {
    slug: string;
    session: SessionDetail;
}) {
    if (session.teamOptions.length === 0) {
        return null;
    }

    return (
        <Form
            {...storeAgendaBooking.form([slug, session.id])}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="grid grid-cols-[minmax(0,1fr)] gap-2"
        >
            {({ processing }) => (
                <>
                    <Label htmlFor="staff_user_id">Anotar al equipo</Label>
                    <div className="flex gap-2">
                        <input type="hidden" name="kind" value="staff" />
                        <Select name="user_id" required>
                            <SelectTrigger
                                id="staff_user_id"
                                className="w-full min-w-0"
                            >
                                <SelectValue placeholder="Elige a alguien" />
                            </SelectTrigger>
                            <SelectContent>
                                {session.teamOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Anotar
                        </Button>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Ocupa un cupo, como cualquier reserva.
                    </p>
                </>
            )}
        </Form>
    );
}

function SubstituteForm({
    slug,
    session,
}: {
    slug: string;
    session: SessionDetail;
}) {
    return (
        <Form
            {...substituteInstructor.form([slug, session.id])}
            options={{ preserveScroll: true }}
            onError={(errors) => focusFirstError(errors)}
            className="grid grid-cols-[minmax(0,1fr)] gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <Label htmlFor="instructor_id">Instructor</Label>
                    <div className="flex gap-2">
                        <Select
                            name="instructor_id"
                            defaultValue={session.instructorId ?? undefined}
                            required
                        >
                            <SelectTrigger
                                id="instructor_id"
                                {...fieldErrorProps(
                                    'instructor_id',
                                    errors.instructor_id,
                                )}
                                className="w-full min-w-0"
                            >
                                <SelectValue placeholder="Sin instructor" />
                            </SelectTrigger>
                            <SelectContent>
                                {session.teachers.map((teacher) => (
                                    <SelectItem
                                        key={teacher.value}
                                        value={teacher.value}
                                    >
                                        {teacher.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Cambiar
                        </Button>
                    </div>
                    <InputError
                        id="instructor_id-error"
                        message={errors.instructor_id}
                    />
                    <p className="text-sm text-muted-foreground">
                        Solo en esta clase; el horario semanal no cambia.
                    </p>
                </>
            )}
        </Form>
    );
}

/**
 * Cancel someone's booking. After the free cancellation window the class
 * counts as used, unless the owner returns it with a reason.
 */
function CancelBookingDialog({
    slug,
    session,
    attendee,
}: {
    slug: string;
    session: SessionDetail;
    attendee: Attendee;
}) {
    const [open, setOpen] = useState(false);
    const [returnClass, setReturnClass] = useState(false);
    const canReturn = !session.cancelsOnTime && session.can.returnClass;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setReturnClass(false);
            }}
        >
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    aria-label={`Cancelar la reserva de ${attendee.name}`}
                >
                    Cancelar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    ¿Cancelar la reserva de {attendee.name}?
                </DialogTitle>
                <DialogDescription>
                    {session.cancelsOnTime
                        ? 'Está a tiempo: no pierde la clase.'
                        : returnClass
                          ? 'Ya pasó el plazo de cancelación gratis, pero le devuelves la clase: queda cancelada a tiempo.'
                          : 'Ya pasó el plazo de cancelación gratis: la clase cuenta como usada.'}
                </DialogDescription>

                <Form
                    {...cancelAgendaBooking.form([
                        slug,
                        session.id,
                        attendee.id,
                    ])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) => focusFirstError(errors)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {canReturn && (
                                <div className="grid gap-4 rounded-lg border p-3">
                                    <div className="flex items-center gap-3">
                                        <input
                                            type="hidden"
                                            name="return_class"
                                            value={returnClass ? '1' : '0'}
                                        />
                                        <Checkbox
                                            id="return_class"
                                            checked={returnClass}
                                            onCheckedChange={(checked) =>
                                                setReturnClass(checked === true)
                                            }
                                        />
                                        <Label htmlFor="return_class">
                                            Devolverle la clase
                                        </Label>
                                    </div>
                                    {returnClass && (
                                        <div className="grid grid-cols-[minmax(0,1fr)] gap-2">
                                            <Label htmlFor="reason">
                                                Motivo
                                            </Label>
                                            <Input
                                                id="reason"
                                                {...fieldErrorProps(
                                                    'reason',
                                                    errors.reason,
                                                )}
                                                name="reason"
                                                maxLength={200}
                                                required
                                                placeholder="Se enfermó"
                                            />
                                            <InputError
                                                id="reason-error"
                                                message={errors.reason}
                                            />
                                        </div>
                                    )}
                                </div>
                            )}
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Volver</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Cancelar la reserva
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CancelSessionDialog({
    slug,
    session,
}: {
    slug: string;
    session: SessionDetail;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" className="justify-start">
                    Cancelar la clase
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>¿Cancelar esta clase?</DialogTitle>
                <DialogDescription>
                    {session.attendees.length === 0
                        ? 'Nadie la ha reservado todavía.'
                        : `Sus ${session.attendees.length} reservas quedan canceladas a tiempo, así que nadie pierde la clase.`}
                </DialogDescription>

                <Form
                    {...cancelSession.form([slug, session.id])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) => focusFirstError(errors)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid grid-cols-[minmax(0,1fr)] gap-2">
                                <Label htmlFor="session_reason">Motivo</Label>
                                <Input
                                    id="session_reason"
                                    {...fieldErrorProps(
                                        'session_reason',
                                        errors.reason,
                                    )}
                                    name="reason"
                                    maxLength={200}
                                    required
                                    placeholder="Corte de luz"
                                />
                                <InputError
                                    id="session_reason-error"
                                    message={errors.reason}
                                />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Volver</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Cancelar la clase
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function SessionPanel({
    slug,
    session,
}: {
    slug: string;
    session: SessionDetail;
}) {
    const canAct = !session.isCancelled && !session.hasStarted;
    const isFull = session.bookedCount >= session.capacity;

    return (
        <>
            <SheetHeader>
                <SheetTitle>{session.classTypeName}</SheetTitle>
                <SheetDescription className="first-letter:uppercase">
                    {session.date} · {session.startsAt}–{session.endsAt}
                </SheetDescription>
            </SheetHeader>

            <div className="grid grid-cols-[minmax(0,1fr)] gap-6 px-4">
                <dl className="grid grid-cols-2 gap-4 text-sm">
                    <div className="grid gap-1">
                        <dt className="text-muted-foreground">Ocupación</dt>
                        <dd className="font-medium tabular-nums">
                            {session.bookedCount} de {session.capacity}
                        </dd>
                    </div>
                    <div className="grid gap-1">
                        <dt className="text-muted-foreground">Instructor</dt>
                        <dd className="font-medium">
                            {session.instructorName ?? 'Sin instructor'}
                        </dd>
                    </div>
                </dl>

                {session.isCancelled && (
                    <p className="rounded-lg bg-muted p-3 text-sm">
                        Clase cancelada
                        {session.cancellationReason
                            ? `: ${session.cancellationReason}`
                            : '.'}
                    </p>
                )}
                {!session.isCancelled && session.hasStarted && (
                    <p className="rounded-lg bg-muted p-3 text-sm">
                        Esta clase ya empezó, así que ya no se puede cambiar.
                    </p>
                )}

                {canAct &&
                    (isFull ? (
                        <p className="rounded-lg bg-muted p-3 text-sm">
                            La clase está llena. Para sumar a alguien, primero
                            cancela una reserva.
                        </p>
                    ) : (
                        <div className="grid grid-cols-[minmax(0,1fr)] gap-4">
                            <BookStudentDialog slug={slug} session={session} />
                            <AddStaffForm slug={slug} session={session} />
                        </div>
                    ))}

                <section
                    aria-labelledby="attendees"
                    className="grid grid-cols-[minmax(0,1fr)] gap-2"
                >
                    <h3 id="attendees" className="text-sm font-medium">
                        Quiénes vienen ({session.attendees.length})
                    </h3>
                    {session.attendees.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nadie todavía.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {session.attendees.map((attendee) => (
                                <li
                                    key={attendee.id}
                                    className="flex items-center justify-between gap-3 py-2"
                                >
                                    <span className="flex min-w-0 items-center gap-3">
                                        <InitialsAvatar name={attendee.name} />
                                        <span className="truncate">
                                            {attendee.name}
                                        </span>
                                        {attendee.isStaff && (
                                            <Badge variant="outline">
                                                Equipo
                                            </Badge>
                                        )}
                                    </span>
                                    {canAct && (
                                        <CancelBookingDialog
                                            slug={slug}
                                            session={session}
                                            attendee={attendee}
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {canAct && session.can.substitute && (
                    <SubstituteForm
                        key={`${session.id}-${session.instructorId}`}
                        slug={slug}
                        session={session}
                    />
                )}
            </div>

            {canAct && session.can.cancel && (
                <SheetFooter>
                    <Separator className="mb-2" />
                    <CancelSessionDialog slug={slug} session={session} />
                </SheetFooter>
            )}
        </>
    );
}

export default function Agenda({ week, days, session }: Props) {
    const { studio } = usePage().props;
    // The panel keeps the last class while it slides out.
    const [shown, setShown] = useState(session);

    if (session !== null && session !== shown) {
        setShown(session);
    }

    if (!studio) {
        return null;
    }

    function closePanel() {
        if (!studio) {
            return;
        }

        router.get(
            weekUrl(studio.slug, week.start),
            {},
            { only: ['session'], preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Agenda" />

            <div className="flex flex-col gap-6 px-4 py-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="grid gap-0.5">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Agenda
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Las clases de la semana y quiénes vienen.
                        </p>
                    </div>

                    <nav
                        aria-label="Semanas"
                        className="flex items-center gap-2"
                    >
                        <Button variant="outline" size="icon" asChild>
                            <Link
                                href={weekUrl(studio.slug, week.previous)}
                                only={WEEK_RELOAD}
                                preserveState
                                aria-label="Semana anterior"
                            >
                                <ChevronLeft />
                            </Link>
                        </Button>
                        <span className="min-w-48 text-center text-sm font-medium">
                            {week.label}
                        </span>
                        <Button variant="outline" size="icon" asChild>
                            <Link
                                href={weekUrl(studio.slug, week.next)}
                                only={WEEK_RELOAD}
                                preserveState
                                aria-label="Semana siguiente"
                            >
                                <ChevronRight />
                            </Link>
                        </Button>
                        {!week.isCurrent && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={agendaIndex.url(studio.slug)}
                                    only={WEEK_RELOAD}
                                    preserveState
                                >
                                    Hoy
                                </Link>
                            </Button>
                        )}
                    </nav>
                </header>

                <div className="grid gap-4 xl:grid-cols-7">
                    {days.map((day) => (
                        <section
                            key={day.date}
                            aria-labelledby={`day-${day.date}`}
                            className={cn(
                                'grid content-start gap-2 rounded-lg bg-muted/50 p-2',
                                day.isToday && 'ring-1 ring-foreground/20',
                            )}
                        >
                            <h2
                                id={`day-${day.date}`}
                                className="flex items-baseline justify-between gap-2 px-1 text-sm font-medium"
                            >
                                <span>
                                    {day.label}
                                    {day.isToday && (
                                        <span className="ml-1.5 text-muted-foreground">
                                            · Hoy
                                        </span>
                                    )}
                                </span>
                                {day.sessions.length > 0 && (
                                    <span className="text-muted-foreground tabular-nums">
                                        {day.sessions.length}
                                    </span>
                                )}
                            </h2>

                            {day.sessions.length === 0 ? (
                                <p className="px-1 text-sm text-muted-foreground">
                                    Sin clases
                                </p>
                            ) : (
                                <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-1">
                                    {day.sessions.map((card) => (
                                        <SessionLink
                                            key={card.id}
                                            slug={studio.slug}
                                            weekStart={week.start}
                                            session={card}
                                            isOpen={session?.id === card.id}
                                            dayLabel={day.longLabel}
                                        />
                                    ))}
                                </div>
                            )}
                        </section>
                    ))}
                </div>
            </div>

            <Sheet
                open={session !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        closePanel();
                    }
                }}
            >
                <SheetContent
                    className="overflow-y-auto"
                    onCloseAutoFocus={(event) => {
                        // The panel has no trigger of its own: the focus goes
                        // back to the class that opened it.
                        event.preventDefault();
                        document
                            .querySelector<HTMLElement>(
                                `[data-session-id="${shown?.id}"]`,
                            )
                            ?.focus();
                    }}
                >
                    {shown && (
                        <SessionPanel
                            key={shown.id}
                            slug={studio.slug}
                            session={shown}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

Agenda.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Agenda', href: agendaIndex(props.studio.slug) }]
            : []),
    ],
});
