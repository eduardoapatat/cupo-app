import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';

type ErrorStatus = 403 | 404 | 500 | 503;

const MESSAGES: Record<ErrorStatus, { title: string; description: string }> = {
    403: {
        title: 'No tienes acceso a esta página',
        description:
            'Tu cuenta no tiene permiso para ver esto. Si crees que es un error, pide acceso a la dueña del estudio.',
    },
    404: {
        title: 'No encontramos esta página',
        description:
            'Puede que el enlace esté mal escrito o que lo que buscas ya no exista.',
    },
    500: {
        title: 'Algo salió mal',
        description:
            'Tuvimos un problema al cargar esta página. Inténtalo de nuevo en unos minutos.',
    },
    503: {
        title: 'Estamos en mantención',
        description: 'Cupo vuelve en unos minutos.',
    },
};

/**
 * The page shown for access, missing and server errors (see
 * AppServiceProvider::configureErrorPages()).
 */
export default function ErrorPage({ status }: { status: ErrorStatus }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    const homeHref = user ? dashboard() : home();

    return (
        <>
            <Head title={MESSAGES[status].title} />

            <Button asChild className="w-full">
                <Link href={homeHref}>Volver al inicio</Link>
            </Button>
        </>
    );
}

ErrorPage.layout = (props: { status: ErrorStatus }) => ({
    title: MESSAGES[props.status].title,
    description: MESSAGES[props.status].description,
});
