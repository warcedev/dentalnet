import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import portal from '@/routes/portal';
import { Form, Head } from '@inertiajs/react';

export default function PortalSetPassword({ token, email }: { token: string; email: string }) {
    return (
        <>
            <Head title="Crear contraseña" />
            <Form
                {...portal.password.update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="grid gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                type="email"
                                value={email}
                                readOnly
                                autoComplete="email"
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Nueva contraseña</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                aria-describedby="password-hint"
                            />
                            <p id="password-hint" className="text-xs text-muted-foreground">
                                Al menos 10 caracteres, con letras y números.
                            </p>
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">Repita la contraseña</Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                            />
                        </div>
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar contraseña
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

PortalSetPassword.layout = {
    title: 'Crear contraseña',
    description: 'Elija la contraseña para ingresar al portal del paciente',
};
