import { Head } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatClp } from '@/lib/money';

type Props = {
    /** Webpay's payment form. */
    url: string;
    token: string;
    amount: number;
};

/**
 * On the way to Webpay. Transbank asks for a POST with the token, so a
 * plain form submits itself (a full page visit, outside Inertia), with a
 * button in case the browser stops it.
 */
export default function WebpayRedirect({ url, token, amount }: Props) {
    const form = useRef<HTMLFormElement>(null);

    useEffect(() => {
        form.current?.submit();
    }, []);

    return (
        <>
            <Head title="Ir a Webpay" />

            <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 px-4 py-6 text-center">
                <Spinner className="size-6" />
                <div className="grid gap-1">
                    <h1 className="text-lg font-semibold">
                        Te llevamos a Webpay
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Vas a pagar {formatClp(amount)}. Al terminar vuelves
                        aquí.
                    </p>
                </div>
                <form ref={form} method="post" action={url}>
                    <input type="hidden" name="token_ws" value={token} />
                    <Button type="submit" variant="outline">
                        Ir a Webpay
                    </Button>
                </form>
            </div>
        </>
    );
}
