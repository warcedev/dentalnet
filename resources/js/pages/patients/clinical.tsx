import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { NativeSelect } from '@/components/native-select';
import { PatientLayout } from '@/components/patients/patient-layout';
import { Textarea } from '@/components/textarea';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import patients from '@/routes/patients';
import type { Option, PatientHeader, RecordLifecycle } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { FileClock, History, Plus } from 'lucide-react';
import { useState, type ReactNode } from 'react';

type History = {
    version: number;
    medical_background: string | null;
    surgical_history: string | null;
    current_medication: string | null;
    habits: string | null;
    observations: string | null;
    change_reason: string | null;
    created_at: string;
    recorded_by: string | null;
};

type AllergyRow = RecordLifecycle & {
    id: number;
    type: string;
    allergen: string;
    severity: string;
    severity_label: string;
    reaction: string | null;
};

type ConditionRow = RecordLifecycle & {
    id: number;
    condition: string;
    high_risk: boolean;
    notes: string | null;
};

type Props = {
    header: PatientHeader;
    history: History | null;
    latest_version: number | null;
    versions: {
        version: number;
        created_at: string;
        recorded_by: string | null;
        change_reason: string | null;
    }[];
    allergies: AllergyRow[];
    conditions: ConditionRow[];
    options: { allergy_types: Option[]; severities: Option[]; conditions: Option[] };
};

const HISTORY_FIELDS: { key: keyof History; label: string }[] = [
    { key: 'medical_background', label: 'Antecedentes médicos' },
    { key: 'current_medication', label: 'Medicación actual' },
    { key: 'surgical_history', label: 'Antecedentes quirúrgicos' },
    { key: 'habits', label: 'Hábitos' },
    { key: 'observations', label: 'Observaciones' },
];

function SectionCard({
    title,
    description,
    action,
    children,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
                <div className="space-y-1">
                    <CardTitle className="text-base">
                        <h2>{title}</h2>
                    </CardTitle>
                    {description && <CardDescription>{description}</CardDescription>}
                </div>
                {action}
            </CardHeader>
            <CardContent className="grid gap-4">{children}</CardContent>
        </Card>
    );
}

function InactiveNote({ row }: { row: RecordLifecycle }) {
    return (
        <p className="text-xs text-muted-foreground">
            Desactivada el {formatDateTime(row.deactivated_at)} por {row.deactivated_by ?? '—'}.
            Motivo: {row.deactivation_reason}
        </p>
    );
}

