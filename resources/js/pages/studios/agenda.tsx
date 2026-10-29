import { Form, Head, router, usePage } from '@inertiajs/react';
import { ListPlus, Search, UserPlus } from 'lucide-react';
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
import {
    destroy as removeFromWaitlist,
    store as storeAgendaWaitlist,
} from '@/actions/App/Http/Controllers/Studio/AgendaWaitlistController';
import { store as cancelSession } from '@/actions/App/Http/Controllers/Studio/SessionCancellationController';
import { update as substituteInstructor } from '@/actions/App/Http/Controllers/Studio/SessionInstructorController';
import AgendaHeader from '@/components/agenda/agenda-header';
import DayStrip from '@/components/agenda/day-strip';
import MonthGrid from '@/components/agenda/month-grid';
import TimeGrid from '@/components/agenda/time-grid';
import type {
    AgendaFocus,
    AgendaMonth,
    AgendaToday,
    AgendaView,
    AgendaWeek,
    CalendarDay,
    CalendarSession,
} from '@/components/agenda/types';
import AttendanceList from '@/components/attendance-list';
import type { Attendee } from '@/components/attendance-list';
import InitialsAvatar from '@/components/initials-avatar';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { useIsMobile } from '@/hooks/use-mobile';
import { fieldErrorProps, focusFirstError, wentThrough } from '@/lib/form';
import { formatPhone } from '@/lib/phone';
import { dashboard } from '@/routes';
import type { CurrentStudio, Option } from '@/types';

type WaitingPerson = { id: number; name: string; position: number };

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
    attendanceIsOpen: boolean;
    attendanceClosesLabel: string;
    canCorrectClosedAttendance: boolean;
    /** Whether a student can still join the waitlist, once the class is full. */
    waitlistIsOpen: boolean;
    waitlist: WaitingPerson[];
    teamOptions: Option[];
    teachers: Option[];
    can: {
        returnClass: boolean;
        substitute: boolean;
        cancel: boolean;
        markAttendance: boolean;
    };
};

type Props = {
    today: AgendaToday;
    focus: AgendaFocus;
    week: AgendaWeek;
    days: CalendarDay[];
    /** The view asked for in the URL, or null to let the screen choose. */
    view: AgendaView | null;
    /** Only when the month view is asked for. */
    month: AgendaMonth | null;
    session: SessionDetail | null;
};

type FoundStudent = {
    id: number;
    name: string;
    phone: string | null;
    alreadyBooked: boolean;
    alreadyWaiting: boolean;
};

/** What reloads when the Agenda moves: the calendar and the panel, nothing else. */
const CALENDAR_RELOAD = [
    'today',
    'focus',
    'week',
    'days',
    'view',
    'month',
    'session',
];

function agendaQuery(date: string, view: AgendaView) {
    return { query: { fecha: date, vista: view } };
}

/**
 * Book a student of the studio, or put her in the waitlist of a full
 * class: search by name, email or phone, and pick from the results.
 */
