import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import portal from '@/routes/portal';
import { Form, Head } from '@inertiajs/react';

export default function PortalLogin({ status }: { status?: string | null }) {
    return (
        <>
            <Head title="Portal del paciente" />
            {status && (
                <div
                    role="status"
                    className="text-center text-sm font-medium text-green-700 dark:text-green-400"
                >
                    {status}
                </div>
            )}
            <Form
                {...portal.login.store.form()}
                resetOnSuccess={['password']}
                className="grid gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                autoComplete="email"
                                aria-invalid={!!errors.email}
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">Contraseña</Label>
                                <TextLink
                                    href={portal.password.request()}
                                    className="ml-auto text-sm"
                                >
                                    ¿Olvidó su contraseña?
                                </TextLink>
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="flex items-center gap-3">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember">Recordarme en este equipo</Label>
                        </div>
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing && <Spinner />}
                            Ingresar
                        </Button>
                        <p className="text-center text-sm text-muted-foreground">
                            El acceso lo habilita el consultorio. Si no recibió la invitación,
                            consúltelo en recepción.
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

PortalLogin.layout = {
    title: 'Portal del paciente',
    description: 'Consulte sus turnos, tratamientos, recetas y recibos',
};
