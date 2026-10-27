import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { dashboard } from '@/routes';

export default function StudioHome() {
    return (
        <>
            <Head title="Inicio" />

            <div className="flex flex-col gap-4 px-4 py-6">
                <Heading title="Inicio" />
            </div>
        </>
    );
}

StudioHome.layout = {
    breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
};
