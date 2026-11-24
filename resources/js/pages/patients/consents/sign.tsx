import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/native-select';
import { PatientLayout } from '@/components/patients/patient-layout';
import { SignaturePad } from '@/components/signature-pad';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import patients from '@/routes/patients';
import type { Option, PatientHeader } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';

type Template = { id: number; title: string; version: number; body: string };

type Props = {
    header: PatientHeader;
    templates: Template[];
    defaults: { signer_name: string; signer_document: string; signer_relationship: string };
    relationships: Option[];
    preselected: number | null;
};

export default function SignConsent({
    header,
    templates,
    defaults,
    relationships,
    preselected,
}: Props) {
    const [templateId, setTemplateId] = useState<number | null>(
        preselected ?? templates[0]?.id ?? null,
    );
    const [signature, setSignature] = useState<string | null>(null);
    const [accepted, setAccepted] = useState(false);
    const template = templates.find((t) => t.id === templateId) ?? null;

    return (
        <>
            <Head title={`Firmar consentimiento — ${header.full_name}`} />
            <PatientLayout header={header}>
                {templates.length === 0 ? (
                    <p className="rounded-md border px-3 py-2 text-sm">
                        No hay plantillas de consentimiento activas. Un administrador debe cargarlas
                        en Administración › Consentimientos.
                    </p>
                ) : (
                    <Form
                        {...patients.consents.store.form(header.id)}
                        className="grid max-w-3xl gap-6"
                        options={{ preserveScroll: true }}
                    >
                        {({ errors, processing, hasErrors }) => (
                            <>
                                {hasErrors && (
                                    <p
                                        role="alert"
                                        className="rounded-md border border-destructive px-3 py-2 text-sm text-destructive"
                                    >
                                        No se pudo guardar. Revise los campos marcados.
                                    </p>
                                )}

                                <FormField
                                    id="consent_id"
                                    label="Consentimiento"
                                    error={errors.consent_id}
                                    required
                                >
                                    {(p) => (
                                        <NativeSelect
                                            {...p}
                                            name="consent_id"
                                            value={templateId ?? ''}
                                            onChange={(e) => setTemplateId(Number(e.target.value))}
                                        >
                                            {templates.map((t) => (
                                                <option key={t.id} value={t.id}>
                                                    {t.title} (v{t.version})
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    )}
                                </FormField>

                                {template && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-base">
                                                <h2>{template.title}</h2>
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div
                                                tabIndex={0}
                                                role="region"
                                                aria-label="Texto del consentimiento"
                                                className="max-h-96 overflow-y-auto rounded-md bg-muted/40 p-4 text-sm leading-relaxed whitespace-pre-line outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                {template.body}
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}

                                <fieldset className="grid gap-4 sm:grid-cols-3">
                                    <legend className="mb-2 text-sm font-medium">
                                        Quién firma
                                    </legend>
                                    <FormField
                                        id="signer_relationship"
                                        label="Vínculo"
                                        error={errors.signer_relationship}
                                        required
                                    >
                                        {(p) => (
                                            <NativeSelect
                                                {...p}
                                                name="signer_relationship"
                                                defaultValue={defaults.signer_relationship}
                                            >
                                                {relationships
                                                    .filter(
                                                        (r) =>
                                                            !(
                                                                header.is_minor &&
                                                                r.value === 'paciente'
                                                            ),
                                                    )
                                                    .map((r) => (
                                                        <option key={r.value} value={r.value}>
                                                            {r.label}
                                                        </option>
                                                    ))}
                                            </NativeSelect>
                                        )}
                                    </FormField>
                                    <FormField
                                        id="signer_name"
                                        label="Nombre completo"
                                        error={errors.signer_name}
                                        required
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name="signer_name"
                                                defaultValue={defaults.signer_name}
                                            />
                                        )}
                                    </FormField>
                                    <FormField
                                        id="signer_document"
                                        label="Documento"
                                        error={errors.signer_document}
                                        required
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name="signer_document"
                                                defaultValue={defaults.signer_document}
                                            />
                                        )}
                                    </FormField>
                                </fieldset>

                                <div className="grid gap-2">
                                    <p id="firma-instrucciones" className="text-sm font-medium">
                                        Firma
                                        <span className="text-destructive" aria-hidden="true">
                                            {' '}
                                            *
                                        </span>
                                    </p>
                                    <SignaturePad
                                        onChange={setSignature}
                                        invalid={!!errors.signature}
                                        describedBy="firma-instrucciones firma-error"
                                    />
                                    <input type="hidden" name="signature" value={signature ?? ''} />
                                    <InputError id="firma-error" message={errors.signature} />
                                </div>

                                <div className="grid gap-1">
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            id="accepted"
                                            checked={accepted}
                                            onCheckedChange={(c) => setAccepted(c === true)}
                                        />
                                        <Label htmlFor="accepted" className="leading-snug">
                                            Confirmo que quien firma leyó el texto, se le explicó en
                                            lenguaje claro y pudo hacer preguntas.
                                        </Label>
                                    </div>
                                    <input
                                        type="hidden"
                                        name="accepted"
                                        value={accepted ? '1' : '0'}
                                    />
                                    <InputError message={errors.accepted} />
                                </div>

                                <div className="flex flex-wrap gap-3">
                                    <Button
                                        type="submit"
                                        disabled={processing || !signature || !accepted}
                                    >
                                        {processing && <Spinner />}
                                        Guardar consentimiento firmado
                                    </Button>
                                    <Button asChild variant="ghost">
                                        <Link href={patients.consents.index(header.id)}>
                                            Cancelar
                                        </Link>
                                    </Button>
                                </div>
                                {(!signature || !accepted) && (
                                    <p className="text-sm text-muted-foreground">
                                        Para guardar, falta {!signature ? 'la firma' : ''}
                                        {!signature && !accepted ? ' y ' : ''}
                                        {!accepted ? 'la confirmación de lectura' : ''}.
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                )}
            </PatientLayout>
        </>
    );
}

SignConsent.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
