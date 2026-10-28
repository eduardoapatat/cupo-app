import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import type { Option } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type StudioFieldsProps = {
    communes: Option[];
    studio?: { name: string; commune: string };
    errors: Record<string, string | undefined>;
};

/**
 * The studio's name and commune, for a form that brings its own submit
 * button.
 */
export function StudioFields({ communes, studio, errors }: StudioFieldsProps) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre del estudio</Label>
                <Input
                    id="name"
                    {...fieldErrorProps('name', errors.name)}
                    name="name"
                    defaultValue={studio?.name}
                    required
                    maxLength={100}
                    autoComplete="organization"
                    placeholder="Estudio Flow"
                />
                <InputError id="name-error" message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="commune">Comuna</Label>
                <Select name="commune" defaultValue={studio?.commune} required>
                    <SelectTrigger
                        id="commune"
                        {...fieldErrorProps('commune', errors.commune)}
                        className="w-full"
                    >
                        <SelectValue placeholder="Elige una comuna" />
                    </SelectTrigger>
                    <SelectContent>
                        {communes.map((commune) => (
                            <SelectItem
                                key={commune.value}
                                value={commune.value}
                            >
                                {commune.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError id="commune-error" message={errors.commune} />
            </div>
        </>
    );
}

type StudioFormProps = {
    action: RouteFormDefinition<'post'>;
    communes: Option[];
    studio?: { name: string; commune: string };
    submitLabel: string;
};

/**
 * The studio's data with its submit button, for creating a studio.
 */
export default function StudioForm({
    action,
    communes,
    studio,
    submitLabel,
}: StudioFormProps) {
    return (
        <Form
            {...action}
            onError={(errors) => focusFirstError(errors)}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <StudioFields
                        communes={communes}
                        studio={studio}
                        errors={errors}
                    />

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
