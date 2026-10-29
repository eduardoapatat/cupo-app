import { router } from '@inertiajs/react';
import { useState } from 'react';
import { update as updateAttendance } from '@/actions/App/Http/Controllers/Studio/AttendanceController';
import InitialsAvatar from '@/components/initials-avatar';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { wentThrough } from '@/lib/form';
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

function AttendanceRow({
    slug,
    sessionId,
    attendee,
    canMark,
}: {
    slug: string;
    sessionId: number;
    attendee: Attendee;
    canMark: boolean;
}) {
    // Shown at once and saved behind; it goes back if the save fails.
    const [attended, setAttended] = useState(attendee.attended);
    const [saving, setSaving] = useState(false);
    const checkboxId = `attended-${attendee.id}`;

    function mark(next: boolean) {
        setAttended(next);
        setSaving(true);
        router.put(
            updateAttendance.url([slug, sessionId, attendee.id]),
            { attended: next },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    if (!wentThrough(page)) {
                        setAttended(!next);
                    }
                },
                onError: () => setAttended(!next),
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <li className="flex items-center justify-between gap-3 py-2">
            <span className="flex min-w-0 items-center gap-3">
                <InitialsAvatar name={attendee.name} />
                <span
                    className={cn(
                        'truncate',
                        attendee.noShow &&
                            !attended &&
                            'text-muted-foreground line-through',
                    )}
                >
                    {attendee.name}
                </span>
                {attendee.isStaff && <Badge variant="outline">Equipo</Badge>}
            </span>
            {canMark ? (
                <span className="flex shrink-0 items-center gap-2">
                    <Checkbox
                        id={checkboxId}
                        checked={attended}
                        disabled={saving}
                        onCheckedChange={(checked) => mark(checked === true)}
                    />
                    <Label htmlFor={checkboxId} className="font-normal">
                        Asistió
                        <span className="sr-only"> {attendee.name}</span>
                    </Label>
                </span>
            ) : attended ? (
                <Badge variant="secondary">Asistió</Badge>
            ) : (
                attendee.noShow && <Badge variant="outline">Faltó</Badge>
            )}
        </li>
    );
}

/**
 * Who came to a class that already started. Nobody starts marked: whoever
 * teaches ticks «Asistió» for each person who came, and whoever is not
 * ticked when the attendance closes missed it.
 */
export default function AttendanceList({
    slug,
    sessionId,
    attendees,
    canMark,
}: {
    slug: string;
    sessionId: number;
    attendees: Attendee[];
    canMark: boolean;
}) {
    return (
        <ul className="divide-y">
            {attendees.map((attendee) => (
                <AttendanceRow
                    key={attendee.id}
                    slug={slug}
                    sessionId={sessionId}
                    attendee={attendee}
                    canMark={canMark}
                />
            ))}
        </ul>
    );
}
