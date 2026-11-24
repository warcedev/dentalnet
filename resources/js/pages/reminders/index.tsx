import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { NativeSelect } from '@/components/native-select';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatLocalDateTime } from '@/lib/format';
import reminders from '@/routes/reminders';
import type { Option, Paginated } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { BellRing, Filter, Play, RotateCcw } from 'lucide-react';

type ReminderRow = {
    id: number;
    patient: { id: number; name: string };
    type: string;
    channel: string;
    scheduled_for: string;
    status: string;
    status_label: string;
    attempts: number;
    last_error: string | null;
    sent_at: string | null;
    logs: { attempt: number; status: string; message: string | null; created_at: string }[];
};

type Props = {
    reminders: Paginated<ReminderRow>;
    filters: { estado: string; tipo: string };
    counts: Record<string, number>;
    statuses: Option[];
    types: Option[];
    channel: string;
};

const VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pendiente: 'outline',
    en_cola: 'outline',
    enviado: 'default',
    fallido: 'destructive',
    cancelado: 'secondary',
    omitido: 'secondary',
};

const local = (value: string | null) => formatLocalDateTime(value);

export default function Reminders({
    reminders: list,
    filters,
    counts,
    statuses,
    types,
    channel,
}: Props) {
    return (
        <>
            <Head title="Recordatorios" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Recordatorios"
                    description={`Se envían por ${channel === 'email' ? 'correo electrónico' : channel} solo a pacientes que aceptaron recibir comunicaciones. Una tarea programada los procesa cada 10 minutos.`}
                    actions={
                        <Form {...reminders.run.form()} options={{ preserveScroll: true }}>
                            {({ processing }) => (
                                <Button type="submit" variant="outline" disabled={processing}>
                                    {processing ? <Spinner /> : <Play aria-hidden="true" />}
                                    Procesar ahora
                                </Button>
                            )}
                        </Form>
                    }
                />

                <ul className="flex flex-wrap gap-2" aria-label="Totales por estado">
                    {statuses.map((s) => (
                        <li key={s.value}>
                            <Link
                                href={reminders.index({
                                    query: filters.estado === s.value ? {} : { estado: s.value },
                                })}
                                className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm hover:bg-accent aria-[current=true]:border-primary aria-[current=true]:bg-accent"
                                aria-current={filters.estado === s.value ? 'true' : undefined}
                            >
                                {s.label}
                                <span className="font-semibold tabular-nums">
                                    {counts[s.value] ?? 0}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>

                <Form
                    {...reminders.index.form()}
                    options={{ preserveState: true, replace: true }}
                    className="flex flex-wrap items-end gap-3"
                >
                    {() => (
                        <>
                            <input type="hidden" name="estado" value={filters.estado} />
                            <div className="w-56">
                                <FormField id="tipo" label="Tipo">
                                    {(p) => (
                                        <NativeSelect
                                            {...p}
                                            name="tipo"
                                            defaultValue={filters.tipo}
                                        >
                                            <option value="">Todos</option>
                                            {types.map((t) => (
                                                <option key={t.value} value={t.value}>
                                                    {t.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            </div>
                            <Button type="submit" variant="secondary">
                                <Filter aria-hidden="true" />
                                Filtrar
                            </Button>
                        </>
                    )}
                </Form>

                {list.data.length === 0 ? (
                    <EmptyState
                        icon={BellRing}
                        title="No hay recordatorios"
                        description="Se crean al agendar citas y con la tarea programada (limpiezas, controles y cumpleaños)."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">Recordatorios</caption>
                            <thead className="border-b bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Programado
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Paciente
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Tipo
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Detalle
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.data.map((r) => (
                                    <tr key={r.id} className="border-b align-top last:border-0">
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {local(r.scheduled_for)}
                                        </td>
                                        <td className="px-4 py-3">{r.patient.name}</td>
                                        <td className="px-4 py-3">
                                            {r.type}
                                            <span className="block text-xs text-muted-foreground">
                                                {r.channel}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant={VARIANT[r.status]}>
                                                {r.status_label}
                                            </Badge>
                                            {r.attempts > 0 && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {r.attempts} intento(s)
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {r.last_error && (
                                                <p className="text-xs">{r.last_error}</p>
                                            )}
                                            {r.logs.length > 0 && (
                                                <details className="text-xs">
                                                    <summary className="cursor-pointer text-muted-foreground">
                                                        Registro de envíos ({r.logs.length})
                                                    </summary>
                                                    <ol className="mt-1 grid gap-0.5">
                                                        {r.logs.map((l, i) => (
                                                            <li key={i}>
                                                                #{l.attempt} · {local(l.created_at)}{' '}
                                                                · {l.status}
                                                                {l.message && ` · ${l.message}`}
                                                            </li>
                                                        ))}
                                                    </ol>
                                                </details>
                                            )}
                                            {(r.status === 'fallido' || r.status === 'omitido') && (
                                                <Form
                                                    {...reminders.retry.form(r.id)}
                                                    options={{ preserveScroll: true }}
                                                    className="mt-1"
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={processing}
                                                        >
                                                            <RotateCcw aria-hidden="true" />
                                                            Reintentar
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination page={list} itemLabel="recordatorios" />
            </div>
        </>
    );
}

Reminders.layout = {
    breadcrumbs: [{ title: 'Recordatorios', href: reminders.index() }],
};
