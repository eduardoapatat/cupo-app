import { Form } from '@inertiajs/react';
import { CalendarRange, CalendarX2, ListOrdered, Timer } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { update as updateRules } from '@/actions/App/Http/Controllers/Studio/StudioRulesController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fieldErrorProps, focusFirstError } from '@/lib/form';

export type StudioRules = {
    cancellation_window_hours: number;
    booking_cutoff_minutes: number;
    waitlist_cutoff_hours: number;
    booking_horizon_days: number;
};

type RuleField = {
    name: keyof StudioRules;
    label: string;
    help: string;
    unit: string;
    icon: LucideIcon;
    min: number;
    max: number;
    /** What the value means for a sample class, in plain words. */
    example: (value: number) => string;
};

/** The sample class the examples talk about, in minutes after midnight. */
const SAMPLE_START = 19 * 60;

/** The studios' time zone (config('studio.timezone')), for today's date. */
const STUDIO_TIME_ZONE = 'America/Santiago';

const FIELDS: RuleField[] = [
    {
        name: 'cancellation_window_hours',
        label: 'Cancelación gratis',
        help: 'Hasta cuántas horas antes de la clase se puede cancelar sin perderla.',
        unit: 'horas',
        icon: CalendarX2,
        min: 0,
        max: 72,
        example: (hours) =>
            hours === 0
                ? 'Una clase de las 19:00 se cancela gratis hasta que empieza.'
                : `Una clase de las 19:00 se cancela gratis hasta las ${timeBefore(hours * 60)}.`,
    },
    {
        name: 'booking_cutoff_minutes',
        label: 'Cierre de reservas',
        help: 'Cuánto antes de empezar se cierran las reservas, para saber con tiempo quién viene.',
        unit: 'min',
        icon: Timer,
        min: 0,
        max: 1440,
        example: (minutes) =>
            minutes === 0
                ? 'Una clase de las 19:00 se reserva hasta que empieza.'
                : `Una clase de las 19:00 se reserva hasta las ${timeBefore(minutes)}.`,
    },
    {
        name: 'waitlist_cutoff_hours',
        label: 'Lista de espera',
        help: 'Hasta cuántas horas antes un cupo liberado se le da sola a la primera de la lista.',
        unit: 'horas',
        icon: ListOrdered,
        min: 0,
        max: 72,
        example: (hours) =>
            hours === 0
                ? 'Si se libera un cupo antes de que empiece la clase, pasa sola a la primera de la lista.'
                : `Si se libera un cupo de la clase de las 19:00 antes de las ${timeBefore(hours * 60)}, pasa sola a la primera de la lista. Después, queda libre para cualquiera.`,
    },
    {
        name: 'booking_horizon_days',
        label: 'Anticipación',
        help: 'Con cuántos días de anticipación se puede reservar.',
        unit: 'días',
        icon: CalendarRange,
        min: 1,
        max: 60,
        example: (days) =>
            days === 1
                ? 'Solo se puede reservar para hoy.'
                : `Hoy, ${dayFromToday(0)}, se puede reservar hasta el ${dayFromToday(days - 1)}.`,
    },
];

/**
 * The clock time some minutes before the sample class, with the day when
 * it falls before it: «13:00», «23:00 del día anterior».
 */
function timeBefore(minutes: number): string {
    const total = SAMPLE_START - minutes;
    const daysBefore = -Math.floor(total / 1440);
    const minuteOfDay = ((total % 1440) + 1440) % 1440;
    const time = `${String(Math.floor(minuteOfDay / 60)).padStart(2, '0')}:${String(minuteOfDay % 60).padStart(2, '0')}`;

    if (daysBefore === 0) {
        return time;
    }

    return daysBefore === 1
        ? `${time} del día anterior`
        : `${time} de ${daysBefore} días antes`;
}

/**
 * A date counted from today in the studio's time zone, as «28 de octubre».
 * Sessions are generated from today up to the horizon minus one day.
 */
