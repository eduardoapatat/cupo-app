import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { StudioRole } from '@/types';

type HomeBlock = {
    title: string;
    description: string;
    /** The phase that fills the block with real data. */
    phase: number;
};

/*
 * What each role sees on arrival (docs/roles-y-permisos.md). The blocks are
 * defined from phase 0 and each phase fills its own with real data.
 */
const BLOCKS: Record<StudioRole, HomeBlock[]> = {
    owner: [
        {
            title: 'Ocupación de hoy',
            description: 'Cuántos cupos se llenaron en cada clase del día.',
            phase: 2,
        },
        {
            title: 'Alertas',
            description:
                'Comprobantes pendientes, cajas con diferencia y anulaciones del día.',
            phase: 3,
        },
        {
            title: 'Ingresos del mes',
            description: 'Lo que entró este mes, por medio de pago.',
            phase: 3,
        },
    ],
    front_desk: [
        {
            title: 'Clases de hoy',
            description: 'Las clases del día, con sus cupos libres.',
            phase: 2,
        },
        {
            title: 'Alumnas que llegan',
            description: 'Quiénes tienen reserva en las próximas clases.',
            phase: 2,
        },
        {
            title: 'Comprobantes por validar',
            description: 'Las transferencias que esperan tu revisión.',
            phase: 3,
        },
        {
            title: 'Tu caja',
            description: 'Si está abierta y cuánto llevas cobrado, por medio.',
            phase: 3,
        },
    ],
    instructor: [
        {
            title: 'Tus clases de hoy',
            description: 'Las clases que dictas hoy y quiénes vienen.',
            phase: 2,
        },
    ],
    student: [
        {
            title: 'Tu próxima clase',
            description: 'Cuándo es y hasta qué hora puedes cancelarla gratis.',
            phase: 2,
        },
        {
            title: 'Clases que te quedan',
            description: 'Las clases disponibles en tu plan.',
            phase: 3,
        },
        {
            title: 'Vencimiento de tu plan',
            description: 'Hasta cuándo puedes usar tus clases.',
            phase: 3,
        },
    ],
};

export default function StudioHome() {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    return (
        <>
            <Head title="Inicio" />

            <div className="flex flex-col gap-4 px-4 py-6">
                <Heading title="Inicio" />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {BLOCKS[studio.role].map((block) => (
                        <Card key={block.title}>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-2">
                                    <CardTitle>
                                        <h2>{block.title}</h2>
                                    </CardTitle>
                                    <Badge variant="outline">Pronto</Badge>
                                </div>
                                <CardDescription>
                                    {block.description} Llega en la Fase{' '}
                                    {block.phase}.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

StudioHome.layout = {
    breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
};
