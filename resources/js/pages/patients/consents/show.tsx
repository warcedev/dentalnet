import { PatientLayout } from '@/components/patients/patient-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import patients from '@/routes/patients';
import type { PatientHeader } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';

type Signature = {
    id: number;
    title: string;
    version: number;
    body: string;
    body_hash: string;
    signature_hash: string;
    signer_name: string;
    signer_document: string;
    signer_relationship: string;
    witness: string | null;
    signed_at: string;
    image_url: string;
};

export default function ConsentShow({
    header,
    signature,
}: {
    header: PatientHeader;
    signature: Signature;
}) {
    return (
        <>
            <Head title={`${signature.title} — ${header.full_name}`} />
            <PatientLayout
                header={header}
                actions={
                    <>
                        <Button variant="outline" onClick={() => window.print()}>
                            <Printer aria-hidden="true" />
                            Imprimir
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href={patients.consents.index(header.id)}>
                                <ArrowLeft aria-hidden="true" />
                                Volver
                            </Link>
                        </Button>
                    </>
                }
            >
                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle>
                            <h2 className="text-lg">{signature.title}</h2>
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Versión {signature.version} · Firmado el{' '}
                            {formatDateTime(signature.signed_at)}
                        </p>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        <div className="text-sm leading-relaxed whitespace-pre-line">
                            {signature.body}
                        </div>

                        <div className="grid gap-2 sm:grid-cols-[auto_1fr] sm:items-end sm:gap-6">
                            <img
                                src={signature.image_url}
                                alt={`Firma de ${signature.signer_name}`}
                                className="h-28 w-auto max-w-xs rounded border bg-white"
                            />
                            <dl className="grid gap-1 text-sm">
                                <div>
                                    <dt className="inline text-muted-foreground">Firmó: </dt>
                                    <dd className="inline">
                                        {signature.signer_name} ({signature.signer_relationship})
                                    </dd>
                                </div>
                                <div>
                                    <dt className="inline text-muted-foreground">Documento: </dt>
                                    <dd className="inline">{signature.signer_document}</dd>
                                </div>
                                <div>
                                    <dt className="inline text-muted-foreground">
                                        Profesional presente:{' '}
                                    </dt>
                                    <dd className="inline">{signature.witness ?? '—'}</dd>
                                </div>
                            </dl>
                        </div>

                        <details className="text-xs text-muted-foreground">
                            <summary className="cursor-pointer">Datos de verificación</summary>
                            <p className="mt-2 break-all">
                                Huella del texto (SHA-256): {signature.body_hash}
                            </p>
                            <p className="break-all">
                                Huella de la firma (SHA-256): {signature.signature_hash}
                            </p>
                        </details>
                    </CardContent>
                </Card>
            </PatientLayout>
        </>
    );
}

ConsentShow.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
