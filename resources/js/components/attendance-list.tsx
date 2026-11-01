import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { update as updateAttendance } from '@/actions/App/Http/Controllers/Studio/AttendanceController';
import InitialsAvatar from '@/components/initials-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

/** Someone with a place in a class, as the attendance shows them. */
export type Attendee = {
    id: number;
    name: string;
    isStaff: boolean;
    /** Marked as present by whoever teaches. */
    attended: boolean;
    /** Not marked when the attendance closed (or unmarked afterwards). */
    noShow: boolean;
};

function attendedIds(attendees: Attendee[]): number[] {
    return attendees
        .filter((attendee) => attendee.attended)
        .map((attendee) => attendee.id);
}

/**
 * Who came to a class. Nobody starts marked: whoever teaches ticks who
 * came and saves the whole list at once; whoever is not ticked when the
 * attendance closes missed it. Read only when it cannot be marked.
 */
export default function AttendanceList({
    slug,
    sessionId,
    attendees,
    canMark,
    footnote,
    needsSaving = false,
    onDirtyChange,
}: {
    slug: string;
    sessionId: number;
    attendees: Attendee[];
    canMark: boolean;
    /** Shown next to «Guardar»: until when it can be changed. */
    footnote?: ReactNode;
    /** Its list was never saved, so «Guardar» works even without changes. */
    needsSaving?: boolean;
    /** Tells the page whether there are changes not saved yet. */
    onDirtyChange?: (dirty: boolean) => void;
}) {
    const saved = useMemo(() => attendedIds(attendees), [attendees]);
    const [checked, setChecked] = useState<number[]>(saved);
    const [saving, setSaving] = useState(false);
    const dirty =
        checked.length !== saved.length ||
        checked.some((id) => !saved.includes(id));

    // What the server saved becomes the new starting point.
    useEffect(() => setChecked(saved), [saved]);
    useEffect(() => onDirtyChange?.(dirty), [dirty, onDirtyChange]);

    function toggle(id: number, next: boolean) {
        setChecked((current) =>
            next ? [...current, id] : current.filter((item) => item !== id),
        );
    }

    function save() {
        setSaving(true);
        router.put(
            updateAttendance.url([slug, sessionId]),
            { attended: checked },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSaving(false),
            },
        );
    }

    const allChecked = checked.length === attendees.length;

    return (
        <div className="grid gap-3">
            {canMark && (
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm tabular-nums" aria-live="polite">
                        Vinieron{' '}
                        <span className="font-semibold">{checked.length}</span>{' '}
                        de {attendees.length}
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            setChecked(
                                allChecked
                                    ? []
                                    : attendees.map((attendee) => attendee.id),
                            )
                        }
                    >
                        {allChecked ? 'Desmarcar todas' : 'Marcar todas'}
                    </Button>
                </div>
            )}

            <ul className="divide-y">
                {attendees.map((attendee) => {
                    const isChecked = checked.includes(attendee.id);
                    const checkboxId = `attended-${sessionId}-${attendee.id}`;

                    return (
                        <li
                            key={attendee.id}
                            className="flex items-center justify-between gap-3 py-2"
                        >
                            <span className="flex min-w-0 items-center gap-3">
                                <InitialsAvatar name={attendee.name} />
                                <span
                                    className={cn(
                                        'truncate',
                                        attendee.noShow &&
                                            !isChecked &&
                                            'text-muted-foreground line-through',
                                    )}
                                >
                                    {attendee.name}
                                </span>
                                {attendee.isStaff && (
                                    <Badge variant="outline">Equipo</Badge>
                                )}
                            </span>
                            {canMark ? (
                                <span className="flex shrink-0 items-center gap-2">
                                    <Checkbox
                                        id={checkboxId}
                                        checked={isChecked}
                                        disabled={saving}
                                        onCheckedChange={(next) =>
                                            toggle(attendee.id, next === true)
                                        }
                                    />
                                    <Label
                                        htmlFor={checkboxId}
                                        className="font-normal"
                                    >
                                        Asistió
                                        <span className="sr-only">
                                            {' '}
                                            {attendee.name}
                                        </span>
                                    </Label>
                                </span>
                            ) : attendee.attended ? (
                                <Badge variant="secondary">Asistió</Badge>
                            ) : (
                                attendee.noShow && (
                                    <Badge variant="outline">Faltó</Badge>
                                )
                            )}
                        </li>
                    );
                })}
            </ul>

            {canMark && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                    <p className="max-w-sm text-sm text-muted-foreground">
                        {footnote}
                    </p>
                    <Button
                        type="button"
                        onClick={save}
                        disabled={saving || (!dirty && !needsSaving)}
                    >
                        {saving && <Spinner />}
                        Guardar asistencia
                    </Button>
                </div>
            )}
        </div>
    );
}
