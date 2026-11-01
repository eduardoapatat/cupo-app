import { Head, router, usePage } from '@inertiajs/react';
import { CalendarX2, Check } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { index as mySessionsIndex } from '@/actions/App/Http/Controllers/Studio/MySessionsController';
import DayStrip from '@/components/agenda/day-strip';
import AttendanceList from '@/components/attendance-list';
import type { Attendee } from '@/components/attendance-list';
import Heading from '@/components/heading';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CurrentStudio } from '@/types';

/** Where the list of a class stands (MySessionsController::state). */
type ListState =
    | 'to_mark'
    | 'taken'
    | 'upcoming'
    | 'closed'
    | 'empty'
    | 'cancelled';

type StripDay = {
    date: string;
    weekday: string;
    dayOfMonth: number;
    longLabel: string;
    isToday: boolean;
    /** The ids of her classes that day. */
    sessions: number[];
};

type SessionRow = {
    id: number;
    startsAt: string;
    endsAt: string;
    classTypeName: string;
    bookedCount: number;
    capacity: number;
    state: ListState;
};

type SelectedSession = SessionRow & {
    attendanceIsOpen: boolean;
    /** «18:45»: when its list can be taken. */
    attendanceOpensAt: string;
    attendanceClosesLabel: string;
    /** When the list was last saved, in words. */
    takenAt: string | null;
    attendees: Attendee[];
};

type Props = {
    days: StripDay[];
    selectedDate: string;
    dayLabel: string;
    sessions: SessionRow[];
    selected: SelectedSession | null;
};

function StateBadge({ state }: { state: ListState }) {
    switch (state) {
        case 'to_mark':
            return <Badge>Por pasar lista</Badge>;
        case 'taken':
            return (
                <Badge variant="secondary">
                    <Check aria-hidden="true" />
                    Lista pasada
                </Badge>
            );
        case 'upcoming':
            return <Badge variant="outline">Próxima</Badge>;
        case 'closed':
            return <Badge variant="outline">Asistencia cerrada</Badge>;
        case 'empty':
            return <Badge variant="outline">Sin reservas</Badge>;
        case 'cancelled':
            return <Badge variant="outline">Cancelada</Badge>;
    }
}

/** The list of the chosen class: take it, or read it. */
function SessionDetail({
    slug,
    session,
    onDirtyChange,
}: {
    slug: string;
    session: SelectedSession;
    onDirtyChange: (dirty: boolean) => void;
}) {
    let body: ReactNode;

    if (session.state === 'cancelled') {
        body = (
            <p className="text-sm text-muted-foreground">
                El estudio canceló esta clase.
            </p>
        );
    } else if (session.attendees.length === 0) {
        body = (
            <p className="text-sm text-muted-foreground">
                Nadie reservó esta clase.
            </p>
        );
    } else if (session.attendanceIsOpen) {
        body = (
            <AttendanceList
                key={session.id}
                slug={slug}
                sessionId={session.id}
                attendees={session.attendees}
                canMark
                needsSaving={session.state === 'to_mark'}
                onDirtyChange={onDirtyChange}
                footnote={`Quien no quede marcada cuenta como que faltó. Puedes cambiarla hasta ${session.attendanceClosesLabel}.`}
            />
        );
    } else {
        body = (
            <div className="grid gap-2">
                <p className="text-sm text-muted-foreground">
                    {session.state === 'upcoming'
                        ? `Podrás pasar lista desde las ${session.attendanceOpensAt}. Por ahora, quiénes vienen:`
                        : 'La asistencia ya cerró.'}
                </p>
                <AttendanceList
                    key={session.id}
                    slug={slug}
                    sessionId={session.id}
                    attendees={session.attendees}
                    canMark={false}
                />
            </div>
        );
    }

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <StateBadge state={session.state} />
                <span className="text-muted-foreground tabular-nums">
                    {session.bookedCount} de {session.capacity} cupos
                </span>
            </div>
            {session.takenAt && (
                <p className="text-sm text-muted-foreground first-letter:uppercase">
                    Guardada el {session.takenAt}.
                </p>
            )}
            {body}
        </div>
    );
}

