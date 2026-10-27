import { Head } from '@inertiajs/react';
import { create, store } from '@/actions/App/Http/Controllers/StudioController';
import Heading from '@/components/heading';
import StudioForm from '@/components/studio-form';
import { dashboard } from '@/routes';
import type { Option } from '@/types';

export default function CreateStudio({ communes }: { communes: Option[] }) {
    return (
        <>
            <Head title="Crear mi estudio" />

            <div className="flex flex-col px-4 py-6">
                <Heading
                    title="Crear mi estudio"
                    description="Quedarás como su dueña. Después podrás invitar a tu equipo y a tus alumnas."
                />

                <div className="max-w-xl">
                    <StudioForm
                        action={store.form()}
                        communes={communes}
                        submitLabel="Crear estudio"
                    />
                </div>
            </div>
        </>
    );
}

CreateStudio.layout = {
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        { title: 'Crear mi estudio', href: create() },
    ],
};
