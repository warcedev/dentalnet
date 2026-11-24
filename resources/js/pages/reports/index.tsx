import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatGuaranies } from '@/lib/format';
import patients from '@/routes/patients';
import reports from '@/routes/reports';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import { useState, type ReactNode } from 'react';

type Props = {
    period: { from: string; to: string };
    attended: { patients: number; consultations: number; attended_appointments: number };
    top_procedures: { procedure: string; count: number }[];
    income_by_professional: { professional: string; payments: number; total: number }[];
    income_by_method: { method: string; label: string; payments: number; total: number }[];
    income_total: number;
    no_show: { no_show: number; attended: number; rate: number };
    without_control: {
        id: number;
        name: string;
        phone: string | null;
        last_visit: string | null;
        accepts_communications: boolean;
    }[];
};

/** Barra de magnitud en una celda: un solo tono, el valor siempre en texto al lado. */
function Bar({ value, max }: { value: number; max: number }) {
    return (
        <span className="block h-2 w-full max-w-40 rounded-full bg-muted" aria-hidden="true">
            <span
                className="block h-2 rounded-full bg-primary"
                style={{ width: `${max > 0 ? Math.max(2, (value / max) * 100) : 0}%` }}
            />
        </span>
    );
}

function Section({
    title,
    description,
    csv,
    query,
    children,
}: {
    title: string;
    description?: string;
    csv: string;
    query: Record<string, string>;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="grid min-w-0 gap-1.5">
                    <CardTitle className="text-base">
                        <h2>{title}</h2>
                    </CardTitle>
                    {description && <CardDescription>{description}</CardDescription>}
                </div>
                <Button variant="outline" size="sm" asChild>
                    <a href={reports.csv(csv, { query }).url}>
                        <Download aria-hidden="true" />
                        CSV<span className="sr-only"> de {title.toLowerCase()}</span>
                    </a>
                </Button>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

const Empty = () => <p className="text-sm text-muted-foreground">Sin datos en el período.</p>;

export default function ReportsIndex(props: Props) {
    const {
        period,
        attended,
        top_procedures,
        income_by_professional,
        income_by_method,
        income_total,
        no_show,
        without_control,
    } = props;
    const errors = usePage().props.errors as Record<string, string>;
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const [loading, setLoading] = useState(false);
    const query = { desde: period.from, hasta: period.to };
    const maxProc = Math.max(0, ...top_procedures.map((p) => p.count));
    const maxProf = Math.max(0, ...income_by_professional.map((p) => p.total));
    const maxMethod = Math.max(0, ...income_by_method.map((p) => p.total));

    return (
        <>
            <Head title="Reportes" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Reportes"
                    description={`Período del ${formatDate(period.from)} al ${formatDate(period.to)}`}
                    actions={
                        <Button variant="outline" asChild>
                            <a href={reports.pdf({ query }).url} target="_blank" rel="noopener">
                                <FileText aria-hidden="true" />
                                Exportar PDF
                            </a>
                        </Button>
                    }
                />

                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            reports.index().url,
                            { desde: from, hasta: to },
                            {
                                preserveState: true,
                                onStart: () => setLoading(true),
                                onFinish: () => setLoading(false),
                            },
                        );
                    }}
                >
                    <FormField id="desde" label="Desde" error={errors.desde}>
                        {(p) => (
                            <Input
                                {...p}
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                            />
                        )}
                    </FormField>
                    <FormField id="hasta" label="Hasta" error={errors.hasta}>
                        {(p) => (
                            <Input
                                {...p}
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                            />
                        )}
                    </FormField>
                    <Button type="submit" disabled={loading}>
                        {loading && <Spinner />}
                        Aplicar
                    </Button>
                </form>

                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-busy={loading}>
                    {[
                        {
                            label: 'Pacientes atendidos',
                            value: attended.patients.toLocaleString('es-PY'),
                            hint: `${attended.consultations} consultas`,
                        },
                        {
                            label: 'Turnos atendidos',
                            value: attended.attended_appointments.toLocaleString('es-PY'),
                            hint: 'Con estado atendido',
                        },
                        {
                            label: 'Ingresos',
                            value: formatGuaranies(income_total),
                            hint: 'Pagos no anulados',
                        },
                        {
                            label: 'Ausentismo',
                            value: `${no_show.rate.toLocaleString('es-PY')} %`,
                            hint: `${no_show.no_show} ausencias de ${no_show.no_show + no_show.attended} turnos`,
                        },
                    ].map((k) => (
                        <div key={k.label} className="rounded-xl border bg-card p-4">
                            <dt className="text-sm text-muted-foreground">{k.label}</dt>
                            <dd className="text-2xl font-semibold tabular-nums">{k.value}</dd>
                            <dd className="text-xs text-muted-foreground">{k.hint}</dd>
                        </div>
                    ))}
                </dl>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Section title="Tratamientos más realizados" csv="tratamientos" query={query}>
                        {top_procedures.length === 0 ? (
                            <Empty />
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead className="border-b text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="py-2 pr-3 font-medium">
                                            Procedimiento
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 text-right font-medium"
                                        >
                                            Cantidad
                                        </th>
                                        <th scope="col" className="w-32 py-2">
                                            <span className="sr-only">Proporción</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {top_procedures.map((p) => (
                                        <tr key={p.procedure} className="border-b last:border-0">
                                            <td className="py-2 pr-3">{p.procedure}</td>
                                            <td className="py-2 pr-3 text-right tabular-nums">
                                                {p.count}
                                            </td>
                                            <td className="py-2">
                                                <Bar value={p.count} max={maxProc} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Section>

                    <Section
                        title="Ingresos por profesional"
                        description="Según el profesional responsable del plan de tratamiento."
                        csv="ingresos-profesional"
                        query={query}
                    >
                        {income_by_professional.length === 0 ? (
                            <Empty />
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead className="border-b text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="py-2 pr-3 font-medium">
                                            Profesional
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 text-right font-medium"
                                        >
                                            Pagos
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 text-right font-medium"
                                        >
                                            Total
                                        </th>
                                        <th scope="col" className="w-32 py-2">
                                            <span className="sr-only">Proporción</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {income_by_professional.map((p) => (
                                        <tr key={p.professional} className="border-b last:border-0">
                                            <td className="py-2 pr-3">{p.professional}</td>
                                            <td className="py-2 pr-3 text-right tabular-nums">
                                                {p.payments}
                                            </td>
                                            <td className="py-2 pr-3 text-right tabular-nums">
                                                {formatGuaranies(p.total)}
                                            </td>
                                            <td className="py-2">
                                                <Bar value={p.total} max={maxProf} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Section>

                    <Section title="Ingresos por forma de pago" csv="ingresos-forma" query={query}>
                        {income_by_method.length === 0 ? (
                            <Empty />
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead className="border-b text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="py-2 pr-3 font-medium">
                                            Forma
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 text-right font-medium"
                                        >
                                            Pagos
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 text-right font-medium"
                                        >
                                            Total
                                        </th>
                                        <th scope="col" className="w-32 py-2">
                                            <span className="sr-only">Proporción</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {income_by_method.map((p) => (
                                        <tr key={p.method} className="border-b last:border-0">
                                            <td className="py-2 pr-3">{p.label}</td>
                                            <td className="py-2 pr-3 text-right tabular-nums">
                                                {p.payments}
                                            </td>
                                            <td className="py-2 pr-3 text-right tabular-nums">
                                                {formatGuaranies(p.total)}
                                            </td>
                                            <td className="py-2">
                                                <Bar value={p.total} max={maxMethod} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Section>

                    <Section
                        title="Pacientes sin control"
                        description="Sin visitas en los últimos 6 meses (no depende del período elegido)."
                        csv="sin-control"
                        query={query}
                    >
                        {without_control.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Todos los pacientes tienen control al día.
                            </p>
                        ) : (
                            <div className="max-h-96 overflow-y-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="sticky top-0 border-b bg-card text-muted-foreground">
                                        <tr>
                                            <th scope="col" className="py-2 pr-3 font-medium">
                                                Paciente
                                            </th>
                                            <th scope="col" className="py-2 pr-3 font-medium">
                                                Teléfono
                                            </th>
                                            <th scope="col" className="py-2 font-medium">
                                                Última visita
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {without_control.map((p) => (
                                            <tr key={p.id} className="border-b last:border-0">
                                                <td className="py-2 pr-3">
                                                    <Link
                                                        href={patients.show(p.id)}
                                                        className="font-medium text-primary underline-offset-4 hover:underline"
                                                    >
                                                        {p.name}
                                                    </Link>
                                                    {!p.accepts_communications && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            No acepta recordatorios
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-2 pr-3">{p.phone ?? '—'}</td>
                                                <td className="py-2">
                                                    {formatDate(p.last_visit, 'Nunca')}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Section>
                </div>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reportes', href: reports.index() }],
};