function BookStudentDialog({
    slug,
    session,
    waitlist = false,
}: {
    slug: string;
    session: SessionDetail;
    waitlist?: boolean;
}) {
    const title = waitlist
        ? 'Anotar en la lista de espera'
        : 'Reservar por una alumna';
    const actionLabel = waitlist ? 'Anotar' : 'Reservar';
    const action = waitlist
        ? storeAgendaWaitlist.form([slug, session.id])
        : storeAgendaBooking.form([slug, session.id]);

    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [students, setStudents] = useState<FoundStudent[] | null>(null);
    const [failed, setFailed] = useState(false);

    // Closing starts the next search from scratch.
    function changeOpen(next: boolean) {
        setOpen(next);

        if (!next) {
            setSearch('');
            setStudents(null);
            setFailed(false);
        }
    }

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
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    return response.json() as Promise<{
                        students: FoundStudent[];
                    }>;
                })
                .then((data) => {
                    setFailed(false);
                    setStudents(data.students);
                })
                .catch((error: unknown) => {
                    if (
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    ) {
                        return;
                    }

                    setFailed(true);
                    setStudents([]);
                });
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, search, slug, session.id]);

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button>
                    {waitlist ? <ListPlus /> : <UserPlus />}
                    {title}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>
                    {session.classTypeName}, {session.date} a las{' '}
                    {session.startsAt}.
                    {waitlist &&
                        ' Si se libera un lugar a tiempo, queda reservada sola.'}
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
                    {failed ? (
                        <p className="py-4 text-sm text-muted-foreground">
                            No pudimos buscar. Recarga la página e inténtalo de
                            nuevo.
                        </p>
                    ) : students === null ? (
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
                                                    {formatPhone(student.phone)}
                                                </span>
                                            )}
                                        </span>
                                    </span>
                                    {student.alreadyBooked ||
                                    (waitlist && student.alreadyWaiting) ? (
                                        <Badge variant="secondary">
                                            {student.alreadyBooked
                                                ? 'Ya está'
                                                : 'Ya espera'}
                                        </Badge>
                                    ) : (
                                        <Form
                                            {...action}
                                            options={{ preserveScroll: true }}
                                            onSuccess={(page) => {
                                                if (wentThrough(page)) {
                                                    changeOpen(false);
                                                }
                                            }}
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="user_id"
                                                        value={student.id}
                                                    />
                                                    {!waitlist && (
                                                        <input
                                                            type="hidden"
                                                            name="kind"
                                                            value="student"
                                                        />
                                                    )}
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={processing}
                                                        aria-label={`${actionLabel} a ${student.name}`}
                                                    >
                                                        {processing && (
                                                            <Spinner />
                                                        )}
                                                        {actionLabel}
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
                    onSuccess={(page) => {
                        if (wentThrough(page)) {
                            setOpen(false);
                        }
                    }}
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
                    onSuccess={(page) => {
                        if (wentThrough(page)) {
                            setOpen(false);
                        }
                    }}
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

/** The people waiting for a spot, in order, each of whom can be taken out. */
function WaitlistSection({
    slug,
    session,
}: {
    slug: string;
    session: SessionDetail;
}) {
    return (
        <section
            aria-labelledby="waitlist"
            className="grid grid-cols-[minmax(0,1fr)] gap-2"
        >
            <h3 id="waitlist" className="text-sm font-medium">
                Lista de espera ({session.waitlist.length})
            </h3>
            {!session.waitlistIsOpen && (
                <p className="text-sm text-muted-foreground">
                    Ya pasó el corte: un cupo que se libere queda libre para
                    cualquiera.
                </p>
            )}
            <ol className="divide-y">
                {session.waitlist.map((person) => (
                    <li
                        key={person.id}
                        className="flex items-center justify-between gap-3 py-2"
                    >
                        <span className="flex min-w-0 items-center gap-3">
                            <span className="w-5 shrink-0 text-right text-sm text-muted-foreground tabular-nums">
                                {person.position}.
                            </span>
                            <InitialsAvatar name={person.name} />
                            <span className="truncate">{person.name}</span>
                        </span>
                        <Form
                            {...removeFromWaitlist.form([
                                slug,
                                session.id,
                                person.id,
                            ])}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="ghost"
                                    disabled={processing}
                                    aria-label={`Quitar a ${person.name} de la lista de espera`}
                                >
                                    {processing && <Spinner />}
                                    Quitar
                                </Button>
                            )}
                        </Form>
                    </li>
                ))}
            </ol>
        </section>
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
    const marksAttendance = !session.isCancelled && session.hasStarted;
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
                        {session.attendanceIsOpen
                            ? `La clase ya empezó. Marca a quien vino, hasta ${session.attendanceClosesLabel}; quien no quede marcado cuenta como que faltó.`
                            : session.canCorrectClosedAttendance
                              ? 'La asistencia de esta clase ya cerró, pero como dueña puedes corregirla.'
                              : 'Esta clase ya terminó y su asistencia ya cerró.'}
                    </p>
                )}

                {canAct &&
                    (isFull ? (
                        session.waitlistIsOpen ? (
                            <div className="grid grid-cols-[minmax(0,1fr)] gap-2">
                                <p className="text-sm text-muted-foreground">
                                    La clase está llena. Si una alumna quiere
                                    venir, anótala en la lista de espera.
                                </p>
                                <BookStudentDialog
                                    slug={slug}
                                    session={session}
                                    waitlist
                                />
                            </div>
                        ) : (
                            <p className="rounded-lg bg-muted p-3 text-sm">
                                La clase está llena y la lista de espera ya
                                cerró. Para sumar a alguien, primero cancela una
                                reserva.
                            </p>
                        )
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
                        {marksAttendance ? 'Asistencia' : 'Quiénes vienen'} (
                        {session.attendees.length})
                    </h3>
                    {session.attendees.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {marksAttendance
                                ? 'Nadie tenía reserva.'
                                : 'Nadie todavía.'}
                        </p>
                    ) : marksAttendance ? (
                        <AttendanceList
                            slug={slug}
                            sessionId={session.id}
                            attendees={session.attendees}
                            canMark={
                                session.can.markAttendance &&
                                (session.attendanceIsOpen ||
                                    session.canCorrectClosedAttendance)
                            }
                        />
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

                {canAct && session.waitlist.length > 0 && (
                    <WaitlistSection slug={slug} session={session} />
                )}

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

export default function Agenda({
    today,
    focus,
    week,
    days,
    view: requestedView,
    month,
    session,
}: Props) {
    const { studio } = usePage().props;
    const isMobile = useIsMobile();
    const view: AgendaView = requestedView ?? (isMobile ? 'dia' : 'semana');
    // The panel keeps the last class while it slides out.
    const [shown, setShown] = useState(session);

    if (session !== null && session !== shown) {
        setShown(session);
    }

    if (!studio) {
        return null;
    }

    const slug = studio.slug;
    const shownDays =
        view === 'dia' ? days.filter((day) => day.date === focus.date) : days;
    const dayCount =
        days
            .find((day) => day.date === focus.date)
            ?.sessions.filter((card) => !card.isCancelled).length ?? 0;

    function goTo(date: string, nextView: AgendaView) {
        router.get(
            agendaIndex.url(slug, agendaQuery(date, nextView)),
            {},
            {
                only: CALENDAR_RELOAD,
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function sessionHref(card: CalendarSession): string {
        return agendaShow.url([slug, card.id], agendaQuery(focus.date, view));
    }

    function closePanel() {
        router.get(
            agendaIndex.url(slug, agendaQuery(focus.date, view)),
            {},
            { only: ['session'], preserveState: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Agenda" />

            <div className="flex flex-col gap-4 px-4 py-6">
                <AgendaHeader
                    today={today}
                    week={week}
                    month={month}
                    focus={focus}
                    view={view}
                    dayCount={dayCount}
                    goTo={goTo}
                />

                {view === 'dia' && (
                    <DayStrip
                        days={days}
                        focusDate={focus.date}
                        onSelect={(date) => goTo(date, 'dia')}
                    />
                )}

                {view === 'mes' && month ? (
                    <MonthGrid
                        month={month}
                        openSessionId={session?.id ?? null}
                        sessionHref={sessionHref}
                        onOpenDay={(date) => goTo(date, 'dia')}
                    />
                ) : (
                    <TimeGrid
                        days={days}
                        shownDays={shownDays}
                        view={view === 'mes' ? 'semana' : view}
                        openSessionId={session?.id ?? null}
                        sessionHref={sessionHref}
                    />
                )}
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
                            slug={slug}
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
