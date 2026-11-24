import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated, StaffUser } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Search, ShieldCheck, UserPlus, Users } from 'lucide-react';

const ROLE_LABELS: Record<string, string> = {
    administrador: 'Administrador',
    odontologo: 'Odontólogo',
    recepcion: 'Recepción',
};

type Props = {
    users: Paginated<StaffUser>;
    filters: { buscar: string };
};

export default function UsersIndex({ users, filters }: Props) {
    return (
        <>
            <Head title="Usuarios" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Usuarios"
                    description="Personal del consultorio y sus permisos de acceso."
                    actions={
                        <Button asChild>
                            <Link href={admin.users.create()}>
                                <UserPlus aria-hidden="true" />
                                Nuevo usuario
                            </Link>
                        </Button>
                    }
                />

                <Form
                    {...admin.users.index.form()}
                    options={{ preserveState: true, replace: true }}
                    role="search"
                    className="flex max-w-md items-end gap-2"
                >
                    {({ processing }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="buscar">Buscar por nombre o correo</Label>
                                <Input
                                    id="buscar"
                                    name="buscar"
                                    type="search"
                                    defaultValue={filters.buscar}
                                />
                            </div>
                            <Button type="submit" variant="secondary" disabled={processing}>
                                <Search aria-hidden="true" />
                                Buscar
                            </Button>
                        </>
                    )}
                </Form>

                {users.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={
                            filters.buscar
                                ? 'No hay usuarios que coincidan'
                                : 'Todavía no hay usuarios'
                        }
                        description={
                            filters.buscar ? 'Pruebe con otro nombre o correo.' : undefined
                        }
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">Listado de usuarios</caption>
                            <thead className="border-b bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Nombre
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Roles
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 font-medium md:table-cell"
                                    >
                                        Último acceso
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        <span className="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.map((user) => (
                                    <tr key={user.id} className="border-b last:border-0">
                                        <td className="px-4 py-3">
                                            <div className="font-medium">{user.name}</div>
                                            <div className="text-muted-foreground">
                                                {user.email}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.map((role) => (
                                                    <Badge key={role} variant="secondary">
                                                        {ROLE_LABELS[role] ?? role}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap items-center gap-1">
                                                {user.is_active ? (
                                                    <Badge variant="outline">Activo</Badge>
                                                ) : (
                                                    <Badge variant="destructive">Inactivo</Badge>
                                                )}
                                                {user.two_factor_enabled && (
                                                    <span title="Verificación en dos pasos activa">
                                                        <ShieldCheck
                                                            className="size-4 text-primary"
                                                            aria-hidden="true"
                                                        />
                                                        <span className="sr-only">
                                                            Verificación en dos pasos activa
                                                        </span>
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="hidden px-4 py-3 text-muted-foreground md:table-cell">
                                            {formatDateTime(user.last_login_at, 'Nunca')}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button asChild variant="ghost" size="sm">
                                                <Link href={admin.users.edit(user.id)}>
                                                    <Pencil aria-hidden="true" />
                                                    Editar
                                                    <span className="sr-only"> a {user.name}</span>
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination page={users} itemLabel="usuarios" />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Usuarios', href: admin.users.index() }],
};
