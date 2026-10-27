import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { login, logout } from '@/routes';

type Props = {
    invitation: {
        studio: string;
        role: string;
        invitedBy: string | null;
        email: string;
        expiresOn: string;
    };
    /**
     * Who opened the link: a guest without an account, a guest with one, a
     * signed-in person with another email, a member, or the invitee.
     */
    state: 'register' | 'login' | 'other_account' | 'member' | 'accept';
    studioUrl: string;
    acceptUrl: string;
    passwordRules: string;
};

function RegisterForm({ invitation, acceptUrl, passwordRules }: Props) {
    return (
        <Form
            action={acceptUrl}
            method="post"
            resetOnError={['password', 'password_confirmation']}
            onError={(errors) => focusFirstError(errors)}
            disableWhileProcessing
            className="grid gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Correo</Label>
                        <Input
                            id="email"
                            {...fieldErrorProps('email', errors.email)}
                            value={invitation.email}
                            readOnly
                        />
                        <InputError id="email-error" message={errors.email} />
                    </div>

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
                        <InputError id="name-error" message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Contraseña</Label>
                        <PasswordInput
                            id="password"
                            {...fieldErrorProps('password', errors.password)}
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

                    <div className="grid gap-2">
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="adult"
                                name="adult"
                                required
                                {...fieldErrorProps('adult', errors.adult)}
                            />
                            <Label htmlFor="adult">
                                Declaro que soy mayor de 18 años
                            </Label>
                        </div>
                        <InputError id="adult-error" message={errors.adult} />
                    </div>

                    <Button type="submit" className="w-full">
                        {processing && <Spinner />}
                        Crear cuenta y unirme
                    </Button>
                </>
            )}
        </Form>
    );
}

export default function ShowInvitation(props: Props) {
    const { invitation, state, studioUrl, acceptUrl } = props;

    return (
        <>
            <Head title={`Invitación a ${invitation.studio}`} />

            {state === 'register' && <RegisterForm {...props} />}

            {state === 'login' && (
                <div className="grid gap-4 text-center text-sm">
                    <p className="text-muted-foreground">
                        Ya tienes una cuenta con {invitation.email}. Inicia
                        sesión y vuelves aquí para aceptar.
                    </p>
                    <Button asChild className="w-full">
                        <Link href={login()}>Iniciar sesión</Link>
                    </Button>
                </div>
            )}

            {state === 'other_account' && (
                <div className="grid gap-4 text-center text-sm">
                    <p className="text-muted-foreground">
                        Esta invitación es para {invitation.email}, pero
                        entraste con otra cuenta. Cierra sesión y entra con ese
                        correo.
                    </p>
                    <Button asChild variant="secondary" className="w-full">
                        <Link href={logout()} as="button">
                            Cerrar sesión
                        </Link>
                    </Button>
                </div>
            )}

            {state === 'member' && (
                <div className="grid gap-4 text-center text-sm">
                    <p className="text-muted-foreground">
                        Ya eres parte de {invitation.studio}, así que no hay
                        nada que aceptar.
                    </p>
                    <Button asChild className="w-full">
                        <Link href={studioUrl}>Ir a {invitation.studio}</Link>
                    </Button>
                </div>
            )}

            {state === 'accept' && (
                <Form action={acceptUrl} method="post">
                    {({ processing, errors }) => (
                        <div className="grid gap-2">
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Unirme a {invitation.studio}
                            </Button>
                            <InputError message={errors.invitation} />
                        </div>
                    )}
                </Form>
            )}
        </>
    );
}

ShowInvitation.layout = (props: Props) => ({
    title: `Te invitaron a ${props.invitation.studio}`,
    description: `${props.invitation.invitedBy ?? 'La dueña'} te invitó como ${props.invitation.role}. La invitación vence el ${props.invitation.expiresOn}.`,
});
