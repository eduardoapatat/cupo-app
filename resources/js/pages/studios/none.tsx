import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

/**
 * The home of someone who does not belong to a studio yet. Creating a
 * studio arrives in phase 1, so its button shows as «Pronto».
 */
export default function NoStudio() {
    return (
        <>
            <Head title="Inicio" />

            <div className="flex flex-col gap-4 px-4 py-6">
                <Heading title="Inicio" />

                <Card className="max-w-xl">
                    <CardHeader>
                        <CardTitle>
                            <h2>Todavía no perteneces a un estudio</h2>
                        </CardTitle>
                        <CardDescription>
                            Si tomas clases o trabajas en un estudio, usa el
                            enlace de inscripción que te compartieron o pídele a
                            la dueña que te invite. Si tienes un estudio, créalo
                            para empezar.
                        </CardDescription>
                    </CardHeader>
                    <CardFooter className="gap-2">
                        <Button disabled>Crear mi estudio</Button>
                        <Badge variant="outline">Pronto</Badge>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}

NoStudio.layout = {
    breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
};
