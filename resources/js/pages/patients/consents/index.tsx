import { EmptyState } from '@/components/empty-state';
import { PatientLayout } from '@/components/patients/patient-layout';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import patients from '@/routes/patients';
import type { PatientHeader } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FileSignature, PenLine } from 'lucide-react';

type Signature = {
    id: number;
    title: string;
    version: number;
    signer_name: string;
    signer_relationship: string;
    witness: string | null;
    signed_at: string;
};

export default function ConsentsIndex({
    header,
    signatures,
}: {
    header: PatientHeader;
    signatures: Signature[];
}) {
    const signAction = header.can.collect_consent && (
        <Button asChild>
            <Link href={patients.consents.create(header.id)}>
                <PenLine aria-hidden="true" />
                Firmar consentimiento
            </Link>
        </Button>
    );

    return (
        <>
            <Head title={`Consentimientos — ${header.full_name}`} />
            <PatientLayout header={header} actions={signAction}>
                {signatures.length === 0 ? (
                    <EmptyState
                        icon={FileSignature}
                        title="Sin consentimientos firmados"
                        description="Los consentimientos firmados quedan guardados aquí con la versión del texto aceptado."
                        action={signAction}
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">Consentimientos firmados</caption>
                            <thead className="border-b bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Consentimiento
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Firmado por
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 font-medium md:table-cell"
                                    >
                                        Profesional
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Fecha
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {signatures.map((s) => (
                                    <tr key={s.id} className="border-b last:border-0">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={patients.consents.show({
                                                    patient: header.id,
                                                    consentSignature: s.id,
                                                })}
                                                className="font-medium text-primary underline-offset-4 hover:underline"
                                            >
                                                {s.title}
                                            </Link>
                                            <span className="block text-xs text-muted-foreground">
                                                Versión {s.version}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {s.signer_name}
                                            <span className="block text-xs text-muted-foreground">
                                                {s.signer_relationship}
                                            </span>
                                        </td>
                                        <td className="hidden px-4 py-3 md:table-cell">
                                            {s.witness ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {formatDateTime(s.signed_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </PatientLayout>
        </>
    );
}

ConsentsIndex.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
