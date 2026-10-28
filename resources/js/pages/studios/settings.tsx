import { Head } from '@inertiajs/react';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/Studio/SettingsController';
import { update as renewEnrollmentLink } from '@/actions/App/Http/Controllers/Studio/EnrollmentLinkController';
import ConfirmDialog from '@/components/confirm-dialog';
import EnrollmentLinkField from '@/components/enrollment-link-field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import StudioForm from '@/components/studio-form';
import StudioRulesForm from '@/components/studio-rules-form';
import type { StudioRules } from '@/components/studio-rules-form';
import { dashboard } from '@/routes';
import type { Option } from '@/types';

type Props = {
    details: { slug: string; name: string; commune: string };
    communes: Option[];
    enrollmentUrl: string;
    rules: StudioRules;
};

export default function StudioSettings({
    details,
    communes,
    enrollmentUrl,
    rules,
}: Props) {
    return (
        <>
            <Head title="Estudio" />

            <div className="flex flex-col gap-12 px-4 py-6">
                <section className="max-w-xl">
                    <Heading
                        title="Estudio"
                        description="Los datos de tu estudio. Cambiar el nombre no cambia su dirección en Cupo."
                    />
                    <StudioForm
                        action={update.form(details.slug)}
                        communes={communes}
                        studio={details}
                        submitLabel="Guardar"
                    />
                </section>

                <section className="grid max-w-xl gap-6">
                    <Heading
                        variant="small"
                        title="Enlace de inscripción"
                        description="Compártelo con tus alumnas, por ejemplo por Instagram o WhatsApp. Quien entra por él y crea su cuenta queda como alumna del estudio."
                    />
                    <EnrollmentLinkField url={enrollmentUrl} />
                    <div>
                        <ConfirmDialog
                            trigger={
                                <Button variant="outline">
                                    Generar un enlace nuevo
                                </Button>
                            }
                            title="¿Generar un enlace nuevo?"
                            description="El enlace actual dejará de servir de inmediato. Quienes ya son alumnas no se ven afectadas."
                            action={renewEnrollmentLink.form(details.slug)}
                            confirmLabel="Generar enlace nuevo"
                        />
                    </div>
                </section>

                <section className="grid max-w-xl gap-6">
                    <Heading
                        variant="small"
                        title="Reglas"
                        description="Rigen desde que las guardas. Las reservas ya hechas no cambian."
                    />
                    <StudioRulesForm slug={details.slug} rules={rules} />
                </section>
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
