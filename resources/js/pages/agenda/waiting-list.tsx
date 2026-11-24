import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/native-select';
import { PageHeader } from '@/components/page-header';
import { PatientPicker } from '@/components/patient-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import { agenda } from '@/routes';
import waitingList from '@/routes/waiting-list';
import type { NamedOption, PatientOption } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { CalendarPlus, ListTodo } from 'lucide-react';
import { useState } from 'react';

type Entry = {
    id: number;
    patient: { id: number; name: string; phone: string | null };
    type: string | null;
    professional: string | null;
    preference: string | null;
    notes: string | null;
    status: string;
    status_label: string;
    created_at: string | null;
};

type Props = {
    entries: Entry[];
    show_all: boolean;
    types: NamedOption[];
    professionals: NamedOption[];
    can_manage: boolean;
    patient_options?: PatientOption[];
};

export default function WaitingList({
    entries,
    show_all,
    types,
    professionals,
    can_manage,
    patient_options,
}: Props) {
    const [patient, setPatient] = useState<PatientOption | null>(null);

    return (
        <>
            <Head title="Lista de espera" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Lista de espera"
                    description="Pacientes que esperan un turno. Al agendarlos desde aquí, salen de la lista automáticamente."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={waitingList.index({ query: show_all ? {} : { todos: 1 } })}>
                                {show_all ? 'Ver solo en espera' : 'Ver historial'}
                            </Link>
                        </Button>
                    }
                />

                {can_manage && (
                    <Card className="max-w-3xl">
                        <CardHeader>
                            <CardTitle className="text-base">
                                <h2>Agregar a la lista</h2>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...waitingList.store.form()}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setPatient(null)}
                                resetOnSuccess
                                className="grid gap-4 sm:grid-cols-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2 sm:col-span-2">
                                            <label
                                                htmlFor="espera-paciente"
                                                className="text-sm font-medium"
                                            >
                                                Paciente
                                                <span
                                                    className="text-destructive"
                                                    aria-hidden="true"
                                                >
                                                    {' '}
                                                    *
                                                </span>
                                            </label>
                                            <PatientPicker
                                                id="espera-paciente"
                                                value={patient}
                                                onChange={setPatient}
                                                options={patient_options}
                                                invalid={!!errors.patient_id}
                                            />
                                            <input
                                                type="hidden"
                                                name="patient_id"
                                                value={patient?.id ?? ''}
                                            />
                                            <InputError message={errors.patient_id} />
                                        </div>
                                        <FormField
                                            id="espera-tipo"
                                            label="Tipo de cita"
                                            error={errors.appointment_type_id}
                                        >
                                            {(p) => (
                                                <NativeSelect
                                                    {...p}
                                                    name="appointment_type_id"
                                                    defaultValue=""
                                                >
                                                    <option value="">Cualquiera</option>
                                                    {types.map((t) => (
                                                        <option key={t.id} value={t.id}>
                                                            {t.name}
                                                        </option>
                                                    ))}
                                                </NativeSelect>
                                            )}
                                        </FormField>
                                        <FormField
                                            id="espera-profesional"
                                            label="Profesional"
                                            error={errors.professional_id}
                                        >
                                            {(p) => (
                                                <NativeSelect
                                                    {...p}
                                                    name="professional_id"
                                                    defaultValue=""
                                                >
                                                    <option value="">Cualquiera</option>
                                                    {professionals.map((t) => (
                                                        <option key={t.id} value={t.id}>
                                                            {t.name}
                                                        </option>
                                                    ))}
                                                </NativeSelect>
                                            )}
                                        </FormField>
                                        <FormField
                                            id="espera-preferencia"
                                            label="Preferencia de horario"
                                            error={errors.preference}
                                        >
                                            {(p) => (
                                                <Input
                                                    {...p}
                                                    name="preference"
                                                    placeholder="Ej.: martes por la tarde"
                                                />
                                            )}
                                        </FormField>
                                        <FormField
                                            id="espera-notas"
                                            label="Notas"
                                            error={errors.notes}
                                        >
                                            {(p) => <Input {...p} name="notes" />}
                                        </FormField>
                                        <div className="sm:col-span-2">
                                            <Button type="submit" disabled={processing || !patient}>
                                                {processing && <Spinner />}
                                                Agregar
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {entries.length === 0 ? (
                    <EmptyState
                        icon={ListTodo}
                        title={
                            show_all ? 'La lista está vacía' : 'No hay pacientes esperando turno'
                        }
                    />
                ) : (
                    <ul className="grid gap-3">
                        {entries.map((e, i) => (
                            <li
                                key={e.id}
                                className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="grid gap-0.5">
                                    <p className="font-medium">
                                        <span className="mr-2 text-muted-foreground tabular-nums">
                                            {i + 1}.
                                        </span>
                                        {e.patient.name}
                                        {e.status !== 'en_espera' && (
                                            <Badge variant="secondary" className="ml-2">
                                                {e.status_label}
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {[
                                            e.type ?? 'Cualquier tipo',
                                            e.professional ?? 'Cualquier profesional',
                                            e.preference,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Desde {formatDate(e.created_at)}
                                        {e.patient.phone && ` · ${e.patient.phone}`}
                                        {e.notes && ` · ${e.notes}`}
                                    </p>
                                </div>
                                {can_manage && e.status === 'en_espera' && (
                                    <div className="flex gap-2">
                                        <Button asChild size="sm">
                                            <Link href={agenda({ query: { espera: e.id } })}>
                                                <CalendarPlus aria-hidden="true" />
                                                Agendar
                                            </Link>
                                        </Button>
                                        <ConfirmDialog
                                            trigger={
                                                <Button size="sm" variant="ghost">
                                                    Quitar
                                                </Button>
                                            }
                                            title={`¿Quitar a ${e.patient.name} de la lista?`}
                                            description="Queda en el historial como descartado."
                                            confirmLabel="Quitar"
                                            form={waitingList.discard.form(e.id)}
                                        />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

WaitingList.layout = {
    breadcrumbs: [
        { title: 'Agenda', href: agenda() },
        { title: 'Lista de espera', href: waitingList.index() },
    ],
};
