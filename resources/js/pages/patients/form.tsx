import { FormField } from '@/components/form-field';
import { NativeSelect } from '@/components/native-select';
import { PageHeader } from '@/components/page-header';
import { Textarea } from '@/components/textarea';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import patients from '@/routes/patients';
import type { Option, PatientDetails } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';

type Props = {
    patient: PatientDetails | null;
    options: { document_types: Option[]; sexes: Option[]; guardian_relationships: Option[] };
};

function ageFrom(birth: string): number | null {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(birth)) {
        return null;
    }

    const [y, m, d] = birth.split('-').map(Number);
    const today = new Date();
    let age = today.getFullYear() - y;

    if (today.getMonth() + 1 < m || (today.getMonth() + 1 === m && today.getDate() < d)) {
        age--;
    }

    return age;
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    <h2>{title}</h2>
                </CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent className="grid gap-4 sm:grid-cols-2">{children}</CardContent>
        </Card>
    );
}

export default function PatientForm({ patient, options }: Props) {
    const isEdit = patient !== null;
    const [birthDate, setBirthDate] = useState(patient?.birth_date ?? '');
    const [acceptsCommunications, setAcceptsCommunications] = useState(
        patient?.accepts_communications ?? false,
    );
    const age = ageFrom(birthDate);
    const isMinor = age !== null && age < 18;
    const title = isEdit ? `Editar a ${patient.first_name} ${patient.last_name}` : 'Nuevo paciente';
    const today = new Date().toISOString().slice(0, 10);

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description="Los campos marcados con * son obligatorios."
                />

                <Form
                    {...(isEdit ? patients.update.form(patient.id) : patients.store.form())}
                    className="grid max-w-4xl gap-6"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing, hasErrors }) => (
                        <>
                            {hasErrors && (
                                <p
                                    role="alert"
                                    className="rounded-md border border-destructive px-3 py-2 text-sm text-destructive"
                                >
                                    Revise los campos marcados en rojo.
                                </p>
                            )}

                            <Section title="Identificación">
                                <FormField
                                    id="first_name"
                                    label="Nombres"
                                    error={errors.first_name}
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="first_name"
                                            defaultValue={patient?.first_name}
                                            autoComplete="off"
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="last_name"
                                    label="Apellidos"
                                    error={errors.last_name}
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="last_name"
                                            defaultValue={patient?.last_name}
                                            autoComplete="off"
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="document_type"
                                    label="Tipo de documento"
                                    error={errors.document_type}
                                    required
                                >
                                    {(p) => (
                                        <NativeSelect
                                            {...p}
                                            name="document_type"
                                            defaultValue={patient?.document_type ?? 'ci'}
                                        >
                                            {options.document_types.map((o) => (
                                                <option key={o.value} value={o.value}>
                                                    {o.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    )}
                                </FormField>
                                <FormField
                                    id="document_number"
                                    label="Número de documento"
                                    error={errors.document_number}
                                    hint="La cédula puede escribirse con o sin puntos."
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="document_number"
                                            defaultValue={patient?.document_number}
                                            inputMode="text"
                                            autoComplete="off"
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="birth_date"
                                    label="Fecha de nacimiento"
                                    error={errors.birth_date}
                                    hint={
                                        age !== null
                                            ? `${age} años${isMinor ? ' — menor de edad' : ''}`
                                            : undefined
                                    }
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="birth_date"
                                            type="date"
                                            max={today}
                                            value={birthDate}
                                            onChange={(e) => setBirthDate(e.target.value)}
                                        />
                                    )}
                                </FormField>
                                <FormField id="sex" label="Sexo" error={errors.sex} required>
                                    {(p) => (
                                        <NativeSelect
                                            {...p}
                                            name="sex"
                                            defaultValue={patient?.sex ?? ''}
                                        >
                                            <option value="" disabled>
                                                Seleccione…
                                            </option>
                                            {options.sexes.map((o) => (
                                                <option key={o.value} value={o.value}>
                                                    {o.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            </Section>

                            {isMinor && (
                                <Section
                                    title="Tutor legal"
                                    description="El paciente es menor de edad: el tutor firma los consentimientos."
                                >
                                    <FormField
                                        id="guardian_name"
                                        label="Nombre del tutor"
                                        error={errors.guardian_name}
                                        required
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name="guardian_name"
                                                defaultValue={patient?.guardian_name ?? ''}
                                            />
                                        )}
                                    </FormField>
                                    <FormField
                                        id="guardian_document"
                                        label="Documento del tutor"
                                        error={errors.guardian_document}
                                        required
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name="guardian_document"
                                                defaultValue={patient?.guardian_document ?? ''}
                                            />
                                        )}
                                    </FormField>
                                    <FormField
                                        id="guardian_relationship"
                                        label="Vínculo"
                                        error={errors.guardian_relationship}
                                        required
                                    >
                                        {(p) => (
                                            <NativeSelect
                                                {...p}
                                                name="guardian_relationship"
                                                defaultValue={patient?.guardian_relationship ?? ''}
                                            >
                                                <option value="" disabled>
                                                    Seleccione…
                                                </option>
                                                {options.guardian_relationships.map((o) => (
                                                    <option key={o.value} value={o.value}>
                                                        {o.label}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        )}
                                    </FormField>
                                    <FormField
                                        id="guardian_phone"
                                        label="Teléfono del tutor"
                                        error={errors.guardian_phone}
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name="guardian_phone"
                                                type="tel"
                                                defaultValue={patient?.guardian_phone ?? ''}
                                            />
                                        )}
                                    </FormField>
                                </Section>
                            )}

                            <Section title="Contacto">
                                <FormField
                                    id="phone"
                                    label="Teléfono"
                                    error={errors.phone}
                                    hint="Ej.: 0981 123 456"
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="phone"
                                            type="tel"
                                            defaultValue={patient?.phone ?? ''}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="phone_alt"
                                    label="Teléfono alternativo"
                                    error={errors.phone_alt}
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="phone_alt"
                                            type="tel"
                                            defaultValue={patient?.phone_alt ?? ''}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="email"
                                    label="Correo electrónico"
                                    error={errors.email}
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="email"
                                            type="email"
                                            defaultValue={patient?.email ?? ''}
                                        />
                                    )}
                                </FormField>
                                <FormField id="city" label="Ciudad" error={errors.city}>
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="city"
                                            defaultValue={patient?.city ?? ''}
                                        />
                                    )}
                                </FormField>
                                <FormField id="address" label="Dirección" error={errors.address}>
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="address"
                                            defaultValue={patient?.address ?? ''}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="occupation"
                                    label="Ocupación"
                                    error={errors.occupation}
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="occupation"
                                            defaultValue={patient?.occupation ?? ''}
                                        />
                                    )}
                                </FormField>
                                <div className="flex items-start gap-3 sm:col-span-2">
                                    <Checkbox
                                        id="accepts_communications"
                                        checked={acceptsCommunications}
                                        onCheckedChange={(c) =>
                                            setAcceptsCommunications(c === true)
                                        }
                                        aria-describedby="accepts_communications-ayuda"
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="accepts_communications">
                                            Acepta recibir recordatorios y comunicaciones
                                        </Label>
                                        <p
                                            id="accepts_communications-ayuda"
                                            className="text-xs text-muted-foreground"
                                        >
                                            Necesario para enviarle recordatorios de citas,
                                            controles y saludos.
                                        </p>
                                    </div>
                                    <input
                                        type="hidden"
                                        name="accepts_communications"
                                        value={acceptsCommunications ? '1' : '0'}
                                    />
                                </div>
                            </Section>

                            <Section title="Contacto de emergencia">
                                <FormField
                                    id="emergency_contact_name"
                                    label="Nombre"
                                    error={errors.emergency_contact_name}
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="emergency_contact_name"
                                            defaultValue={patient?.emergency_contact_name ?? ''}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="emergency_contact_phone"
                                    label="Teléfono"
                                    error={errors.emergency_contact_phone}
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="emergency_contact_phone"
                                            type="tel"
                                            defaultValue={patient?.emergency_contact_phone ?? ''}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="emergency_contact_relationship"
                                    label="Parentesco"
                                    error={errors.emergency_contact_relationship}
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="emergency_contact_relationship"
                                            defaultValue={
                                                patient?.emergency_contact_relationship ?? ''
                                            }
                                        />
                                    )}
                                </FormField>
                            </Section>

                            <Section
                                title="Cobertura"
                                description="Obra social o prepaga (solo como dato de referencia)."
                            >
                                <FormField
                                    id="insurer"
                                    label="Obra social o prepaga"
                                    error={errors.insurer}
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="insurer"
                                            defaultValue={patient?.insurer ?? ''}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="insurance_number"
                                    label="Número de afiliado"
                                    error={errors.insurance_number}
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="insurance_number"
                                            defaultValue={patient?.insurance_number ?? ''}
                                        />
                                    )}
                                </FormField>
                                <div className="sm:col-span-2">
                                    <FormField
                                        id="administrative_notes"
                                        label="Notas administrativas"
                                        error={errors.administrative_notes}
                                        hint="No registre aquí información clínica: use la ficha clínica."
                                    >
                                        {(p) => (
                                            <Textarea
                                                {...p}
                                                name="administrative_notes"
                                                rows={3}
                                                defaultValue={patient?.administrative_notes ?? ''}
                                            />
                                        )}
                                    </FormField>
                                </div>
                            </Section>

                            <div className="flex flex-wrap gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {isEdit ? 'Guardar cambios' : 'Registrar paciente'}
                                </Button>
                                <Button asChild variant="ghost">
                                    <Link
                                        href={isEdit ? patients.show(patient.id) : patients.index()}
                                    >
                                        Cancelar
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PatientForm.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
