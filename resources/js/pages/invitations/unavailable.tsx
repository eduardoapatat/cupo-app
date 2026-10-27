import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

type Reason = 'invalid' | 'accepted' | 'expired';

const MESSAGES: Record<Reason, { title: string; description: string }> = {
    invalid: {
        title: 'Este enlace no sirve',
        description:
            'Puede que esté incompleto o que la invitación se haya cancelado. Pídele a la dueña del estudio que te invite de nuevo.',
    },
    accepted: {
        title: 'Esta invitación ya se usó',
        description:
            'Si fuiste tú, inicia sesión para entrar al estudio. Si no, pídele a la dueña que te invite de nuevo.',
    },
    expired: {
        title: 'Esta invitación venció',
        description:
            'Pídele a la dueña del estudio que te la reenvíe. El enlace nuevo te llega al correo.',
    },
};

/**
 * Why an invitation link no longer works (see InvitationController::find()).
 */
export default function InvitationUnavailable({ reason }: { reason: Reason }) {
    return (
        <>
            <Head title={MESSAGES[reason].title} />

            <Button asChild className="w-full">
                <Link href={home()}>Ir a Cupo</Link>
            </Button>
        </>
    );
}

InvitationUnavailable.layout = (props: { reason: Reason }) => ({
    title: MESSAGES[props.reason].title,
    description: MESSAGES[props.reason].description,
});
