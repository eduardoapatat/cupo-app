import { Form, Head, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { CalendarClock, Landmark, Store } from 'lucide-react';
import { useState } from 'react';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/Studio/SettingsController';
import { update as updateTransferDetails } from '@/actions/App/Http/Controllers/Studio/TransferDetailsController';
import InitialsAvatar from '@/components/initials-avatar';
import InputError from '@/components/input-error';
import { StudioFields } from '@/components/studio-form';
import StudioRulesForm from '@/components/studio-rules-form';
import type { StudioRules } from '@/components/studio-rules-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { dashboard } from '@/routes';
import { home } from '@/routes/studios';
import type { Option } from '@/types';

type Props = {
    details: { slug: string; name: string; commune: string };
    communes: Option[];
    rules: StudioRules;
    /** Where students transfer to; null while she has not written them. */
    transferDetails: string | null;
};

type TabValue = 'general' | 'reservas' | 'pagos';

/* The studio's sections, in the order she sets it up. */
const TABS: { value: TabValue; label: string; icon: LucideIcon }[] = [
    { value: 'general', label: 'General', icon: Store },
    { value: 'reservas', label: 'Reservas', icon: CalendarClock },
    { value: 'pagos', label: 'Pagos', icon: Landmark },
];

/** With them, students can buy a plan from the app by transfer. */
function TransferDetailsForm({
    slug,
    transferDetails,
}: {
    slug: string;
    transferDetails: string | null;
}) {
    return (
        <Form
            {...updateTransferDetails.form(slug)}
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
            onError={(errors) =>
                focusFirstError(errors, {
                    transfer_details: 'transfer-details',
                })
            }
        >
            {({ processing, errors, isDirty }) => (
                <Card className="gap-0 overflow-hidden py-0">
                    <CardHeader className="py-6">
                        <CardTitle>
                            <h2>Datos para transferir</h2>
                        </CardTitle>
                        <CardDescription>
                            Las alumnas los ven al comprar un plan desde la app.
                            Sin ellos, desde la app solo pagan con Webpay.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2 pb-6">
                        <Label htmlFor="transfer-details">
                            Banco, cuenta y correo
                        </Label>
                        <Textarea
                            id="transfer-details"
                            {...fieldErrorProps(
                                'transfer-details',
                                errors.transfer_details,
                            )}
                            name="transfer_details"
                            defaultValue={transferDetails ?? ''}
                            maxLength={500}
                            rows={4}
                            placeholder={
                                'Banco\nCuenta corriente 000123456\npagos@tuestudio.cl'
                            }
                        />
                        <InputError
                            id="transfer-details-error"
                            message={errors.transfer_details}
                        />
                    </CardContent>
                    <CardFooter className="border-t bg-muted/40 py-4">
                        <Button
                            type="submit"
                            disabled={processing || !isDirty}
                            className="ml-auto"
                        >
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </CardFooter>
                </Card>
            )}
        </Form>
    );
}

export default function StudioSettings({
    details,
    communes,
    rules,
    transferDetails,
}: Props) {
    const communeLabel = communes.find(
        (commune) => commune.value === details.commune,
    )?.label;
    const { url } = usePage();
    const [tab, setTab] = useState<TabValue>(() => {
        // From Inertia's URL, which the server render has too.
        const asked = new URLSearchParams(url.split('?')[1] ?? '').get(
            'pestana',
        );

        return TABS.some((item) => item.value === asked)
            ? (asked as TabValue)
            : 'general';
    });

    // The tab goes in the address, so a reload or a shared link opens it.
    function chooseTab(value: string) {
        setTab(value as TabValue);
        const url = new URL(window.location.href);

        if (value === 'general') {
            url.searchParams.delete('pestana');
        } else {
            url.searchParams.set('pestana', value);
        }

        window.history.replaceState(window.history.state, '', url);
    }

    return (
        <>
            <Head title="Estudio" />

            <div className="flex flex-col gap-8 px-4 py-6">
                <header className="flex items-center gap-4">
                    <InitialsAvatar
                        name={details.name}
                        className="size-14 text-lg"
                    />
                    <div className="grid min-w-0 gap-0.5">
                        <h1 className="text-xl font-semibold tracking-tight break-words">
                            {details.name}
                        </h1>
                        <p className="flex flex-wrap items-baseline gap-x-1.5 text-sm text-muted-foreground">
                            <span>{communeLabel}</span>
                            <span aria-hidden="true">·</span>
                            <span className="font-mono text-xs break-all">
                                {home.url(details.slug)}
                            </span>
                        </p>
                    </div>
                </header>

                <Tabs
                    value={tab}
                    onValueChange={chooseTab}
                    className="max-w-5xl gap-6"
                >
                    {/* A line under the tabs, the active one underlined in teal. */}
                    <div className="border-b">
                        <TabsList
                            variant="line"
                            aria-label="Secciones del estudio"
                            className="h-11 gap-4 p-0"
                        >
                            {TABS.map(({ value, label, icon: Icon }) => (
                                <TabsTrigger
                                    key={value}
                                    value={value}
                                    className="flex-none px-1 after:bg-primary group-data-[orientation=horizontal]/tabs:after:-bottom-0.5 data-[state=active]:text-foreground"
                                >
                                    <Icon aria-hidden="true" />
                                    {label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </div>

                    {/* Mounted while hidden, so a tab keeps what she typed. */}
                    <TabsContent
                        value="general"
                        forceMount
                        className="w-full data-[state=inactive]:hidden"
                    >
                        <Form
                            {...update.form(details.slug)}
                            options={{ preserveScroll: true }}
                            setDefaultsOnSuccess
                            onError={(errors) => focusFirstError(errors)}
                        >
                            {({ processing, errors, isDirty }) => (
                                <Card className="gap-0 overflow-hidden py-0">
                                    <CardHeader className="py-6">
                                        <CardTitle>
                                            <h2>Datos del estudio</h2>
                                        </CardTitle>
                                        <CardDescription>
                                            Cómo lo ven tu equipo y tus alumnas.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-6 pb-6">
                                        <StudioFields
                                            communes={communes}
                                            studio={details}
                                            errors={errors}
                                        />
                                    </CardContent>
                                    <CardFooter className="flex-col items-stretch gap-3 border-t bg-muted/40 py-4 sm:flex-row sm:items-center sm:justify-between">
                                        <p className="text-sm text-muted-foreground">
                                            Cambiar el nombre no cambia su
                                            dirección en Cupo.
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={processing || !isDirty}
                                            className="self-end sm:self-auto"
                                        >
                                            {processing && <Spinner />}
                                            Guardar
                                        </Button>
                                    </CardFooter>
                                </Card>
                            )}
                        </Form>
                    </TabsContent>

                    <TabsContent
                        value="reservas"
                        forceMount
                        className="w-full data-[state=inactive]:hidden"
                    >
                        <StudioRulesForm slug={details.slug} rules={rules} />
                    </TabsContent>

                    <TabsContent
                        value="pagos"
                        forceMount
                        className="w-full data-[state=inactive]:hidden"
                    >
                        <TransferDetailsForm
                            slug={details.slug}
                            transferDetails={transferDetails}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

StudioSettings.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        { title: 'Estudio', href: edit(props.details.slug) },
    ],
});