export default function ClinicalRecord({
    header,
    history,
    latest_version,
    versions,
    allergies,
    conditions,
    options,
}: Props) {
    const canEdit = header.can.update_clinical;
    const [editingHistory, setEditingHistory] = useState(false);
    const [addingAllergy, setAddingAllergy] = useState(false);
    const [addingCondition, setAddingCondition] = useState(false);
    const viewingOld =
        history !== null && latest_version !== null && history.version !== latest_version;
    const activeAllergies = allergies.filter((a) => a.is_active);
    const inactiveAllergies = allergies.filter((a) => !a.is_active);
    const activeConditions = conditions.filter((c) => c.is_active);
    const inactiveConditions = conditions.filter((c) => !c.is_active);

    return (
        <>
            <Head title={`Ficha clínica — ${header.full_name}`} />
            <PatientLayout header={header}>
                <div className="grid gap-4 xl:grid-cols-[2fr_1fr]">
                    <SectionCard
                        title="Antecedentes"
                        description={
                            history
                                ? `Versión ${history.version} · ${formatDateTime(history.created_at)} · ${history.recorded_by ?? '—'}`
                                : 'Todavía no se registraron antecedentes.'
                        }
                        action={
                            canEdit &&
                            !editingHistory &&
                            !viewingOld && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setEditingHistory(true)}
                                >
                                    {history ? 'Actualizar' : 'Registrar'}
                                </Button>
                            )
                        }
                    >
                        {viewingOld && (
                            <p role="status" className="rounded-md bg-muted px-3 py-2 text-sm">
                                Está viendo una versión anterior.{' '}
                                <Link
                                    href={patients.clinical(header.id)}
                                    className="font-medium text-primary underline"
                                >
                                    Ver la versión vigente
                                </Link>
                            </p>
                        )}

                        {editingHistory ? (
                            <Form
                                {...patients.history.store.form(header.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setEditingHistory(false)}
                                className="grid gap-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <p className="text-sm text-muted-foreground">
                                            Se guardará como una versión nueva. La versión anterior
                                            queda en el historial y no se modifica.
                                        </p>
                                        {HISTORY_FIELDS.map((f) => (
                                            <FormField
                                                key={f.key}
                                                id={f.key}
                                                label={f.label}
                                                error={errors[f.key]}
                                            >
                                                {(p) => (
                                                    <Textarea
                                                        {...p}
                                                        name={f.key}
                                                        rows={3}
                                                        defaultValue={
                                                            (history?.[f.key] as string | null) ??
                                                            ''
                                                        }
                                                    />
                                                )}
                                            </FormField>
                                        ))}
                                        {history && (
                                            <FormField
                                                id="change_reason"
                                                label="Motivo del cambio"
                                                error={errors.change_reason}
                                                hint="Ej.: actualización en control, corrección de un dato mal cargado."
                                                required
                                            >
                                                {(p) => <Input {...p} name="change_reason" />}
                                            </FormField>
                                        )}
                                        <div className="flex gap-2">
                                            <Button type="submit" disabled={processing}>
                                                {processing && <Spinner />}
                                                Guardar versión
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={() => setEditingHistory(false)}
                                            >
                                                Cancelar
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : history ? (
                            <dl className="grid gap-4 sm:grid-cols-2">
                                {HISTORY_FIELDS.map((f) => (
                                    <div key={f.key} className="grid gap-0.5">
                                        <dt className="text-xs text-muted-foreground">{f.label}</dt>
                                        <dd className="text-sm whitespace-pre-line">
                                            {(history[f.key] as string | null) || '—'}
                                        </dd>
                                    </div>
                                ))}
                                {history.change_reason && (
                                    <div className="grid gap-0.5 sm:col-span-2">
                                        <dt className="text-xs text-muted-foreground">
                                            Motivo del cambio
                                        </dt>
                                        <dd className="text-sm">{history.change_reason}</dd>
                                    </div>
                                )}
                            </dl>
                        ) : (
                            <EmptyState icon={FileClock} title="Sin antecedentes registrados" />
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Historial de versiones"
                        description="Los antecedentes nunca se sobrescriben."
                    >
                        {versions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Sin versiones.</p>
                        ) : (
                            <ol className="grid gap-2">
                                {versions.map((v) => {
                                    const current = history?.version === v.version;

                                    return (
                                        <li key={v.version}>
                                            <Link
                                                href={patients.clinical(header.id, {
                                                    query: { version: v.version },
                                                })}
                                                preserveScroll
                                                aria-current={current ? 'true' : undefined}
                                                className={
                                                    'block rounded-md border px-3 py-2 text-sm hover:bg-accent ' +
                                                    (current ? 'border-primary' : '')
                                                }
                                            >
                                                <span className="flex items-center gap-2 font-medium">
                                                    <History
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Versión {v.version}
                                                    {v.version === latest_version && (
                                                        <Badge variant="secondary">Vigente</Badge>
                                                    )}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatDateTime(v.created_at)} ·{' '}
                                                    {v.recorded_by ?? '—'}
                                                </span>
                                                {v.change_reason && (
                                                    <span className="mt-1 block text-xs">
                                                        {v.change_reason}
                                                    </span>
                                                )}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </SectionCard>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <SectionCard
                        title="Alergias"
                        action={
                            canEdit &&
                            !addingAllergy && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setAddingAllergy(true)}
                                >
                                    <Plus aria-hidden="true" />
                                    Agregar
                                </Button>
                            )
                        }
                    >
                        {addingAllergy && (
                            <Form
                                {...patients.allergies.store.form(header.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setAddingAllergy(false)}
                                className="grid gap-3 rounded-md border p-3 sm:grid-cols-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <FormField
                                            id="allergen"
                                            label="Alérgeno"
                                            error={errors.allergen}
                                            required
                                        >
                                            {(p) => (
                                                <Input
                                                    {...p}
                                                    name="allergen"
                                                    placeholder="Ej.: Penicilina"
                                                    autoFocus
                                                />
                                            )}
                                        </FormField>
                                        <FormField
                                            id="allergy_type"
                                            label="Tipo"
                                            error={errors.type}
                                            required
                                        >
                                            {(p) => (
                                                <NativeSelect
                                                    {...p}
                                                    name="type"
                                                    defaultValue="medicamento"
                                                >
                                                    {options.allergy_types.map((o) => (
                                                        <option key={o.value} value={o.value}>
                                                            {o.label}
                                                        </option>
                                                    ))}
                                                </NativeSelect>
                                            )}
                                        </FormField>
                                        <FormField
                                            id="severity"
                                            label="Gravedad"
                                            error={errors.severity}
                                            required
                                        >
                                            {(p) => (
                                                <NativeSelect
                                                    {...p}
                                                    name="severity"
                                                    defaultValue="moderada"
                                                >
                                                    {options.severities.map((o) => (
                                                        <option key={o.value} value={o.value}>
                                                            {o.label}
                                                        </option>
                                                    ))}
                                                </NativeSelect>
                                            )}
                                        </FormField>
                                        <FormField
                                            id="reaction"
                                            label="Reacción"
                                            error={errors.reaction}
                                        >
                                            {(p) => (
                                                <Input
                                                    {...p}
                                                    name="reaction"
                                                    placeholder="Ej.: urticaria"
                                                />
                                            )}
                                        </FormField>
                                        <div className="flex gap-2 sm:col-span-2">
                                            <Button type="submit" size="sm" disabled={processing}>
                                                {processing && <Spinner />}
                                                Guardar alergia
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => setAddingAllergy(false)}
                                            >
                                                Cancelar
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        )}

                        {activeAllergies.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Sin alergias activas registradas.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {activeAllergies.map((a) => (
                                    <li
                                        key={a.id}
                                        className="flex items-start justify-between gap-2 rounded-md border px-3 py-2"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {a.allergen}{' '}
                                                <Badge
                                                    variant={
                                                        a.severity === 'grave'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {a.severity_label}
                                                </Badge>
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {a.type}
                                                {a.reaction && ` · ${a.reaction}`}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Registrada el {formatDateTime(a.created_at)} por{' '}
                                                {a.recorded_by ?? '—'}
                                            </p>
                                        </div>
                                        {canEdit && (
                                            <ConfirmDialog
                                                trigger={
                                                    <Button size="sm" variant="ghost">
                                                        Desactivar
                                                        <span className="sr-only">
                                                            {' '}
                                                            alergia a {a.allergen}
                                                        </span>
                                                    </Button>
                                                }
                                                title={`¿Desactivar la alergia a ${a.allergen}?`}
                                                description="La alergia dejará de mostrarse como alerta, pero queda en el historial con el motivo."
                                                confirmLabel="Desactivar"
                                                reasonLabel="Motivo"
                                                form={patients.allergies.deactivate.form({
                                                    patient: header.id,
                                                    allergy: a.id,
                                                })}
                                            />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {inactiveAllergies.length > 0 && (
                            <details className="text-sm">
                                <summary className="cursor-pointer text-muted-foreground">
                                    Alergias desactivadas ({inactiveAllergies.length})
                                </summary>
                                <ul className="mt-2 grid gap-2">
                                    {inactiveAllergies.map((a) => (
                                        <li
                                            key={a.id}
                                            className="rounded-md border border-dashed px-3 py-2 opacity-80"
                                        >
                                            <p className="line-through">{a.allergen}</p>
                                            <InactiveNote row={a} />
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Condiciones de riesgo"
                        action={
                            canEdit &&
                            !addingCondition && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setAddingCondition(true)}
                                >
                                    <Plus aria-hidden="true" />
                                    Agregar
                                </Button>
                            )
                        }
                    >
                        {addingCondition && (
                            <Form
                                {...patients.conditions.store.form(header.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setAddingCondition(false)}
                                className="grid gap-3 rounded-md border p-3 sm:grid-cols-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <FormField
                                            id="condition"
                                            label="Condición"
                                            error={errors.condition}
                                            required
                                        >
                                            {(p) => (
                                                <NativeSelect
                                                    {...p}
                                                    name="condition"
                                                    defaultValue=""
                                                >
                                                    <option value="" disabled>
                                                        Seleccione…
                                                    </option>
                                                    {options.conditions.map((o) => (
                                                        <option key={o.value} value={o.value}>
                                                            {o.label}
                                                        </option>
                                                    ))}
                                                </NativeSelect>
                                            )}
                                        </FormField>
                                        <FormField
                                            id="condition_notes"
                                            label="Detalle"
                                            error={errors.notes}
                                        >
                                            {(p) => (
                                                <Input
                                                    {...p}
                                                    name="notes"
                                                    placeholder="Ej.: controlada con losartán"
                                                />
                                            )}
                                        </FormField>
                                        <div className="flex gap-2 sm:col-span-2">
                                            <Button type="submit" size="sm" disabled={processing}>
                                                {processing && <Spinner />}
                                                Guardar condición
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => setAddingCondition(false)}
                                            >
                                                Cancelar
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        )}

                        {activeConditions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Sin condiciones de riesgo activas.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {activeConditions.map((c) => (
                                    <li
                                        key={c.id}
                                        className="flex items-start justify-between gap-2 rounded-md border px-3 py-2"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {c.condition}{' '}
                                                {c.high_risk && (
                                                    <Badge variant="destructive">Riesgo alto</Badge>
                                                )}
                                            </p>
                                            {c.notes && (
                                                <p className="text-sm text-muted-foreground">
                                                    {c.notes}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                Registrada el {formatDateTime(c.created_at)} por{' '}
                                                {c.recorded_by ?? '—'}
                                            </p>
                                        </div>
                                        {canEdit && (
                                            <ConfirmDialog
                                                trigger={
                                                    <Button size="sm" variant="ghost">
                                                        Desactivar
                                                        <span className="sr-only">
                                                            {' '}
                                                            {c.condition}
                                                        </span>
                                                    </Button>
                                                }
                                                title={`¿Desactivar "${c.condition}"?`}
                                                description="Dejará de mostrarse como alerta, pero queda en el historial con el motivo."
                                                confirmLabel="Desactivar"
                                                reasonLabel="Motivo"
                                                form={patients.conditions.deactivate.form({
                                                    patient: header.id,
                                                    condition: c.id,
                                                })}
                                            />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {inactiveConditions.length > 0 && (
                            <details className="text-sm">
                                <summary className="cursor-pointer text-muted-foreground">
                                    Condiciones desactivadas ({inactiveConditions.length})
                                </summary>
                                <ul className="mt-2 grid gap-2">
                                    {inactiveConditions.map((c) => (
                                        <li
                                            key={c.id}
                                            className="rounded-md border border-dashed px-3 py-2 opacity-80"
                                        >
                                            <p className="line-through">{c.condition}</p>
                                            <InactiveNote row={c} />
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        )}
                    </SectionCard>
                </div>
            </PatientLayout>
        </>
    );
}

ClinicalRecord.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
