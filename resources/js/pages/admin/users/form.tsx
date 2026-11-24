import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import admin from '@/routes/admin';
import type { Option, StaffUser } from '@/types';
import { Form, Head, Link, router } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useState } from 'react';

type Props = {
    user: StaffUser | null;
    roles: Option[];
};

export default function UserForm({ user, roles }: Props) {
    const isEdit = user !== null;
    const [selectedRoles, setSelectedRoles] = useState<string[]>(user?.roles ?? []);
    const [isActive, setIsActive] = useState<boolean>(user?.is_active ?? true);
    const [sendingLink, setSendingLink] = useState(false);
    const isDentist = selectedRoles.includes('odontologo');

    const toggleRole = (role: string, checked: boolean) =>
        setSelectedRoles((current) =>
            checked ? [...new Set([...current, role])] : current.filter((r) => r !== role),
        );

    const sendLink = () => {
        if (!user) {
            return;
        }

        router.post(
            admin.users.passwordLink(user.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setSendingLink(true),
                onFinish: () => setSendingLink(false),
            },
        );
    };

    const formRoute = isEdit ? admin.users.update.form(user.id) : admin.users.store.form();
    const title = isEdit ? `Editar a ${user.name}` : 'Nuevo usuario';

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={
                        isEdit
                            ? 'Los usuarios no se eliminan: desactívelos para quitarles el acceso.'
                            : 'El usuario recibirá un correo con un enlace para crear su contraseña.'
                    }
                />

                <Card className="max-w-2xl">
                    <CardContent>
                        <Form {...formRoute} className="grid gap-6" disableWhileProcessing>
                            {({ errors, processing }) => (
                                <>
                                    <FormField
                                        id="name"
                                        label="Nombre completo"
                                        error={errors.name}
                                        required
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name="name"
                                                defaultValue={user?.name}
                                                autoComplete="off"
                                                required
                                            />
                                        )}
                                    </FormField>

                                    <FormField
                                        id="email"
                                        label="Correo electrónico"
                                        error={errors.email}
                                        required
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name="email"
                                                type="email"
                                                defaultValue={user?.email}
                                                autoComplete="off"
                                                required
                                            />
                                        )}
                                    </FormField>

                                    <div className="grid gap-6 sm:grid-cols-2">
                                        <FormField
                                            id="phone"
                                            label="Teléfono"
                                            error={errors.phone}
                                            hint="Ej.: 0981 123 456"
                                        >
                                            {(p) => (
                                                <Input
                                                    {...p}
                                                    name="phone"
                                                    type="tel"
                                                    defaultValue={user?.phone ?? ''}
                                                />
                                            )}
                                        </FormField>

                                        <FormField
                                            id="license_number"
                                            label="Registro profesional"
                                            error={errors.license_number}
                                            required={isDentist}
                                            hint={
                                                isDentist
                                                    ? 'Obligatorio para odontólogos; se imprime en las recetas.'
                                                    : undefined
                                            }
                                        >
                                            {(p) => (
                                                <Input
                                                    {...p}
                                                    name="license_number"
                                                    defaultValue={user?.license_number ?? ''}
                                                />
                                            )}
                                        </FormField>
                                    </div>

                                    <fieldset
                                        className="grid gap-3"
                                        aria-describedby={errors.roles ? 'roles-error' : undefined}
                                    >
                                        <legend className="mb-1 text-sm font-medium">
                                            Roles
                                            <span className="text-destructive" aria-hidden="true">
                                                {' '}
                                                *
                                            </span>
                                        </legend>
                                        {roles.map((role) => (
                                            <div
                                                key={role.value}
                                                className="flex items-center gap-3"
                                            >
                                                <Checkbox
                                                    id={`role-${role.value}`}
                                                    checked={selectedRoles.includes(role.value)}
                                                    onCheckedChange={(checked) =>
                                                        toggleRole(role.value, checked === true)
                                                    }
                                                />
                                                <Label htmlFor={`role-${role.value}`}>
                                                    {role.label}
                                                </Label>
                                            </div>
                                        ))}
                                        {selectedRoles.map((role) => (
                                            <input
                                                key={role}
                                                type="hidden"
                                                name="roles[]"
                                                value={role}
                                            />
                                        ))}
                                        <InputError
                                            id="roles-error"
                                            message={errors.roles ?? errors['roles.0']}
                                        />
                                    </fieldset>

                                    {isEdit && (
                                        <div className="grid gap-2">
                                            <div className="flex items-center gap-3">
                                                <Checkbox
                                                    id="is_active"
                                                    checked={isActive}
                                                    onCheckedChange={(checked) =>
                                                        setIsActive(checked === true)
                                                    }
                                                />
                                                <Label htmlFor="is_active">Cuenta activa</Label>
                                            </div>
                                            <input
                                                type="hidden"
                                                name="is_active"
                                                value={isActive ? '1' : '0'}
                                            />
                                            <InputError message={errors.is_active} />
                                        </div>
                                    )}

                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button type="submit" disabled={processing}>
                                            {processing && <Spinner />}
                                            {isEdit ? 'Guardar cambios' : 'Crear usuario'}
                                        </Button>
                                        <Button asChild variant="ghost">
                                            <Link href={admin.users.index()}>Cancelar</Link>
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {isEdit && (
                    <Card className="max-w-2xl">
                        <CardContent className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="font-medium">Enlace de contraseña</p>
                                <p className="text-sm text-muted-foreground">
                                    Envía a {user.email} un enlace para crear una contraseña nueva.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={sendLink}
                                disabled={sendingLink}
                            >
                                {sendingLink ? <Spinner /> : <Mail aria-hidden="true" />}
                                Enviar enlace
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

UserForm.layout = {
    breadcrumbs: [{ title: 'Usuarios', href: admin.users.index() }],
};
