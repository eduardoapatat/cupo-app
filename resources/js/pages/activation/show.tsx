import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fieldErrorProps, focusFirstError } from '@/lib/form';

type Props = {
    name: string;
    email: string;
    activateUrl: string;
    passwordRules: string;
};

export default function ActivateAccount({
    email,
    activateUrl,
    passwordRules,
}: Props) {
    return (
        <>
            <Head title="Activa tu cuenta" />

            <Form
                action={activateUrl}
                method="post"
                resetOnError
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
                                value={email}
                                readOnly
                                autoComplete="username"
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
                                autoFocus
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

                        <Button type="submit" className="w-full">
                            {processing && <Spinner />}
                            Crear mi contraseña
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

ActivateAccount.layout = (props: Props) => ({
    title: `Hola, ${props.name}`,
    description:
        'Crea tu contraseña para entrar a Cupo y reservar tus clases desde el celular.',
});
