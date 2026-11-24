import { ConfirmDialog } from '@/components/confirm-dialog';
import { PatientLayout } from '@/components/patients/patient-layout';
import { PortalAccessCard, type PortalAccess } from '@/components/patients/portal-access-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatLocalDateTime } from '@/lib/format';
import { agenda } from '@/routes';
import patients from '@/routes/patients';
import type { PatientDetails, PatientHeader } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { Archive, ArchiveRestore, CalendarPlus, Pencil } from 'lucide-react';

type Props = {
    header: PatientHeader;
    patient: PatientDetails;
    labels: { guardian_relationship: string | null };
    upcoming:
        | {
              id: number;
              starts_at: string;
              type: string;
              professional: string;
              room: string;
              status_label: string;
          }[]
        | null;
    can_book: boolean;
    portal: PortalAccess;
};

function Field({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="grid gap-0.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm">{value || '—'}</dd>
        </div>
    );
}

export default function PatientShow({
    header,
    patient,
    labels,
    upcoming,
    can_book,
    portal,
}: Props) {
    const actions = (
        <>
            {header.can.update && (
                <Button asChild variant="outline">
                    <Link href={patients.edit(header.id)}>
                        <Pencil aria-hidden="true" />
                        Editar datos
                    </Link>
                </Button>
            )}
            {header.can.archive && (
                <ConfirmDialog
                    trigger={
                        <Button variant="ghost">
                            <Archive aria-hidden="true" />
                            Archivar
                        </Button>
                    }
                    title={`¿Archivar a ${header.full_name}?`}
                    description="El paciente dejará de aparecer en las búsquedas. Su historial clínico se conserva completo y un administrador puede restaurarlo."
                    confirmLabel="Archivar paciente"
                    form={patients.archive.form(header.id)}
                    destructive
                />
            )}
            {header.can.restore && (
                <Form {...patients.restore.form(header.id)}>
                    {({ processing }) => (
                        <Button type="submit" disabled={processing}>
                            <ArchiveRestore aria-hidden="true" />
                            Restaurar
                        </Button>
                    )}
                </Form>
            )}
        </>
    );

    return (
        <>
            <Head title={header.full_name} />
            <PatientLayout header={header} actions={actions}>
                {upcoming !== null && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0">
                            <CardTitle className="text-base">
                                <h2>Próximas citas</h2>
                            </CardTitle>
                            {can_book && (
                                <Button asChild size="sm">
                                    <Link href={agenda({ query: { paciente: header.id } })}>
                                        <CalendarPlus aria-hidden="true" />
                                        Agendar cita
                                    </Link>
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent>
                            {upcoming.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No tiene citas agendadas.
                                </p>
                            ) : (
                                <ul className="grid gap-1 text-sm">
                                    {upcoming.map((a) => (
                                        <li key={a.id} className="flex flex-wrap gap-x-3">
                                            <span className="font-medium tabular-nums">
                                                {formatLocalDateTime(a.starts_at)}
                                            </span>
                                            <span>{a.type}</span>
                                            <span className="text-muted-foreground">
                                                {a.professional} · {a.room} · {a.status_label}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                <h2>Contacto</h2>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field label="Teléfono" value={patient.phone} />
                                <Field label="Teléfono alternativo" value={patient.phone_alt} />
                                <Field label="Correo electrónico" value={patient.email} />
                                <Field label="Ciudad" value={patient.city} />
                                <Field label="Dirección" value={patient.address} />
                                <Field label="Ocupación" value={patient.occupation} />
                                <Field
                                    label="Comunicaciones"
                                    value={
                                        patient.accepts_communications
                                            ? 'Acepta recordatorios'
                                            : 'No acepta recordatorios'
                                    }
                                />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                <h2>Emergencia y cobertura</h2>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Contacto de emergencia"
                                    value={patient.emergency_contact_name}
                                />
                                <Field
                                    label="Teléfono de emergencia"
                                    value={patient.emergency_contact_phone}
                                />
                                <Field
                                    label="Parentesco"
                                    value={patient.emergency_contact_relationship}
                                />
                                <Field label="Obra social o prepaga" value={patient.insurer} />
                                <Field
                                    label="Número de afiliado"
                                    value={patient.insurance_number}
                                />
                            </dl>
                        </CardContent>
                    </Card>

                    {header.is_minor && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    <h2>Tutor legal</h2>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Nombre" value={patient.guardian_name} />
                                    <Field label="Documento" value={patient.guardian_document} />
                                    <Field label="Vínculo" value={labels.guardian_relationship} />
                                    <Field label="Teléfono" value={patient.guardian_phone} />
                                </dl>
                            </CardContent>
                        </Card>
                    )}

                    {patient.administrative_notes && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    <h2>Notas administrativas</h2>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm whitespace-pre-line">
                                    {patient.administrative_notes}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>
                <PortalAccessCard
                    patientId={header.id}
                    portal={portal}
                    defaultEmail={patient.email}
                />
                <p className="text-xs text-muted-foreground">
                    Registrado el {formatDate(patient.created_at)}.
                </p>
            </PatientLayout>
        </>
    );
}

PatientShow.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
