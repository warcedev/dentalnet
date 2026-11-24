import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { NativeSelect } from '@/components/native-select';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { AuditEntry, Option, Paginated } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { Filter, History } from 'lucide-react';

/** Nombres legibles de los campos auditados. Se amplía con cada módulo. */
const FIELD_LABELS: Record<string, string> = {
    name: 'Nombre',
    email: 'Correo',
    phone: 'Teléfono',
    license_number: 'Registro profesional',
    is_active: 'Activo',
    roles: 'Roles',
    email_verified_at: 'Correo verificado',
    two_factor_confirmed_at: '2FA confirmado',
    id: 'ID',
};

type Props = {
    logs: Paginated<AuditEntry>;
    filters: { usuario: string; evento: string; desde: string; hasta: string };
    users: { id: number; name: string }[];
    events: Option[];
};

function display(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Sí' : 'No';
    }

    if (Array.isArray(value)) {
        return value.join(', ');
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

function Changes({ entry }: { entry: AuditEntry }) {
    const keys = [
        ...new Set([
            ...Object.keys(entry.old_values ?? {}),
            ...Object.keys(entry.new_values ?? {}),
        ]),
    ];

    if (keys.length === 0) {
        return <span className="text-muted-foreground">Sin detalle</span>;
    }

    return (
        <details>
            <summary className="cursor-pointer text-primary underline-offset-4 hover:underline">
                Ver {keys.length} {keys.length === 1 ? 'campo' : 'campos'}
            </summary>
            <dl className="mt-2 grid gap-1">
                {keys.map((key) => (
                    <div key={key} className="grid grid-cols-[minmax(7rem,auto)_1fr] gap-2">
                        <dt className="text-muted-foreground">{FIELD_LABELS[key] ?? key}</dt>
                        <dd className="break-words">
                            {entry.old_values && key in entry.old_values && (
                                <>
                                    <del className="text-destructive">
                                        {display(entry.old_values[key])}
                                    </del>
                                    <span aria-hidden="true"> → </span>
                                    <span className="sr-only"> cambió a </span>
                                </>
                            )}
                            <ins className="no-underline">
                                {display(entry.new_values?.[key] ?? null)}
                            </ins>
                        </dd>
                    </div>
                ))}
            </dl>
        </details>
    );
}

export default function AuditIndex({ logs, filters, users, events }: Props) {
    const hasFilters = Object.values(filters).some((v) => v !== '');

    return (
        <>
            <Head title="Auditoría" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Auditoría"
                    description="Registro inmutable de accesos y cambios. No se puede editar ni borrar."
                />

                <Form
                    {...admin.audit.index.form()}
                    options={{ preserveState: true, replace: true }}
                    className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField id="usuario" label="Usuario">
                                {(p) => (
                                    <NativeSelect
                                        {...p}
                                        name="usuario"
                                        defaultValue={filters.usuario}
                                    >
                                        <option value="">Todos</option>
                                        {users.map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </FormField>
                            <FormField id="evento" label="Evento">
                                {(p) => (
                                    <NativeSelect
                                        {...p}
                                        name="evento"
                                        defaultValue={filters.evento}
                                    >
                                        <option value="">Todos</option>
                                        {events.map((e) => (
                                            <option key={e.value} value={e.value}>
                                                {e.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </FormField>
                            <FormField id="desde" label="Desde" error={errors.desde}>
                                {(p) => (
                                    <Input
                                        {...p}
                                        name="desde"
                                        type="date"
                                        defaultValue={filters.desde}
                                    />
                                )}
                            </FormField>
                            <FormField id="hasta" label="Hasta" error={errors.hasta}>
                                {(p) => (
                                    <Input
                                        {...p}
                                        name="hasta"
                                        type="date"
                                        defaultValue={filters.hasta}
                                    />
                                )}
                            </FormField>
                            <div className="flex gap-2">
                                <Button type="submit" variant="secondary" disabled={processing}>
                                    <Filter aria-hidden="true" />
                                    Filtrar
                                </Button>
                                {hasFilters && (
                                    <Button asChild variant="ghost">
                                        <Link href={admin.audit.index()}>Limpiar</Link>
                                    </Button>
                                )}
                            </div>
                        </>
                    )}
                </Form>

                {logs.data.length === 0 ? (
                    <EmptyState
                        icon={History}
                        title="No hay registros"
                        description={
                            hasFilters ? 'Ningún registro coincide con los filtros.' : undefined
                        }
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Registros de auditoría, del más reciente al más antiguo
                            </caption>
                            <thead className="border-b bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Fecha y hora
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Usuario
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Evento
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Registro afectado
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Cambios
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 font-medium lg:table-cell"
                                    >
                                        IP
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((entry) => (
                                    <tr key={entry.id} className="border-b align-top last:border-0">
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {formatDateTime(entry.created_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {entry.user ?? (
                                                <span className="text-muted-foreground">
                                                    Sistema / anónimo
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    entry.event.startsWith('login_')
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                            >
                                                {entry.event_label}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            {entry.model
                                                ? `${entry.model} #${entry.model_id}`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Changes entry={entry} />
                                        </td>
                                        <td className="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                                            {entry.ip_address ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination page={logs} itemLabel="registros" />
            </div>
        </>
    );
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Auditoría', href: admin.audit.index() }],
};
