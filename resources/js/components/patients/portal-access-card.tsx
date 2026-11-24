import { FormField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import patients from '@/routes/patients';
import { Form } from '@inertiajs/react';

export type PortalAccess = {
    account: {
        email: string;
        is_active: boolean;
        invited_at: string | null;
        last_login_at: string | null;
    } | null;
    can_manage: boolean;
};

/** Acceso del paciente a su portal: invitación por correo y activación. */
export function PortalAccessCard({
    patientId,
    portal,
    defaultEmail,
}: {
    patientId: number;
    portal: PortalAccess;
    defaultEmail: string | null;
}) {
    const { account, can_manage } = portal;

    if (!account && !can_manage) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <h2>Portal del paciente</h2>
                    {account && (
                        <Badge variant={account.is_active ? 'secondary' : 'destructive'}>
                            {account.is_active ? 'Activo' : 'Desactivado'}
                        </Badge>
                    )}
                </CardTitle>
                <CardDescription>
                    El paciente ve sus turnos, odontograma, tratamientos, recetas y recibos. No ve
                    las notas clínicas internas.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4 text-sm">
                {account && (
                    <dl className="grid gap-2 sm:grid-cols-3">
                        <div>
                            <dt className="text-muted-foreground">Correo de acceso</dt>
                            <dd className="font-medium break-all">{account.email}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Última invitación</dt>
                            <dd>{formatDateTime(account.invited_at)}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Último ingreso</dt>
                            <dd>{formatDateTime(account.last_login_at, 'Nunca ingresó')}</dd>
                        </div>
                    </dl>
                )}
                {can_manage && (
                    <div className="flex flex-wrap items-end gap-3">
                        <Form
                            {...patients.portal.invite.form(patientId)}
                            options={{ preserveScroll: true }}
                            className="flex flex-wrap items-end gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormField
                                        id="portal-email"
                                        label="Correo electrónico"
                                        error={errors.email}
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name="email"
                                                type="email"
                                                autoComplete="off"
                                                defaultValue={account?.email ?? defaultEmail ?? ''}
                                                required
                                                className="w-72 max-w-full"
                                            />
                                        )}
                                    </FormField>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        {account ? 'Reenviar invitación' : 'Invitar al portal'}
                                    </Button>
                                </>
                            )}
                        </Form>
                        {account && (
                            <Form
                                {...patients.portal.toggle.form(patientId)}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button type="submit" variant="outline" disabled={processing}>
                                        {account.is_active
                                            ? 'Desactivar acceso'
                                            : 'Reactivar acceso'}
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
