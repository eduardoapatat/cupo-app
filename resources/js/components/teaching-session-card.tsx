import { ChevronDown } from 'lucide-react';
import AttendanceList from '@/components/attendance-list';
import type { Attendee } from '@/components/attendance-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

/** A class someone teaches, with who comes and its attendance. */
export type TeachingSession = {
    id: number;
    startsAt: string;
    endsAt: string;
    classTypeName: string;
    bookedCount: number;
    capacity: number;
    isCancelled: boolean;
    hasStarted: boolean;
    attendanceIsOpen: boolean;
    attendanceClosesLabel: string;
    attendees: Attendee[];
};

/**
 * A class in «Mis clases» and in the instructor's Inicio: its time, how
 * full it is and who comes. Once it starts, the list opens to mark who
 * missed it.
 */
export default function TeachingSessionCard({
    slug,
    session,
}: {
    slug: string;
    session: TeachingSession;
}) {
    // A class that started opens with its list, to mark who missed it.
    const marksAttendance = session.hasStarted && !session.isCancelled;

    return (
        <li className="rounded-xl border p-4">
            <Collapsible className="grid gap-3" defaultOpen={marksAttendance}>
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
                                {marksAttendance
                                    ? `Asistencia (${session.attendees.length})`
                                    : `Quiénes vienen (${session.attendees.length})`}
                            </Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="grid gap-1 px-2">
                            {marksAttendance && (
                                <p className="text-sm text-muted-foreground">
                                    {session.attendanceIsOpen
                                        ? `Marca a quien vino, hasta ${session.attendanceClosesLabel}. Quien no quede marcado, cuenta como que faltó.`
                                        : 'La asistencia ya cerró.'}
                                </p>
                            )}
                            <AttendanceList
                                slug={slug}
                                sessionId={session.id}
                                attendees={session.attendees}
                                canMark={session.attendanceIsOpen}
                            />
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
