import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

/**
 * An enrollment link that no longer exists, usually because the studio
 * renewed it (see EnrollmentController::show()).
 */
export default function EnrollmentUnavailable() {
    return (
        <>
            <Head title="Este enlace ya no sirve" />

            <Button asChild className="w-full">
                <Link href={home()}>Ir a Cupo</Link>
            </Button>
        </>
    );
}

EnrollmentUnavailable.layout = {
    title: 'Este enlace ya no sirve',
    description:
        'Puede que el estudio haya generado uno nuevo. Pídele el enlace actualizado a tu estudio.',
};
