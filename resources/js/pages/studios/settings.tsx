import { Form, Head } from '@inertiajs/react';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/Studio/SettingsController';
import { update as updateTransferDetails } from '@/actions/App/Http/Controllers/Studio/TransferDetailsController';
import InitialsAvatar from '@/components/initials-avatar';
import InputError from '@/components/input-error';
import { StudioFields } from '@/components/studio-form';
import StudioRulesForm from '@/components/studio-rules-form';
import type { StudioRules } from '@/components/studio-rules-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { dashboard } from '@/routes';
import { home } from '@/routes/studios';
import type { Option } from '@/types';

type Props = {
    details: { slug: string; name: string; commune: string };
    communes: Option[];
    rules: StudioRules;
    /** Where students transfer to; null while she has not written them. */
    transferDetails: string | null;
};

/** With them, students can buy a plan from the app by transfer. */
function TransferDetailsForm({
    slug,
    transferDetails,
}: {
    slug: string;
    transferDetails: string | null;
}) {
    return (
        <Form
            {...updateTransferDetails.form(slug)}
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
            onError={(errors) =>
                focusFirstError(errors, {
                    transfer_details: 'transfer-details',
                })
            }
        >
            {({ processing, errors, isDirty }) => (
                <Card className="gap-0 overflow-hidden py-0">
                    <CardHeader className="py-6">
                        <CardTitle>
                            <h2>Datos para transferir</h2>
                        </CardTitle>
                        <CardDescription>
                            Las alumnas los ven al comprar un plan desde la app.
                            Sin ellos, solo compran en el mesón.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2 pb-6">
                        <Label htmlFor="transfer-details">
                            Banco, cuenta y correo
                        </Label>
                        <Textarea
                            id="transfer-details"
                            {...fieldErrorProps(
                                'transfer-details',
                                errors.transfer_details,
                            )}
                            name="transfer_details"
                            defaultValue={transferDetails ?? ''}
                            maxLength={500}
                            rows={4}
                            placeholder={
                                'Banco\nCuenta corriente 000123456\npagos@tuestudio.cl'
                            }
                        />
                        <InputError
                            id="transfer-details-error"
                            message={errors.transfer_details}
                        />
                    </CardContent>
                    <CardFooter className="border-t bg-muted/40 py-4">
                        <Button
                            type="submit"
                            disabled={processing || !isDirty}
                            className="ml-auto"
                        >
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </CardFooter>
                </Card>
            )}
        </Form>
    );
}

export default function StudioSettings({
    details,
    communes,
    rules,
    transferDetails,
}: Props) {
    const communeLabel = communes.find(
        (commune) => commune.value === details.commune,
    )?.label;

    return (
        <>
            <Head title="Estudio" />

            <div className="flex flex-col gap-8 px-4 py-6">
                <header className="flex items-center gap-4">
                    <InitialsAvatar
                        name={details.name}
                        className="size-14 text-lg"
                    />
                    <div className="grid min-w-0 gap-0.5">
                        <h1 className="text-xl font-semibold tracking-tight break-words">
                            {details.name}
                        </h1>
                        <p className="flex flex-wrap items-baseline gap-x-1.5 text-sm text-muted-foreground">
                            <span>{communeLabel}</span>
                            <span aria-hidden="true">·</span>
                            <span className="font-mono text-xs break-all">
                                {home.url(details.slug)}
                            </span>
                        </p>
                    </div>
                </header>

                {/* The short cards stay in view while the rules scroll. */}
                <div className="grid max-w-6xl items-start gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
                    <div className="grid gap-6 xl:sticky xl:top-6">
                        <Form
                            {...update.form(details.slug)}
                            options={{ preserveScroll: true }}
                            setDefaultsOnSuccess
                            onError={(errors) => focusFirstError(errors)}
                        >
                            {({ processing, errors, isDirty }) => (
                                <Card className="gap-0 overflow-hidden py-0">
                                    <CardHeader className="py-6">
                                        <CardTitle>
                                            <h2>Datos del estudio</h2>
                                        </CardTitle>
                                        <CardDescription>
                                            Cómo lo ven tu equipo y tus alumnas.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-6 pb-6">
                                        <StudioFields
                                            communes={communes}
                                            studio={details}
                                            errors={errors}
                                        />
                                    </CardContent>
                                    <CardFooter className="flex-col items-stretch gap-3 border-t bg-muted/40 py-4">
                                        <p className="text-sm text-muted-foreground">
                                            Cambiar el nombre no cambia su
                                            dirección en Cupo.
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={processing || !isDirty}
                                            className="self-end"
                                        >
                                            {processing && <Spinner />}
                                            Guardar
                                        </Button>
                                    </CardFooter>
                                </Card>
                            )}
                        </Form>
                        <TransferDetailsForm
                            slug={details.slug}
                            transferDetails={transferDetails}
                        />
                    </div>

                    <StudioRulesForm slug={details.slug} rules={rules} />
                </div>
            </div>
        </>
    );
}

StudioSettings.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        { title: 'Estudio', href: edit(props.details.slug) },
    ],
});