function dayFromToday(days: number): string {
    const [year, month, day] = new Intl.DateTimeFormat('en-CA', {
        timeZone: STUDIO_TIME_ZONE,
    })
        .format(new Date())
        .split('-')
        .map(Number);

    return new Intl.DateTimeFormat('es-CL', {
        timeZone: 'UTC',
        day: 'numeric',
        month: 'long',
    }).format(new Date(Date.UTC(year, month - 1, day + days)));
}

function exampleFor(field: RuleField, input: string): string | null {
    const value = Number(input);

    if (input.trim() === '' || !Number.isInteger(value)) {
        return null;
    }

    if (value < field.min || value > field.max) {
        return null;
    }

    return field.example(value);
}

/**
 * The studio's rules, for its owner, one per row with an example of what
 * the number means. They apply from the moment they are saved; bookings
 * already made keep their state.
 */
export default function StudioRulesForm({
    slug,
    rules,
}: {
    slug: string;
    rules: StudioRules;
}) {
    const [values, setValues] = useState<Record<keyof StudioRules, string>>(
        () => ({
            cancellation_window_hours: String(rules.cancellation_window_hours),
            booking_cutoff_minutes: String(rules.booking_cutoff_minutes),
            waitlist_cutoff_hours: String(rules.waitlist_cutoff_hours),
            booking_horizon_days: String(rules.booking_horizon_days),
        }),
    );

    return (
        <Form
            {...updateRules.form(slug)}
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
            onError={(errors) => focusFirstError(errors)}
        >
            {({ processing, errors, isDirty }) => (
                <Card className="gap-0 overflow-hidden py-0">
                    <CardHeader className="py-6">
                        <CardTitle>
                            <h2>Reglas de reserva</h2>
                        </CardTitle>
                        <CardDescription>
                            Cuándo se puede reservar, cancelar y tomar un cupo
                            de la lista de espera. Los ejemplos usan una clase
                            de las 19:00.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="divide-y border-t px-0">
                        {FIELDS.map((field) => {
                            const Icon = field.icon;
                            const example = exampleFor(
                                field,
                                values[field.name],
                            );

                            return (
                                <div
                                    key={field.name}
                                    className="grid gap-3 px-6 py-5 sm:grid-cols-[1fr_auto] sm:gap-x-8"
                                >
                                    <div className="flex gap-3">
                                        <Icon
                                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <div className="grid gap-1">
                                            <Label htmlFor={field.name}>
                                                {field.label}
                                            </Label>
                                            <p
                                                id={`${field.name}-help`}
                                                className="text-sm text-muted-foreground"
                                            >
                                                {field.help}
                                            </p>
                                            {example && (
                                                <p
                                                    id={`${field.name}-example`}
                                                    className="text-sm"
                                                >
                                                    {example}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 pl-7 sm:self-start sm:pl-0">
                                        <Input
                                            id={field.name}
                                            {...fieldErrorProps(
                                                field.name,
                                                errors[field.name],
                                                `${field.name}-unit`,
                                                `${field.name}-help`,
                                                ...(example
                                                    ? [`${field.name}-example`]
                                                    : []),
                                            )}
                                            name={field.name}
                                            type="number"
                                            inputMode="numeric"
                                            min={field.min}
                                            max={field.max}
                                            defaultValue={rules[field.name]}
                                            onChange={(event) => {
                                                const value =
                                                    event.currentTarget.value;
                                                setValues((current) => ({
                                                    ...current,
                                                    [field.name]: value,
                                                }));
                                            }}
                                            required
                                            className="w-20 tabular-nums"
                                        />
                                        <span
                                            id={`${field.name}-unit`}
                                            className="w-10 text-sm text-muted-foreground"
                                        >
                                            {field.unit}
                                        </span>
                                    </div>

                                    <InputError
                                        id={`${field.name}-error`}
                                        message={errors[field.name]}
                                        className="pl-7 sm:col-span-2"
                                    />
                                </div>
                            );
                        })}
                    </CardContent>

                    <CardFooter className="flex-col items-start gap-3 border-t bg-muted/40 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            Rigen desde que las guardas. Las reservas ya hechas
                            no cambian.
                        </p>
                        <Button type="submit" disabled={processing || !isDirty}>
                            {processing && <Spinner />}
                            Guardar reglas
                        </Button>
                    </CardFooter>
                </Card>
            )}
        </Form>
    );
}
