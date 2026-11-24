import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatLocalDateTime } from '@/lib/format';
import { Head } from '@inertiajs/react';

type Props = {
    consultations: {
        id: number;
        consulted_at: string;
        professional: string;
        reason: string | null;
        procedures: string[];
        next_visit: string | null;
    }[];
    treatments: {
        id: number;
        procedure: string;
        tooth_code: number | null;
        completed_on: string | null;
        warranty_until: string | null;
    }[];
};

export default function PortalHistory({ consultations, treatments }: Props) {
    return (
        <>
            <Head title="Historial" />
            <h1 className="text-2xl font-semibold tracking-tight">Historial</h1>
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        <h2>Tratamientos realizados</h2>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {treatments.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Todavía no hay tratamientos completados.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="py-2 pr-3 font-medium">
                                            Fecha
                                        </th>
                                        <th scope="col" className="py-2 pr-3 font-medium">
                                            Tratamiento
                                        </th>
                                        <th scope="col" className="py-2 pr-3 font-medium">
                                            Pieza
                                        </th>
                                        <th scope="col" className="py-2 font-medium">
                                            Garantía hasta
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {treatments.map((t) => (
                                        <tr key={t.id} className="border-b last:border-0">
                                            <td className="py-2 pr-3">
                                                {formatDate(t.completed_on)}
                                            </td>
                                            <td className="py-2 pr-3">{t.procedure}</td>
                                            <td className="py-2 pr-3 tabular-nums">
                                                {t.tooth_code ?? '—'}
                                            </td>
                                            <td className="py-2">
                                                {formatDate(t.warranty_until, 'Sin garantía')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        <h2>Consultas</h2>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {consultations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No hay consultas registradas.
                        </p>
                    ) : (
                        <ol className="grid gap-3">
                            {consultations.map((c) => (
                                <li key={c.id} className="rounded-lg border p-3 text-sm">
                                    <p className="font-medium">
                                        {formatLocalDateTime(c.consulted_at)} · {c.professional}
                                    </p>
                                    {c.reason && <p>Motivo: {c.reason}</p>}
                                    {c.procedures.length > 0 && (
                                        <p className="text-muted-foreground">
                                            Procedimientos: {c.procedures.join(', ')}
                                        </p>
                                    )}
                                    {c.next_visit && (
                                        <p className="text-muted-foreground">
                                            Próxima visita: {c.next_visit}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </CardContent>
            </Card>
        </>
    );
}
