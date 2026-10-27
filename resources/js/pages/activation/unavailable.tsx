import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { home, login } from '@/routes';

type Reason = 'invalid' | 'active' | 'expired';

const MESSAGES: Record<Reason, { title: string; description: string }> = {
    invalid: {
        title: 'Este enlace no sirve',
        description:
            'Puede que esté incompleto o que tu estudio haya corregido tu correo. Pídele a tu estudio que te mande un enlace nuevo.',
    },
    active: {
        title: 'Tu cuenta ya está activa',
        description: 'Ya creaste tu contraseña. Inicia sesión con tu correo.',
    },
    expired: {
        title: 'Este enlace venció',
        description:
            'Pídele a tu estudio que te mande uno nuevo. Te llega al mismo correo.',
    },
};

/**
 * Why an activation link no longer works (see
 * AccountActivationController::find()).
 */
export default function ActivationUnavailable({ reason }: { reason: Reason }) {
    return (
        <>
            <Head title={MESSAGES[reason].title} />

            <Button asChild className="w-full">
                {reason === 'active' ? (
                    <Link href={login()}>Iniciar sesión</Link>
                ) : (
                    <Link href={home()}>Ir a Cupo</Link>
                )}
            </Button>
        </>
    );
}

ActivationUnavailable.layout = (props: { reason: Reason }) => ({
    title: MESSAGES[props.reason].title,
    description: MESSAGES[props.reason].description,
});
