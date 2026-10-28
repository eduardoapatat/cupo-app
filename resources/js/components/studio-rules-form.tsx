import { Form } from '@inertiajs/react';
import { update as updateRules } from '@/actions/App/Http/Controllers/Studio/StudioRulesController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
    min: number;
    max: number;
};

const FIELDS: RuleField[] = [
    {
        name: 'cancellation_window_hours',
        label: 'Cancelación gratis (horas antes)',
        help: 'Hasta cuántas horas antes de la clase se puede cancelar sin perderla.',
        min: 0,
        max: 72,
    },
    {
        name: 'booking_cutoff_minutes',
        label: 'Cierre de reservas (minutos antes)',
        help: 'Cuánto antes de empezar se cierran las reservas, para saber con tiempo quién viene.',
        min: 0,
        max: 1440,
    },
    {
        name: 'waitlist_cutoff_hours',
        label: 'Lista de espera (horas antes)',
        help: 'Hasta cuántas horas antes un cupo liberado se le da sola a la primera de la lista. Después queda libre para cualquiera.',
        min: 0,
        max: 72,
    },
    {
        name: 'booking_horizon_days',
        label: 'Anticipación (días)',
        help: 'Con cuántos días de anticipación se puede reservar.',
        min: 1,
        max: 60,
    },
];

/**
 * The studio's rules, for its owner. They apply from the moment they are
 * saved; bookings already made keep their state.
 */
export default function StudioRulesForm({
    slug,
    rules,
}: {
    slug: string;
    rules: StudioRules;
}) {
    return (
        <Form
            {...updateRules.form(slug)}
            options={{ preserveScroll: true }}
            onError={(errors) => focusFirstError(errors)}
            className="grid gap-6"
        >
            {({ processing, errors }) => (
                <>
                    {FIELDS.map((field) => (
                        <div key={field.name} className="grid gap-2">
                            <Label htmlFor={field.name}>{field.label}</Label>
                            <Input
                                id={field.name}
                                {...fieldErrorProps(
                                    field.name,
                                    errors[field.name],
                                    `${field.name}-help`,
                                )}
                                name={field.name}
                                type="number"
                                inputMode="numeric"
                                min={field.min}
                                max={field.max}
                                defaultValue={rules[field.name]}
                                required
                                className="max-w-32 tabular-nums"
                            />
                            <p
                                id={`${field.name}-help`}
                                className="text-sm text-muted-foreground"
                            >
                                {field.help}
                            </p>
                            <InputError
                                id={`${field.name}-error`}
                                message={errors[field.name]}
                            />
                        </div>
                    ))}

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar reglas
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
