import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/native-select';
import { PatientLayout } from '@/components/patients/patient-layout';
import { Textarea } from '@/components/textarea';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatLocalDateTime } from '@/lib/format';
import { surfaceLabel, SURFACES } from '@/lib/odontogram';
import patients from '@/routes/patients';
import type { PatientHeader } from '@/types';
import { Form, Head } from '@inertiajs/react';
import { AlertTriangle, NotebookPen, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type ConsultationRow = {
    id: number;
    consulted_at: string;
    professional: string;
    reason: string;
    subjective: string | null;
    objective: string | null;
    assessment: string | null;
    plan: string | null;
    materials: string | null;
    observations: string | null;
    next_visit: string | null;
    next_visit_on: string | null;
    anesthesia_used: boolean;
    allergies_acknowledged_at: string | null;
    procedures: {
        name: string;
        tooth_code: number | null;
        surface: string | null;
        notes: string | null;
        from_plan: boolean;
    }[];
    addenda: { id: number; body: string; author: string; created_at: string }[];
    can_add_addendum: boolean;
};

type Props = {
    header: PatientHeader;
    consultations: ConsultationRow[];
    options: {
        procedures: {
            id: number;
            name: string;
            requires_anesthesia: boolean;
            tooth_condition: string | null;
        }[];
        pending_items: { id: number; label: string; requires_anesthesia: boolean }[];
        appointments: { id: number; label: string }[];
    };
    allergies: string[];
    can_record: boolean;
};

type Row = {
    key: number;
    source: 'plan' | 'catalogo';
    procedure_id: string;
    treatment_item_id: string;
    tooth_code: string;
    surface: string;
    notes: string;
};

const SOAP: {
    key: 'subjective' | 'objective' | 'assessment' | 'plan';
    label: string;
    hint: string;
}[] = [
    {
        key: 'subjective',
        label: 'S — Subjetivo',
        hint: 'Lo que refiere el paciente: síntomas, dolor, antecedentes del motivo.',
    },
    {
        key: 'objective',
        label: 'O — Objetivo',
        hint: 'Hallazgos del examen clínico y radiográfico.',
    },
    { key: 'assessment', label: 'A — Evaluación', hint: 'Diagnóstico o impresión clínica.' },
    { key: 'plan', label: 'P — Plan', hint: 'Tratamiento indicado y próximos pasos.' },
];

const nowLocal = () => {
    const d = new Date();
    const date = d.toLocaleDateString('en-CA', { timeZone: 'America/Asuncion' });
    const time = d.toLocaleTimeString('en-GB', {
        timeZone: 'America/Asuncion',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });

    return `${date}T${time}`;
};

let nextKey = 1;
const emptyRow = (source: Row['source']): Row => ({
    key: nextKey++,
    source,
    procedure_id: '',
    treatment_item_id: '',
    tooth_code: '',
    surface: '',
    notes: '',
});

function NewConsultation({ props, onDone }: { props: Props; onDone: () => void }) {
    const { header, options, allergies } = props;
    const [rows, setRows] = useState<Row[]>([]);
    const [anesthesia, setAnesthesia] = useState(false);
    const [acknowledged, setAcknowledged] = useState(false);

    const update = (key: number, changes: Partial<Row>) =>
        setRows((all) => all.map((r) => (r.key === key ? { ...r, ...changes } : r)));
    const rowNeedsAnesthesia = rows.some((r) =>
        r.source === 'plan'
            ? options.pending_items.find((i) => String(i.id) === r.treatment_item_id)
                  ?.requires_anesthesia
            : options.procedures.find((p) => String(p.id) === r.procedure_id)?.requires_anesthesia,
    );
    const usesAnesthesia = anesthesia || !!rowNeedsAnesthesia;
    const mustAcknowledge = usesAnesthesia && allergies.length > 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    <h2>Nueva consulta</h2>
                </CardTitle>
                <CardDescription>
                    Una vez guardada no se modifica: las aclaraciones posteriores se agregan como
                    adendas. Los procedimientos con pieza actualizan el odontograma y los vinculados
                    a un plan quedan completados.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    {...patients.consultations.store.form(header.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={onDone}
                    className="grid gap-4"
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
                            <div className="grid gap-3 sm:grid-cols-3">
                                <FormField
                                    id="consulta-fecha"
                                    label="Fecha y hora"
                                    error={errors.consulted_at}
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            type="datetime-local"
                                            name="consulted_at"
                                            defaultValue={nowLocal()}
                                            max={nowLocal()}
                                        />
                                    )}
                                </FormField>
                                {options.appointments.length > 0 && (
                                    <FormField
                                        id="consulta-cita"
                                        label="Cita"
                                        error={errors.appointment_id}
                                    >
                                        {(p) => (
                                            <NativeSelect
                                                {...p}
                                                name="appointment_id"
                                                defaultValue={options.appointments[0].id}
                                            >
                                                <option value="">Sin cita</option>
                                                {options.appointments.map((a) => (
                                                    <option key={a.id} value={a.id}>
                                                        {a.label}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        )}
                                    </FormField>
                                )}
                            </div>
                            <FormField
                                id="consulta-motivo"
                                label="Motivo de consulta"
                                error={errors.reason}
                                required
                            >
                                {(p) => (
                                    <Input
                                        {...p}
                                        name="reason"
                                        placeholder="Ej.: dolor en molar inferior derecho"
                                    />
                                )}
                            </FormField>

                            <fieldset className="grid gap-3 sm:grid-cols-2">
                                <legend className="mb-2 text-sm font-medium">
                                    Evolución (SOAP) — se guarda cifrada
                                </legend>
                                {SOAP.map((s) => (
                                    <FormField
                                        key={s.key}
                                        id={`consulta-${s.key}`}
                                        label={s.label}
                                        hint={s.hint}
                                        error={errors[s.key]}
                                    >
                                        {(p) => <Textarea {...p} name={s.key} rows={3} />}
                                    </FormField>
                                ))}
                            </fieldset>

                            <fieldset className="grid gap-2">
                                <legend className="mb-1 text-sm font-medium">
                                    Procedimientos realizados
                                </legend>
                                {rows.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Ninguno todavía.
                                    </p>
                                )}
                                {rows.map((r, i) => {
                                    const err = (field: string) =>
                                        errors[`procedures.${i}.${field}` as keyof typeof errors];

                                    return (
                                        <div
                                            key={r.key}
                                            className="grid gap-2 rounded-md border p-2 sm:grid-cols-[2fr_6rem_8rem_2fr_auto] sm:items-end"
                                        >
                                            <input
                                                type="hidden"
                                                name={`procedures[${i}][procedure_id]`}
                                                value={
                                                    r.source === 'catalogo' ? r.procedure_id : ''
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name={`procedures[${i}][treatment_item_id]`}
                                                value={
                                                    r.source === 'plan' ? r.treatment_item_id : ''
                                                }
                                            />
                                            {r.source === 'plan' ? (
                                                <FormField
                                                    id={`proc-${r.key}-item`}
                                                    label="Tratamiento del plan"
                                                    error={err('treatment_item_id')}
                                                    required
                                                >
                                                    {(p) => (
                                                        <NativeSelect
                                                            {...p}
                                                            value={r.treatment_item_id}
                                                            onChange={(e) =>
                                                                update(r.key, {
                                                                    treatment_item_id:
                                                                        e.target.value,
                                                                })
                                                            }
                                                        >
                                                            <option value="" disabled>
                                                                Seleccione…
                                                            </option>
                                                            {options.pending_items.map((o) => (
                                                                <option key={o.id} value={o.id}>
                                                                    {o.label}
                                                                </option>
                                                            ))}
                                                        </NativeSelect>
                                                    )}
                                                </FormField>
                                            ) : (
                                                <FormField
                                                    id={`proc-${r.key}-proc`}
                                                    label="Procedimiento"
                                                    error={err('procedure_id')}
                                                    required
                                                >
                                                    {(p) => (
                                                        <NativeSelect
                                                            {...p}
                                                            value={r.procedure_id}
                                                            onChange={(e) =>
                                                                update(r.key, {
                                                                    procedure_id: e.target.value,
                                                                })
                                                            }
                                                        >
                                                            <option value="" disabled>
                                                                Seleccione…
                                                            </option>
                                                            {options.procedures.map((o) => (
                                                                <option key={o.id} value={o.id}>
                                                                    {o.name}
                                                                </option>
                                                            ))}
                                                        </NativeSelect>
                                                    )}
                                                </FormField>
                                            )}
                                            <FormField
                                                id={`proc-${r.key}-tooth`}
                                                label="Diente"
                                                error={err('tooth_code')}
                                            >
                                                {(p) => (
                                                    <Input
                                                        {...p}
                                                        name={`procedures[${i}][tooth_code]`}
                                                        inputMode="numeric"
                                                        value={r.tooth_code}
                                                        onChange={(e) =>
                                                            update(r.key, {
                                                                tooth_code: e.target.value
                                                                    .replace(/\D/g, '')
                                                                    .slice(0, 2),
                                                            })
                                                        }
                                                    />
                                                )}
                                            </FormField>
                                            <FormField
                                                id={`proc-${r.key}-surface`}
                                                label="Superficie"
                                                error={err('surface')}
                                            >
                                                {(p) => (
                                                    <NativeSelect
                                                        {...p}
                                                        name={`procedures[${i}][surface]`}
                                                        value={r.surface}
                                                        onChange={(e) =>
                                                            update(r.key, {
                                                                surface: e.target.value,
                                                            })
                                                        }
                                                    >
                                                        <option value="">—</option>
                                                        {SURFACES.map((s) => (
                                                            <option key={s} value={s}>
                                                                {surfaceLabel(
                                                                    r.tooth_code
                                                                        ? Number(r.tooth_code)
                                                                        : null,
                                                                    s,
                                                                )}
                                                            </option>
                                                        ))}
                                                    </NativeSelect>
                                                )}
                                            </FormField>
                                            <FormField
                                                id={`proc-${r.key}-notes`}
                                                label="Notas"
                                                error={err('notes')}
                                            >
                                                {(p) => (
                                                    <Input
                                                        {...p}
                                                        name={`procedures[${i}][notes]`}
                                                        value={r.notes}
                                                        onChange={(e) =>
                                                            update(r.key, { notes: e.target.value })
                                                        }
                                                    />
                                                )}
                                            </FormField>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                onClick={() =>
                                                    setRows((all) =>
                                                        all.filter((x) => x.key !== r.key),
                                                    )
                                                }
                                                aria-label={`Quitar procedimiento ${i + 1}`}
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        </div>
                                    );
                                })}
                                <div className="flex flex-wrap gap-2">
                                    {options.pending_items.length > 0 && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setRows((all) => [...all, emptyRow('plan')])
                                            }
                                        >
                                            <Plus aria-hidden="true" />
                                            Del plan de tratamiento
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setRows((all) => [...all, emptyRow('catalogo')])
                                        }
                                    >
                                        <Plus aria-hidden="true" />
                                        Otro procedimiento
                                    </Button>
                                </div>
                            </fieldset>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <FormField
                                    id="consulta-materiales"
                                    label="Materiales usados"
                                    error={errors.materials}
                                >
                                    {(p) => (
                                        <Textarea
                                            {...p}
                                            name="materials"
                                            rows={2}
                                            placeholder="Ej.: resina A2, lidocaína 2% 1 carpule"
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="consulta-observaciones"
                                    label="Observaciones"
                                    error={errors.observations}
                                >
                                    {(p) => <Textarea {...p} name="observations" rows={2} />}
                                </FormField>
                                <FormField
                                    id="consulta-proxima"
                                    label="Próxima visita sugerida"
                                    error={errors.next_visit}
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            name="next_visit"
                                            placeholder="Ej.: control en 15 días"
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="consulta-proxima-fecha"
                                    label="Fecha sugerida"
                                    error={errors.next_visit_on}
                                >
                                    {(p) => <Input {...p} type="date" name="next_visit_on" />}
                                </FormField>
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="consulta-anestesia"
                                        checked={usesAnesthesia}
                                        disabled={!!rowNeedsAnesthesia}
                                        onCheckedChange={(c) => setAnesthesia(c === true)}
                                    />
                                    <Label htmlFor="consulta-anestesia">Se aplicó anestesia</Label>
                                    {rowNeedsAnesthesia && (
                                        <span className="text-xs text-muted-foreground">
                                            (un procedimiento la requiere)
                                        </span>
                                    )}
                                </div>
                                <input
                                    type="hidden"
                                    name="anesthesia_used"
                                    value={usesAnesthesia ? '1' : '0'}
                                />
                            </div>

                            {mustAcknowledge && (
                                <div
                                    role="alert"
                                    className="grid gap-2 rounded-lg border-2 border-red-600 bg-red-50 p-3 text-red-950 dark:bg-red-950/40 dark:text-red-50"
                                >
                                    <p className="flex items-center gap-2 font-semibold">
                                        <AlertTriangle className="size-4" aria-hidden="true" />
                                        Paciente con alergias: {allergies.join(', ')}
                                    </p>
                                    <div className="flex items-start gap-2">
                                        <Checkbox
                                            id="consulta-alergias"
                                            checked={acknowledged}
                                            onCheckedChange={(c) => setAcknowledged(c === true)}
                                            className="mt-0.5 bg-white"
                                        />
                                        <Label htmlFor="consulta-alergias" className="leading-snug">
                                            Revisé las alergias del paciente antes de aplicar la
                                            anestesia.
                                        </Label>
                                    </div>
                                    <InputError message={errors.allergies_acknowledged} />
                                </div>
                            )}
                            <input
                                type="hidden"
                                name="allergies_acknowledged"
                                value={mustAcknowledge && acknowledged ? '1' : '0'}
                            />

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing || (mustAcknowledge && !acknowledged)}
                                >
                                    {processing && <Spinner />}
                                    Guardar consulta
                                </Button>
                                <Button type="button" variant="ghost" onClick={onDone}>
                                    Cancelar
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function ConsultationCard({ c, headerId }: { c: ConsultationRow; headerId: number }) {
    const [adding, setAdding] = useState(false);
    const fields = [
        ...SOAP.map((s) => ({ label: s.label, value: c[s.key] })),
        { label: 'Materiales', value: c.materials },
        { label: 'Observaciones', value: c.observations },
    ].filter((f) => f.value);

    return (
        <li>
            <Card>
                <CardHeader>
                    <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                        <h3>{c.reason}</h3>
                        {c.anesthesia_used && <Badge variant="outline">Con anestesia</Badge>}
                    </CardTitle>
                    <CardDescription>
                        {formatLocalDateTime(c.consulted_at)} · {c.professional}
                        {c.allergies_acknowledged_at &&
                            ` · alergias revisadas ${formatLocalDateTime(c.allergies_acknowledged_at)}`}
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 text-sm">
                    {fields.length > 0 && (
                        <dl className="grid gap-2 sm:grid-cols-2">
                            {fields.map((f) => (
                                <div key={f.label}>
                                    <dt className="text-xs text-muted-foreground">{f.label}</dt>
                                    <dd className="whitespace-pre-line">{f.value}</dd>
                                </div>
                            ))}
                        </dl>
                    )}
                    {c.procedures.length > 0 && (
                        <div>
                            <p className="text-xs text-muted-foreground">Procedimientos</p>
                            <ul className="list-inside list-disc">
                                {c.procedures.map((p, i) => (
                                    <li key={i}>
                                        {p.name}
                                        {p.tooth_code && ` — diente ${p.tooth_code}`}
                                        {p.surface && ` ${p.surface}`}
                                        {p.from_plan && ' (del plan)'}
                                        {p.notes && `: ${p.notes}`}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {(c.next_visit || c.next_visit_on) && (
                        <p>
                            <span className="text-muted-foreground">Próxima visita: </span>
                            {c.next_visit}
                            {c.next_visit_on && ` (${formatDate(c.next_visit_on)})`}
                        </p>
                    )}
                    {c.addenda.length > 0 && (
                        <div className="grid gap-2 border-t pt-2">
                            {c.addenda.map((a) => (
                                <div key={a.id} className="rounded-md bg-muted/40 px-3 py-2">
                                    <p className="text-xs text-muted-foreground">
                                        Adenda · {a.author} · {formatLocalDateTime(a.created_at)}
                                    </p>
                                    <p className="whitespace-pre-line">{a.body}</p>
                                </div>
                            ))}
                        </div>
                    )}
                    {c.can_add_addendum &&
                        (adding ? (
                            <Form
                                {...patients.consultations.addenda.store.form({
                                    patient: headerId,
                                    consultation: c.id,
                                })}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setAdding(false)}
                                className="grid gap-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <FormField
                                            id={`adenda-${c.id}`}
                                            label="Adenda"
                                            error={errors.body}
                                            required
                                        >
                                            {(p) => <Textarea {...p} name="body" rows={2} />}
                                        </FormField>
                                        <div className="flex gap-2">
                                            <Button type="submit" size="sm" disabled={processing}>
                                                Agregar adenda
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => setAdding(false)}
                                            >
                                                Cancelar
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <div>
                                <Button size="sm" variant="ghost" onClick={() => setAdding(true)}>
                                    <NotebookPen aria-hidden="true" />
                                    Agregar adenda
                                </Button>
                            </div>
                        ))}
                </CardContent>
            </Card>
        </li>
    );
}

export default function Consultations(props: Props) {
    const { header, consultations, can_record } = props;
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={`Consultas — ${header.full_name}`} />
            <PatientLayout
                header={header}
                actions={
                    can_record &&
                    !creating && (
                        <Button onClick={() => setCreating(true)}>
                            <Plus aria-hidden="true" />
                            Nueva consulta
                        </Button>
                    )
                }
            >
                {creating && <NewConsultation props={props} onDone={() => setCreating(false)} />}
                {consultations.length === 0 ? (
                    <EmptyState icon={NotebookPen} title="Sin consultas registradas" />
                ) : (
                    <ol
                        className="grid gap-4"
                        aria-label="Consultas, de la más reciente a la más antigua"
                    >
                        {consultations.map((c) => (
                            <ConsultationCard key={c.id} c={c} headerId={header.id} />
                        ))}
                    </ol>
                )}
            </PatientLayout>
        </>
    );
}

Consultations.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
