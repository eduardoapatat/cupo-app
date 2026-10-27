import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

/**
 * Tells the user when a visit or a form could not reach the server, instead
 * of failing silently. Their input stays on the page so they can retry.
 */
export function useNetworkErrorToast(): void {
    useEffect(() => {
        return router.on('networkError', (event) => {
            event.preventDefault();
            toast.error(
                'No pudimos conectarnos. Revisa tu conexión e inténtalo de nuevo.',
            );
        });
    }, []);
}
