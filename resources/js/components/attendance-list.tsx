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
    const [noShow, setNoShow] = useState(attendee.noShow);
    const [saving, setSaving] = useState(false);
    const checkboxId = `attended-${attendee.id}`;

    function mark(next: boolean) {
        setNoShow(next);
        setSaving(true);
        router.put(
            updateAttendance.url([slug, sessionId, attendee.id]),
            { no_show: next },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    if (!wentThrough(page)) {
                        setNoShow(!next);
                    }
                },
                onError: () => setNoShow(!next),
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
                        noShow && 'text-muted-foreground line-through',
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
                        checked={!noShow}
                        disabled={saving}
                        onCheckedChange={(checked) => mark(checked !== true)}
                    />
                    <Label htmlFor={checkboxId} className="font-normal">
                        Asistió
                        <span className="sr-only"> {attendee.name}</span>
                    </Label>
                </span>
            ) : (
                noShow && <Badge variant="secondary">Faltó</Badge>
            )}
        </li>
    );
}

/**
 * Who came to a class that already started. Everyone starts ticked as
 * «Asistió»; unticking someone marks that she missed it.
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
