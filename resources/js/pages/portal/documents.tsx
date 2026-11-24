import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatGuaranies, formatLocalDateTime } from '@/lib/format';
import { Head } from '@inertiajs/react';
import { FileDown } from 'lucide-react';

type Props = {
    prescriptions: {
        id: number;
        type: string;
        issued_at: string;
        professional: string;
        url: string;
    }[];
    receipts: {
        id: number;
        receipt_number: string;
        amount: number;
        paid_at: string;
        method: string;
        url: string;
    }[];
};

const pdfLink = (url: string, label: string) => (
    <a
        href={url}
        target="_blank"
        rel="noopener"
        className="inline-flex items-center gap-1 font-medium text-primary underline-offset-4 hover:underline"
    >
        <FileDown className="size-4" aria-hidden="true" />
        {label}
        <span className="sr-only"> (PDF, se abre en una pestaña nueva)</span>
    </a>
);

export default function PortalDocuments({ prescriptions, receipts }: Props) {
    return (
        <>
            <Head title="Recetas y recibos" />
            <h1 className="text-2xl font-semibold tracking-tight">Recetas y recibos</h1>
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        <h2>Recetas e indicaciones</h2>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {prescriptions.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No tiene recetas emitidas.</p>
                    ) : (
                        <ul className="divide-y text-sm">
                            {prescriptions.map((p) => (
                                <li
                                    key={p.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2"
                                >
                                    <span>
                                        {p.type} · {formatLocalDateTime(p.issued_at)} ·{' '}
                                        {p.professional}
                                    </span>
                                    {pdfLink(p.url, 'Descargar')}
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        <h2>Recibos de pago</h2>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {receipts.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No hay pagos registrados.</p>
                    ) : (
                        <ul className="divide-y text-sm">
                            {receipts.map((r) => (
                                <li
                                    key={r.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2"
                                >
                                    <span>
                                        Recibo {r.receipt_number} · {formatLocalDateTime(r.paid_at)}{' '}
                                        · {r.method} ·{' '}
                                        <span className="font-medium tabular-nums">
                                            {formatGuaranies(r.amount)}
                                        </span>
                                    </span>
                                    {pdfLink(r.url, 'Descargar')}
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </>
    );
}