export default function MySessions({
    days,
    selectedDate,
    dayLabel,
    sessions,
    selected,
}: Props) {
    const { studio } = usePage().props;
    const isMobile = useIsMobile();
    const [sheetOpen, setSheetOpen] = useState(false);
    const dirty = useRef(false);
    const [pending, setPending] = useState<(() => void) | null>(null);
    const onDirtyChange = useCallback((next: boolean) => {
        dirty.current = next;
    }, []);

    if (!studio) {
        return null;
    }

    const slug = studio.slug;

    // Leaving a list with changes not saved asks first.
    function guard(go: () => void) {
        if (dirty.current) {
            setPending(() => go);
        } else {
            go();
        }
    }

    function visit(date: string, classId?: number) {
        dirty.current = false;
        router.get(
            mySessionsIndex.url(slug, {
                query: { dia: date, clase: classId },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['selectedDate', 'dayLabel', 'sessions', 'selected'],
                onSuccess: () => setSheetOpen(classId !== undefined),
            },
        );
    }

    const hasClasses = days.some((day) => day.sessions.length > 0);
    const detailTitle = selected
        ? `${selected.classTypeName} · ${selected.startsAt}–${selected.endsAt}`
        : '';

    return (
        <>
            <Head title="Mis clases" />

            <div className="flex max-w-5xl flex-col gap-6 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Mis clases"
                    description="Elige un día y una clase para ver quiénes vienen y pasar lista."
                />

                {!hasClasses ? (
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
                    <>
                        <DayStrip
                            days={days}
                            focusDate={selectedDate}
                            onSelect={(date) => guard(() => visit(date))}
                        />

                        <div className="grid items-start gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
                            <section
                                aria-labelledby="day-sessions"
                                className="grid gap-3"
                            >
                                <h2
                                    id="day-sessions"
                                    className="text-sm font-medium first-letter:uppercase"
                                >
                                    {dayLabel}
                                </h2>
                                {sessions.length === 0 ? (
                                    <p className="rounded-xl border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                                        No das clases este día.
                                    </p>
                                ) : (
                                    <ul className="grid gap-2">
                                        {sessions.map((session) => {
                                            const isSelected =
                                                session.id === selected?.id;

                                            return (
                                                <li key={session.id}>
                                                    <button
                                                        type="button"
                                                        aria-current={
                                                            isSelected
                                                                ? 'true'
                                                                : undefined
                                                        }
                                                        onClick={() =>
                                                            guard(() =>
                                                                visit(
                                                                    selectedDate,
                                                                    session.id,
                                                                ),
                                                            )
                                                        }
                                                        className={cn(
                                                            'grid w-full gap-2 rounded-xl border p-4 text-left transition-colors hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                                            isSelected &&
                                                                'border-primary bg-muted/40',
                                                            session.state ===
                                                                'cancelled' &&
                                                                'text-muted-foreground',
                                                        )}
                                                    >
                                                        <span className="flex items-baseline justify-between gap-3">
                                                            <span className="grid gap-0.5">
                                                                <span className="text-sm text-muted-foreground tabular-nums">
                                                                    {
                                                                        session.startsAt
                                                                    }
                                                                    –
                                                                    {
                                                                        session.endsAt
                                                                    }
                                                                </span>
                                                                <span
                                                                    className={cn(
                                                                        'font-semibold',
                                                                        session.state ===
                                                                            'cancelled' &&
                                                                            'line-through',
                                                                    )}
                                                                >
                                                                    {
                                                                        session.classTypeName
                                                                    }
                                                                </span>
                                                            </span>
                                                            <span className="text-sm tabular-nums">
                                                                {
                                                                    session.bookedCount
                                                                }{' '}
                                                                de{' '}
                                                                {
                                                                    session.capacity
                                                                }
                                                            </span>
                                                        </span>
                                                        <StateBadge
                                                            state={
                                                                session.state
                                                            }
                                                        />
                                                    </button>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </section>

                            {selected &&
                                (isMobile ? (
                                    <Sheet
                                        open={sheetOpen}
                                        onOpenChange={(open) =>
                                            open
                                                ? setSheetOpen(true)
                                                : guard(() =>
                                                      setSheetOpen(false),
                                                  )
                                        }
                                    >
                                        <SheetContent
                                            side="bottom"
                                            className="max-h-[90svh] overflow-y-auto"
                                        >
                                            <SheetHeader>
                                                <SheetTitle>
                                                    {detailTitle}
                                                </SheetTitle>
                                                <SheetDescription className="first-letter:uppercase">
                                                    {dayLabel}
                                                </SheetDescription>
                                            </SheetHeader>
                                            <div className="px-4 pb-6">
                                                <SessionDetail
                                                    slug={slug}
                                                    session={selected}
                                                    onDirtyChange={
                                                        onDirtyChange
                                                    }
                                                />
                                            </div>
                                        </SheetContent>
                                    </Sheet>
                                ) : (
                                    <Card className="lg:sticky lg:top-4">
                                        <CardHeader>
                                            <CardTitle>
                                                <h2>{detailTitle}</h2>
                                            </CardTitle>
                                            <CardDescription className="first-letter:uppercase">
                                                {dayLabel}
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <SessionDetail
                                                slug={slug}
                                                session={selected}
                                                onDirtyChange={onDirtyChange}
                                            />
                                        </CardContent>
                                    </Card>
                                ))}
                        </div>
                    </>
                )}
            </div>

            <AlertDialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Tienes cambios sin guardar
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Si sales ahora, la asistencia que marcaste en esta
                            clase no se guarda.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Seguir marcando</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                const go = pending;
                                dirty.current = false;
                                setPending(null);
                                go?.();
                            }}
                        >
                            Salir sin guardar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

MySessions.layout = (props: { studio: CurrentStudio | null }) => ({
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
