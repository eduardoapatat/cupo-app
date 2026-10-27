import { Head } from '@inertiajs/react';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/Studio/SettingsController';
import Heading from '@/components/heading';
import StudioForm from '@/components/studio-form';
import { dashboard } from '@/routes';
import type { Option } from '@/types';

type Props = {
    details: { slug: string; name: string; commune: string };
    communes: Option[];
};

export default function StudioSettings({ details, communes }: Props) {
    return (
        <>
            <Head title="Estudio" />

            <div className="flex flex-col px-4 py-6">
                <Heading
                    title="Estudio"
                    description="Los datos de tu estudio. Cambiar el nombre no cambia su dirección en Cupo."
                />

                <div className="max-w-xl">
                    <StudioForm
                        action={update.form(details.slug)}
                        communes={communes}
                        studio={details}
                        submitLabel="Guardar"
                    />
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
