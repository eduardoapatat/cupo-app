import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { login } from '@/routes';

type Props = {
    studio: { name: string; commune: string };
    /** A guest, a signed-in person who is not a member yet, or a member. */
    state: 'register' | 'join' | 'member';
    needsAdultDeclaration: boolean;
    studioUrl: string;
    joinUrl: string;
    passwordRules: string;
};

function AdultDeclaration({ error }: { error?: string }) {
    return (
        <div className="grid gap-2">
            <div className="flex items-center gap-3">
                <Checkbox
                    id="adult"
                    name="adult"
                    required
                    {...fieldErrorProps('adult', error)}
                />
                <Label htmlFor="adult">Declaro que soy mayor de 18 años</Label>
            </div>
            <InputError id="adult-error" message={error} />
        </div>
    );
}

export default function Enrollment({
    studio,
    state,
    needsAdultDeclaration,
    studioUrl,
    joinUrl,
    passwordRules,
}: Props) {
    return (
        <>
            <Head title={`Únete a ${studio.name}`} />

            {state === 'register' && (
                <Form
                    action={joinUrl}
                    method="post"
                    resetOnError={['password', 'password_confirmation']}
                    onError={(errors) => focusFirstError(errors)}
                    disableWhileProcessing
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    {...fieldErrorProps('name', errors.name)}
                                    name="name"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    placeholder="Nombre completo"
                                />
                                <InputError
                                    id="name-error"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Correo</Label>
                                <Input
                                    id="email"
                                    {...fieldErrorProps('email', errors.email)}
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    placeholder="correo@ejemplo.com"
                                />
                                <InputError
                                    id="email-error"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Contraseña</Label>
                                <PasswordInput
                                    id="password"
                                    {...fieldErrorProps(
                                        'password',
                                        errors.password,
                                    )}
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    id="password-error"
                                    message={errors.password}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirmar contraseña
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                            </div>

                            <AdultDeclaration error={errors.adult} />

                            <Button type="submit" className="w-full">
                                {processing && <Spinner />}
                                Crear cuenta y unirme
                            </Button>

                            <p className="text-center text-sm text-muted-foreground">
                                ¿Ya tienes cuenta en Cupo?{' '}
                                <TextLink href={login()}>
                                    Inicia sesión
                                </TextLink>{' '}
                                y vuelves aquí para unirte.
                            </p>
                        </>
                    )}
                </Form>
            )}

            {state === 'join' && (
                <Form action={joinUrl} method="post" className="grid gap-6">
                    {({ processing, errors }) => (
                        <>
                            {needsAdultDeclaration && (
                                <AdultDeclaration error={errors.adult} />
                            )}
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Unirme a {studio.name}
                            </Button>
                        </>
                    )}
                </Form>
            )}

            {state === 'member' && (
                <div className="grid gap-4 text-center text-sm">
                    <p className="text-muted-foreground">
                        Ya eres parte de {studio.name}.
                    </p>
                    <Button asChild className="w-full">
                        <Link href={studioUrl}>Ir a {studio.name}</Link>
                    </Button>
                </div>
            )}
        </>
    );
}

Enrollment.layout = (props: Props) => ({
    title: `Únete a ${props.studio.name}`,
    description: `Crea tu cuenta para reservar tus clases en ${props.studio.name}, ${props.studio.commune}.`,
});
