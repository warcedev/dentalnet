import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import portal from '@/routes/portal';
import { Form, Head } from '@inertiajs/react';

export default function PortalForgotPassword({ status }: { status?: string | null }) {
    return (
        <>
            <Head title="Recuperar contraseña" />
            {status && (
                <div
                    role="status"
                    className="text-center text-sm font-medium text-green-700 dark:text-green-400"
                >
                    {status}
                </div>
            )}
            <Form {...portal.password.email.form()} className="grid gap-6">
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
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing && <Spinner />}
                            Enviar enlace
                        </Button>
                    </>
                )}
            </Form>
            <p className="text-center text-sm text-muted-foreground">
                <TextLink href={portal.login()}>Volver a ingresar</TextLink>
            </p>
        </>
    );
}

PortalForgotPassword.layout = {
    title: 'Recuperar contraseña',
    description: 'Le enviaremos un enlace a su correo para crear una nueva contraseña',
};
