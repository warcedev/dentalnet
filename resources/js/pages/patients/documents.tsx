import { ConfirmDialog } from '@/components/confirm-dialog';
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
import { todayLocal } from '@/lib/agenda';
import { formatDate, formatLocalDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import patients from '@/routes/patients';
import type { Option, PatientHeader } from '@/types';
import { Form, Head } from '@inertiajs/react';
import {
    AlertTriangle,
    FileImage,
    FileText,
    FlaskConical,
    Pill,
    Plus,
    Trash2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';

type DocumentRow = {
    id: number;
    title: string;
    category: string;
    description: string | null;
    tooth_code: number | null;
    taken_on: string | null;
    is_image: boolean;
    size: number;
    uploaded_by: string;
    created_at: string | null;
    url: string;
};

type LabOrderRow = {
    id: number;
    laboratory: string;
    work_type: string;
    teeth: string | null;
    shade: string | null;
    instructions: string | null;
    sent_on: string;
    due_on: string;
    received_on: string | null;
    delivered_on: string | null;
    status: string;
    status_label: string;
    overdue: boolean;
    transitions: { value: string; label: string }[];
};

type PrescriptionRow = {
    id: number;
    type: string;
    issued_at: string;
    professional: string;
    summary: string;
    pdf_url: string;
};

type Props = {
    header: PatientHeader;
    documents: DocumentRow[] | null;
    lab_orders: LabOrderRow[];
    prescriptions: PrescriptionRow[] | null;
    options: {
        categories: Option[];
        work_types: Option[];
        consultations: { id: number; label: string }[];
        treatment_items: { id: number; label: string }[];
        max_mb: number;
    };
    allergies: string[];
    can: { upload: boolean; lab: boolean; prescribe: boolean };
};

const sizeLabel = (bytes: number) =>
    bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;

function UploadForm({ props, onDone }: { props: Props; onDone: () => void }) {
    const { header, options } = props;

    return (
        <Form
            {...patients.documents.store.form(header.id)}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="grid gap-3 rounded-md border bg-muted/30 p-3 sm:grid-cols-2"
        >
            {({ errors, processing, progress }) => (
                <>
                    <div className="sm:col-span-2">
                        <FormField
                            id="doc-archivo"
                            label="Archivo"
                            hint={`JPG, PNG, WebP o PDF de hasta ${options.max_mb} MB. Se guarda en almacenamiento privado.`}
                            error={errors.file}
                            required
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    type="file"
                                    name="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                />
                            )}
                        </FormField>
                    </div>
                    <FormField id="doc-tipo" label="Tipo" error={errors.category} required>
                        {(p) => (
                            <NativeSelect {...p} name="category" defaultValue="radiografia">
                                {options.categories.map((c) => (
                                    <option key={c.value} value={c.value}>
                                        {c.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField id="doc-titulo" label="Título" error={errors.title} required>
                        {(p) => <Input {...p} name="title" placeholder="Ej.: Periapical 36" />}
                    </FormField>
                    <FormField id="doc-diente" label="Diente (opcional)" error={errors.tooth_code}>
                        {(p) => <Input {...p} name="tooth_code" inputMode="numeric" />}
                    </FormField>
                    <FormField id="doc-fecha" label="Fecha de la toma" error={errors.taken_on}>
                        {(p) => (
                            <Input
                                {...p}
                                type="date"
                                name="taken_on"
                                max={todayLocal()}
                                defaultValue={todayLocal()}
                            />
                        )}
                    </FormField>
                    {options.consultations.length > 0 && (
                        <FormField
                            id="doc-consulta"
                            label="Consulta asociada"
                            error={errors.consultation_id}
                        >
                            {(p) => (
                                <NativeSelect {...p} name="consultation_id" defaultValue="">
                                    <option value="">Ninguna</option>
                                    {options.consultations.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}
                    <FormField id="doc-descripcion" label="Descripción" error={errors.description}>
                        {(p) => <Input {...p} name="description" />}
                    </FormField>
                    <div className="flex items-center gap-3 sm:col-span-2">
                        <Button type="submit" size="sm" disabled={processing}>
                            {processing ? <Spinner /> : <Upload aria-hidden="true" />}
                            Subir documento
                        </Button>
                        {progress && (
                            <progress
                                value={progress.percentage}
                                max={100}
                                className="h-2 w-40"
                                aria-label="Progreso de la carga"
                            >
                                {progress.percentage}%
                            </progress>
                        )}
                        <Button type="button" size="sm" variant="ghost" onClick={onDone}>
                            Cancelar
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function LabOrderForm({ props, onDone }: { props: Props; onDone: () => void }) {
    const { header, options } = props;

    return (
        <Form
            {...patients.labOrders.store.form(header.id)}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="grid gap-3 rounded-md border bg-muted/30 p-3 sm:grid-cols-3"
        >
            {({ errors, processing }) => (
                <>
                    <FormField
                        id="lab-nombre"
                        label="Laboratorio"
                        error={errors.laboratory}
                        required
                    >
                        {(p) => <Input {...p} name="laboratory" />}
                    </FormField>
                    <FormField id="lab-tipo" label="Trabajo" error={errors.work_type} required>
                        {(p) => (
                            <NativeSelect {...p} name="work_type" defaultValue="corona">
                                {options.work_types.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                    {options.treatment_items.length > 0 ? (
                        <FormField
                            id="lab-item"
                            label="Tratamiento del plan"
                            error={errors.treatment_item_id}
                        >
                            {(p) => (
                                <NativeSelect {...p} name="treatment_item_id" defaultValue="">
                                    <option value="">Ninguno</option>
                                    {options.treatment_items.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                    ) : (
                        <div />
                    )}
                    <FormField id="lab-piezas" label="Piezas" error={errors.teeth}>
                        {(p) => <Input {...p} name="teeth" placeholder="Ej.: 36, 37" />}
                    </FormField>
                    <FormField id="lab-color" label="Color" error={errors.shade}>
                        {(p) => <Input {...p} name="shade" placeholder="Ej.: A2" />}
                    </FormField>
                    <div />
                    <FormField id="lab-envio" label="Enviado el" error={errors.sent_on} required>
                        {(p) => (
                            <Input
                                {...p}
                                type="date"
                                name="sent_on"
                                max={todayLocal()}
                                defaultValue={todayLocal()}
                            />
                        )}
                    </FormField>
                    <FormField
                        id="lab-entrega"
                        label="Entrega estimada"
                        error={errors.due_on}
                        required
                    >
                        {(p) => <Input {...p} type="date" name="due_on" min={todayLocal()} />}
                    </FormField>
                    <div className="sm:col-span-3">
                        <FormField
                            id="lab-indicaciones"
                            label="Indicaciones al laboratorio"
                            error={errors.instructions}
                        >
                            {(p) => <Textarea {...p} name="instructions" rows={2} />}
                        </FormField>
                    </div>
                    <div className="flex gap-2 sm:col-span-3">
                        <Button type="submit" size="sm" disabled={processing}>
                            {processing && <Spinner />}
                            Registrar orden
                        </Button>
                        <Button type="button" size="sm" variant="ghost" onClick={onDone}>
                            Cancelar
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

type Med = { key: number; drug: string; dose: string; frequency: string; duration: string };
let medKey = 1;
const emptyMed = (): Med => ({ key: medKey++, drug: '', dose: '', frequency: '', duration: '' });

function PrescriptionForm({ props, onDone }: { props: Props; onDone: () => void }) {
    const { header, allergies } = props;
    const [type, setType] = useState<'receta' | 'indicaciones'>('receta');
    const [meds, setMeds] = useState<Med[]>([emptyMed()]);
    const [acknowledged, setAcknowledged] = useState(false);
    const mustAcknowledge = type === 'receta' && allergies.length > 0;
    const update = (key: number, changes: Partial<Med>) =>
        setMeds((all) => all.map((m) => (m.key === key ? { ...m, ...changes } : m)));

    return (
        <Form
            {...patients.prescriptions.store.form(header.id)}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="grid gap-3 rounded-md border bg-muted/30 p-3"
        >
            {({ errors, processing }) => (
                <>
                    <fieldset className="flex flex-wrap gap-4 text-sm">
                        <legend className="mb-1 text-sm font-medium">Tipo de documento</legend>
                        {(['receta', 'indicaciones'] as const).map((t) => (
                            <div key={t} className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    id={`rx-${t}`}
                                    name="type"
                                    value={t}
                                    checked={type === t}
                                    onChange={() => setType(t)}
                                    className="accent-[var(--primary)]"
                                />
                                <label htmlFor={`rx-${t}`}>
                                    {t === 'receta' ? 'Receta' : 'Indicaciones post-operatorias'}
                                </label>
                            </div>
                        ))}
                    </fieldset>

                    {type === 'receta' && (
                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">Medicamentos</legend>
                            <InputError message={errors.medications} />
                            {meds.map((m, i) => (
                                <div
                                    key={m.key}
                                    className="grid gap-2 rounded-md border bg-background p-2 sm:grid-cols-[2fr_1fr_1.5fr_1fr_auto] sm:items-end"
                                >
                                    <FormField
                                        id={`med-${m.key}-drug`}
                                        label="Medicamento"
                                        error={
                                            errors[`medications.${i}.drug` as keyof typeof errors]
                                        }
                                        required
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name={`medications[${i}][drug]`}
                                                value={m.drug}
                                                onChange={(e) =>
                                                    update(m.key, { drug: e.target.value })
                                                }
                                                placeholder="Ej.: Ibuprofeno 400 mg"
                                            />
                                        )}
                                    </FormField>
                                    <FormField
                                        id={`med-${m.key}-dose`}
                                        label="Dosis"
                                        error={
                                            errors[`medications.${i}.dose` as keyof typeof errors]
                                        }
                                        required
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name={`medications[${i}][dose]`}
                                                value={m.dose}
                                                onChange={(e) =>
                                                    update(m.key, { dose: e.target.value })
                                                }
                                                placeholder="1 comprimido"
                                            />
                                        )}
                                    </FormField>
                                    <FormField
                                        id={`med-${m.key}-freq`}
                                        label="Frecuencia"
                                        error={
                                            errors[
                                                `medications.${i}.frequency` as keyof typeof errors
                                            ]
                                        }
                                        required
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name={`medications[${i}][frequency]`}
                                                value={m.frequency}
                                                onChange={(e) =>
                                                    update(m.key, { frequency: e.target.value })
                                                }
                                                placeholder="cada 8 horas"
                                            />
                                        )}
                                    </FormField>
                                    <FormField
                                        id={`med-${m.key}-dur`}
                                        label="Duración"
                                        error={
                                            errors[
                                                `medications.${i}.duration` as keyof typeof errors
                                            ]
                                        }
                                        required
                                    >
                                        {(p) => (
                                            <Input
                                                {...p}
                                                name={`medications[${i}][duration]`}
                                                value={m.duration}
                                                onChange={(e) =>
                                                    update(m.key, { duration: e.target.value })
                                                }
                                                placeholder="3 días"
                                            />
                                        )}
                                    </FormField>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        disabled={meds.length === 1}
                                        onClick={() =>
                                            setMeds((all) => all.filter((x) => x.key !== m.key))
                                        }
                                        aria-label={`Quitar medicamento ${i + 1}`}
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                </div>
                            ))}
                            <div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setMeds((all) => [...all, emptyMed()])}
                                >
                                    <Plus aria-hidden="true" />
                                    Agregar medicamento
                                </Button>
                            </div>
                        </fieldset>
                    )}

                    <FormField
                        id="rx-indicaciones"
                        label={
                            type === 'receta'
                                ? 'Indicaciones adicionales'
                                : 'Indicaciones post-operatorias'
                        }
                        error={errors.instructions}
                        required={type === 'indicaciones'}
                    >
                        {(p) => (
                            <Textarea
                                {...p}
                                name="instructions"
                                rows={type === 'receta' ? 2 : 6}
                                placeholder={
                                    type === 'indicaciones'
                                        ? 'Ej.: No enjuagarse por 24 horas. Dieta blanda y fría. Aplicar hielo 10 minutos cada hora…'
                                        : ''
                                }
                            />
                        )}
                    </FormField>

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
                                    id="rx-alergias"
                                    checked={acknowledged}
                                    onCheckedChange={(c) => setAcknowledged(c === true)}
                                    className="mt-0.5 bg-white"
                                />
                                <Label htmlFor="rx-alergias" className="leading-snug">
                                    Revisé las alergias del paciente y los medicamentos indicados no
                                    las contraindican.
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
                            size="sm"
                            disabled={processing || (mustAcknowledge && !acknowledged)}
                        >
                            {processing && <Spinner />}
                            Emitir
                        </Button>
                        <Button type="button" size="sm" variant="ghost" onClick={onDone}>
                            Cancelar
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function Documents(props: Props) {
    const { header, documents, lab_orders, prescriptions, can } = props;
    const [uploading, setUploading] = useState(false);
    const [ordering, setOrdering] = useState(false);
    const [prescribing, setPrescribing] = useState(false);

    return (
        <>
            <Head title={`Documentos — ${header.full_name}`} />
            <PatientLayout header={header}>
                {documents !== null && (
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
                            <div className="space-y-1">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <FileImage className="size-4" aria-hidden="true" />
                                    <h2>Radiografías y fotos</h2>
                                </CardTitle>
                                <CardDescription>
                                    Solo el personal autorizado puede verlas. No tienen una
                                    dirección pública.
                                </CardDescription>
                            </div>
                            {can.upload && !uploading && (
                                <Button size="sm" onClick={() => setUploading(true)}>
                                    <Upload aria-hidden="true" />
                                    Subir
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {uploading && (
                                <UploadForm props={props} onDone={() => setUploading(false)} />
                            )}
                            {documents.length === 0 ? (
                                <EmptyState icon={FileImage} title="Sin documentos" />
                            ) : (
                                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {documents.map((d) => (
                                        <li
                                            key={d.id}
                                            className="overflow-hidden rounded-lg border"
                                        >
                                            <a
                                                href={d.url}
                                                target="_blank"
                                                rel="noopener"
                                                className="block bg-muted/40 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                {d.is_image ? (
                                                    <img
                                                        src={d.url}
                                                        alt={`${d.category}: ${d.title}`}
                                                        loading="lazy"
                                                        className="aspect-[4/3] w-full object-contain"
                                                    />
                                                ) : (
                                                    <span className="flex aspect-[4/3] items-center justify-center">
                                                        <FileText
                                                            className="size-12 text-muted-foreground"
                                                            aria-hidden="true"
                                                        />
                                                        <span className="sr-only">
                                                            Abrir PDF {d.title}
                                                        </span>
                                                    </span>
                                                )}
                                            </a>
                                            <div className="grid gap-0.5 p-3 text-sm">
                                                <p className="font-medium">{d.title}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {d.category}
                                                    {d.tooth_code &&
                                                        ` · pieza ${d.tooth_code}`} ·{' '}
                                                    {formatDate(d.taken_on ?? d.created_at)} ·{' '}
                                                    {sizeLabel(d.size)}
                                                </p>
                                                {d.description && (
                                                    <p className="text-xs">{d.description}</p>
                                                )}
                                                <p className="text-xs text-muted-foreground">
                                                    Subido por {d.uploaded_by}
                                                </p>
                                                {can.upload && (
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                className="justify-self-start px-0"
                                                            >
                                                                Archivar
                                                                <span className="sr-only">
                                                                    {' '}
                                                                    {d.title}
                                                                </span>
                                                            </Button>
                                                        }
                                                        title={`¿Archivar «${d.title}»?`}
                                                        description="Deja de mostrarse en la lista. El archivo no se borra y queda registrado en la auditoría."
                                                        confirmLabel="Archivar"
                                                        form={patients.documents.archive.form({
                                                            patient: header.id,
                                                            document: d.id,
                                                        })}
                                                    />
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FlaskConical className="size-4" aria-hidden="true" />
                            <h2>Órdenes a laboratorio</h2>
                        </CardTitle>
                        {can.lab && !ordering && (
                            <Button size="sm" variant="outline" onClick={() => setOrdering(true)}>
                                <Plus aria-hidden="true" />
                                Nueva orden
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {ordering && (
                            <LabOrderForm props={props} onDone={() => setOrdering(false)} />
                        )}
                        {lab_orders.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Sin órdenes a laboratorio.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {lab_orders.map((o) => (
                                    <li
                                        key={o.id}
                                        className={cn(
                                            'flex flex-col gap-2 rounded-md border px-3 py-2 sm:flex-row sm:items-center sm:justify-between',
                                            o.overdue && 'border-red-600',
                                        )}
                                    >
                                        <div className="text-sm">
                                            <p className="flex flex-wrap items-center gap-2 font-medium">
                                                {o.work_type}
                                                {o.teeth && (
                                                    <span className="font-normal text-muted-foreground">
                                                        piezas {o.teeth}
                                                    </span>
                                                )}
                                                <Badge
                                                    variant={
                                                        o.status === 'cancelado'
                                                            ? 'destructive'
                                                            : 'outline'
                                                    }
                                                >
                                                    {o.status_label}
                                                </Badge>
                                                {o.overdue && (
                                                    <Badge variant="destructive">
                                                        <AlertTriangle aria-hidden="true" />
                                                        Atrasada
                                                    </Badge>
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {o.laboratory} · enviado {formatDate(o.sent_on)} ·
                                                entrega estimada {formatDate(o.due_on)}
                                                {o.received_on &&
                                                    ` · recibido ${formatDate(o.received_on)}`}
                                                {o.delivered_on &&
                                                    ` · colocado ${formatDate(o.delivered_on)}`}
                                                {o.shade && ` · color ${o.shade}`}
                                            </p>
                                            {o.instructions && (
                                                <p className="text-xs">{o.instructions}</p>
                                            )}
                                        </div>
                                        {can.lab && o.transitions.length > 0 && (
                                            <div className="flex gap-1">
                                                {o.transitions.map((t) => (
                                                    <Form
                                                        key={t.value}
                                                        {...patients.labOrders.status.form({
                                                            patient: header.id,
                                                            labOrder: o.id,
                                                        })}
                                                        options={{ preserveScroll: true }}
                                                    >
                                                        {({ processing }) => (
                                                            <>
                                                                <input
                                                                    type="hidden"
                                                                    name="status"
                                                                    value={t.value}
                                                                />
                                                                <Button
                                                                    type="submit"
                                                                    size="sm"
                                                                    variant={
                                                                        t.value === 'cancelado'
                                                                            ? 'ghost'
                                                                            : 'outline'
                                                                    }
                                                                    disabled={processing}
                                                                >
                                                                    {t.label}
                                                                </Button>
                                                            </>
                                                        )}
                                                    </Form>
                                                ))}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {prescriptions !== null && (
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
                            <div className="space-y-1">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Pill className="size-4" aria-hidden="true" />
                                    <h2>Recetas e indicaciones</h2>
                                </CardTitle>
                                <CardDescription>
                                    Una vez emitidas no se modifican. El paciente también las ve en
                                    su portal.
                                </CardDescription>
                            </div>
                            {can.prescribe && !prescribing && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setPrescribing(true)}
                                >
                                    <Plus aria-hidden="true" />
                                    Emitir
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {prescribing && (
                                <PrescriptionForm
                                    props={props}
                                    onDone={() => setPrescribing(false)}
                                />
                            )}
                            {prescriptions.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Sin recetas emitidas.
                                </p>
                            ) : (
                                <ul className="grid gap-2">
                                    {prescriptions.map((p) => (
                                        <li
                                            key={p.id}
                                            className="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm"
                                        >
                                            <div>
                                                <p className="font-medium">{p.type}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatLocalDateTime(p.issued_at)} ·{' '}
                                                    {p.professional}
                                                    {p.summary && ` · ${p.summary}`}
                                                </p>
                                            </div>
                                            <Button asChild size="sm" variant="outline">
                                                <a href={p.pdf_url} target="_blank" rel="noopener">
                                                    <FileText aria-hidden="true" />
                                                    PDF
                                                    <span className="sr-only">
                                                        {' '}
                                                        de {p.type} del{' '}
                                                        {formatLocalDateTime(p.issued_at)}
                                                    </span>
                                                </a>
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}
            </PatientLayout>
        </>
    );
}

Documents.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
