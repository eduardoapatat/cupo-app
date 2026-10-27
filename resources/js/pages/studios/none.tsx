import { Head, Link } from '@inertiajs/react';
import { create as createStudio } from '@/actions/App/Http/Controllers/StudioController';
import Heading from '@/components/heading';
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
 * The home of someone who does not belong to a studio yet: they join one
 * through its link or an invitation, or create their own.
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
                    <CardFooter>
                        <Button asChild>
                            <Link href={createStudio()}>Crear mi estudio</Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}

NoStudio.layout = {
    breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
};
