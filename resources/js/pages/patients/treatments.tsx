import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { PatientLayout } from '@/components/patients/patient-layout';
import { Textarea } from '@/components/textarea';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { todayLocal } from '@/lib/agenda';
import { formatDate, formatGuaranies, formatLocalDateTime } from '@/lib/format';
import { surfaceLabel, SURFACES } from '@/lib/odontogram';
import { cn } from '@/lib/utils';
import patients from '@/routes/patients';
import type {
    MoneySummary,
    NamedOption,
    Option,
    PatientHeader,
    PaymentRow,
    ProcedureOption,
    TreatmentItemRow,
    TreatmentPlanRow,
} from '@/types';
import { Form, Head } from '@inertiajs/react';
import { FileText, Plus, ShieldAlert, Wallet } from 'lucide-react';
import { useState } from 'react';

type Props = {
    header: PatientHeader;
    summary: MoneySummary | null;
    plans: TreatmentPlanRow[];
    payments: PaymentRow[];
    expiring_warranties: {
        id: number;
        procedure: string;
        tooth_code: number | null;
        until: string;
    }[];
    options: {
        procedures: ProcedureOption[];
        professionals: NamedOption[];
        methods: Option[];
        adjustment_types: Option[];
    };
    can: {
        manage: boolean;
        register_payment: boolean;
        void_payment: boolean;
        view_payments: boolean;
    };
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    propuesto: 'outline',
    aceptado: 'default',
    en_curso: 'default',
    completado: 'secondary',
    cancelado: 'destructive',
};

const INSTALLMENT_STYLE: Record<string, string> = {
    pagada: 'text-green-700 dark:text-green-400',
    parcial: 'text-amber-700 dark:text-amber-400',
    pendiente: 'text-muted-foreground',
    vencida: 'text-red-700 dark:text-red-400 font-medium',
};

function SummaryCards({ summary }: { summary: MoneySummary }) {
    const cards = [
        { label: 'Presupuestado sin aceptar', value: summary.proposed },
        { label: 'Total a pagar', value: summary.billed, hint: 'aceptado, en curso y completado' },
        { label: 'Pagado', value: summary.paid },
        {
            label: summary.balance < 0 ? 'Saldo a favor' : 'Saldo pendiente',
            value: Math.abs(summary.balance),
            strong: true,
        },
    ];

    return (
        <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {cards.map((c) => (
                <div
                    key={c.label}
                    className={cn(
                        'rounded-xl border p-4',
                        c.strong && summary.balance > 0 && 'border-primary',
                    )}
                >
                    <dt className="text-sm text-muted-foreground">{c.label}</dt>
                    <dd
                        className={cn('text-xl font-semibold tabular-nums', c.strong && 'text-2xl')}
                    >
                        {formatGuaranies(c.value)}
                    </dd>
                    {c.hint && <dd className="text-xs text-muted-foreground">{c.hint}</dd>}
                </div>
            ))}
        </dl>
    );
}

function ItemForm({
    plan,
    item,
    procedures,
    onDone,
    headerId,
}: {
    plan: TreatmentPlanRow;
    item?: TreatmentItemRow;
    procedures: ProcedureOption[];
    onDone: () => void;
    headerId: number;
}) {
    const [procedureId, setProcedureId] = useState<number | ''>('');
    const [price, setPrice] = useState<number | null>(item?.price ?? null);
    const [warranty, setWarranty] = useState<number | ''>(item?.warranty_months ?? '');
    const [tooth, setTooth] = useState<string>(item?.tooth_code ? String(item.tooth_code) : '');
    const prefix = item ? `item-${item.id}` : `nuevo-${plan.id}`;

    return (
        <Form
            {...(item
                ? patients.treatments.items.update.form({
                      patient: headerId,
                      treatmentPlan: plan.id,
                      treatmentItem: item.id,
                  })
                : patients.treatments.items.store.form({
                      patient: headerId,
                      treatmentPlan: plan.id,
                  }))}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="grid gap-3 rounded-md border bg-muted/30 p-3 sm:grid-cols-6"
        >
            {({ errors, processing }) => (
                <>
                    {!item && (
                        <div className="sm:col-span-3">
                            <FormField
                                id={`${prefix}-proc`}
                                label="Procedimiento"
                                error={errors.procedure_id}
                                required
                            >
                                {(p) => (
                                    <NativeSelect
                                        {...p}
                                        name="procedure_id"
                                        value={procedureId}
                                        onChange={(e) => {
                                            const id = Number(e.target.value);
                                            const proc = procedures.find((x) => x.id === id);
                                            setProcedureId(id);
                                            setPrice(proc?.default_price ?? null);
                                            setWarranty(proc?.warranty_months ?? '');
                                        }}
                                    >
                                        <option value="" disabled>
                                            Seleccione…
                                        </option>
                                        {procedures.map((o) => (
                                            <option key={o.id} value={o.id}>
                                                {o.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </FormField>
                        </div>
                    )}
                    <div className={item ? 'sm:col-span-3' : 'sm:col-span-3'}>
                        <FormField
                            id={`${prefix}-desc`}
                            label="Descripción (opcional)"
                            error={errors.description}
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    name="description"
                                    defaultValue={item?.description ?? ''}
                                />
                            )}
                        </FormField>
                    </div>
                    <div className="sm:col-span-2">
                        <FormField
                            id={`${prefix}-price`}
                            label="Precio"
                            error={errors.price}
                            required
                        >
                            {(p) => (
                                <MoneyInput
                                    {...p}
                                    name="price"
                                    value={price}
                                    onValueChange={setPrice}
                                />
                            )}
                        </FormField>
                    </div>
                    <FormField id={`${prefix}-tooth`} label="Diente" error={errors.tooth_code}>
                        {(p) => (
                            <Input
                                {...p}
                                name="tooth_code"
                                inputMode="numeric"
                                placeholder="Ej.: 16"
                                value={tooth}
                                onChange={(e) =>
                                    setTooth(e.target.value.replace(/\D/g, '').slice(0, 2))
                                }
                            />
                        )}
                    </FormField>
                    <FormField id={`${prefix}-surface`} label="Superficie" error={errors.surface}>
                        {(p) => (
                            <NativeSelect
                                {...p}
                                name="surface"
                                defaultValue={item?.surface ?? ''}
                                disabled={!tooth}
                            >
                                <option value="">—</option>
                                {SURFACES.map((s) => (
                                    <option key={s} value={s}>
                                        {surfaceLabel(tooth ? Number(tooth) : null, s)}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>
                    <FormField
                        id={`${prefix}-warranty`}
                        label="Garantía (meses)"
                        error={errors.warranty_months}
                    >
                        {(p) => (
                            <Input
                                {...p}
                                type="number"
                                min={0}
                                max={240}
                                name="warranty_months"
                                value={warranty}
                                onChange={(e) =>
                                    setWarranty(e.target.value === '' ? '' : Number(e.target.value))
                                }
                            />
                        )}
                    </FormField>
                    <div className="flex items-end gap-2 sm:col-span-6">
                        <Button
                            type="submit"
                            size="sm"
                            disabled={processing || (!item && !procedureId)}
                        >
                            {processing && <Spinner />}
                            {item ? 'Guardar' : 'Agregar al presupuesto'}
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

function AdjustDialog({
    item,
    plan,
    headerId,
    types,
    onClose,
}: {
    item: TreatmentItemRow;
    plan: TreatmentPlanRow;
    headerId: number;
    types: Option[];
    onClose: () => void;
}) {
    const [type, setType] = useState('precio');

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>Ajuste de un tratamiento completado</DialogTitle>
                <DialogDescription>
                    {item.procedure}: el tratamiento completado no se modifica. El ajuste queda
                    registrado con su motivo y su autor (regla 3).
                </DialogDescription>
                <Form
                    {...patients.treatments.items.adjust.form({
                        patient: headerId,
                        treatmentPlan: plan.id,
                        treatmentItem: item.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <FormField
                                id="ajuste-tipo"
                                label="Tipo de ajuste"
                                error={errors.type}
                                required
                            >
                                {(p) => (
                                    <NativeSelect
                                        {...p}
                                        name="type"
                                        value={type}
                                        onChange={(e) => setType(e.target.value)}
                                    >
                                        {types.map((t) => (
                                            <option key={t.value} value={t.value}>
                                                {t.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </FormField>
                            {type === 'precio' && (
                                <FormField
                                    id="ajuste-valor"
                                    label="Nuevo precio"
                                    hint={`Precio vigente: ${formatGuaranies(item.effective_price)}`}
                                    error={errors.new_value}
                                    required
                                >
                                    {(p) => (
                                        <MoneyInput
                                            {...p}
                                            name="new_value"
                                            defaultValue={item.effective_price}
                                        />
                                    )}
                                </FormField>
                            )}
                            {type === 'garantia' && (
                                <FormField
                                    id="ajuste-valor"
                                    label="Nueva garantía (meses)"
                                    hint={`Garantía vigente: ${item.warranty_months} meses`}
                                    error={errors.new_value}
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            type="number"
                                            min={0}
                                            max={240}
                                            name="new_value"
                                            defaultValue={item.warranty_months}
                                        />
                                    )}
                                </FormField>
                            )}
                            <FormField
                                id="ajuste-motivo"
                                label="Motivo"
                                error={errors.reason}
                                required
                            >
                                {(p) => <Textarea {...p} name="reason" rows={2} />}
                            </FormField>
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="ghost" onClick={onClose}>
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Registrar ajuste
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function PlanCard({ plan, props }: { plan: TreatmentPlanRow; props: Props }) {
    const { header, options, can } = props;
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<number | null>(null);
    const [adjusting, setAdjusting] = useState<TreatmentItemRow | null>(null);
    const canManage = can.manage && !plan.closed;
    const ids = { patient: header.id, treatmentPlan: plan.id };

    return (
        <Card>
            <CardHeader className="flex flex-col gap-2 space-y-0 sm:flex-row sm:items-start sm:justify-between">
                <div className="space-y-1">
                    <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                        <h2>{plan.title}</h2>
                        {plan.closed && <Badge variant="secondary">Cerrado</Badge>}
                    </CardTitle>
                    <CardDescription>
                        {plan.professional} · creado el {formatDate(plan.created_at)}
                        {plan.notes && ` · ${plan.notes}`}
                    </CardDescription>
                </div>
                <div className="text-right text-sm">
                    <p>
                        Total{' '}
                        <span className="font-semibold tabular-nums">
                            {formatGuaranies(plan.summary.billed)}
                        </span>
                    </p>
                    {can.view_payments && (
                        <p className="text-muted-foreground">
                            Pagado {formatGuaranies(plan.summary.paid)} · Saldo{' '}
                            <span className="font-medium text-foreground tabular-nums">
                                {formatGuaranies(plan.summary.balance)}
                            </span>
                        </p>
                    )}
                    {plan.summary.proposed > 0 && (
                        <p className="text-muted-foreground">
                            Sin aceptar {formatGuaranies(plan.summary.proposed)}
                        </p>
                    )}
                </div>
            </CardHeader>
            <CardContent className="grid gap-4">
                {plan.items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        El plan todavía no tiene tratamientos.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Tratamientos del plan {plan.title}
                            </caption>
                            <thead className="border-b text-muted-foreground">
                                <tr>
                                    <th scope="col" className="py-2 pr-3 font-medium">
                                        Tratamiento
                                    </th>
                                    <th scope="col" className="py-2 pr-3 font-medium">
                                        Pieza
                                    </th>
                                    <th scope="col" className="py-2 pr-3 text-right font-medium">
                                        Precio
                                    </th>
                                    <th scope="col" className="py-2 pr-3 font-medium">
                                        Garantía
                                    </th>
                                    <th scope="col" className="py-2 pr-3 font-medium">
                                        Estado
                                    </th>
                                    <th scope="col" className="py-2 font-medium">
                                        <span className="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {plan.items.map((item) => (
                                    <tr key={item.id} className="border-b align-top last:border-0">
                                        <td className="py-2 pr-3">
                                            <span
                                                className={cn(
                                                    'font-medium',
                                                    item.status === 'cancelado' && 'line-through',
                                                )}
                                            >
                                                {item.procedure}
                                            </span>
                                            {item.description && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {item.description}
                                                </span>
                                            )}
                                            {item.cancellation_reason && (
                                                <span className="block text-xs text-muted-foreground">
                                                    Motivo: {item.cancellation_reason}
                                                </span>
                                            )}
                                            {item.adjustments.length > 0 && (
                                                <details className="mt-1 text-xs">
                                                    <summary className="cursor-pointer text-muted-foreground">
                                                        Ajustes ({item.adjustments.length})
                                                    </summary>
                                                    <ul className="mt-1 grid gap-1">
                                                        {item.adjustments.map((a) => (
                                                            <li key={a.id}>
                                                                {a.type}
                                                                {a.new_value !== null &&
                                                                    `: ${a.is_money ? formatGuaranies(Number(a.old_value)) : `${a.old_value} meses`} → ${a.is_money ? formatGuaranies(Number(a.new_value)) : `${a.new_value} meses`}`}{' '}
                                                                · {a.reason} · {a.author},{' '}
                                                                {formatDate(a.created_at)}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </details>
                                            )}
                                        </td>
                                        <td className="py-2 pr-3 whitespace-nowrap">
                                            {item.tooth_code ?? '—'}
                                            {item.surface_label && ` ${item.surface_label}`}
                                        </td>
                                        <td className="py-2 pr-3 text-right whitespace-nowrap tabular-nums">
                                            {item.effective_price !== item.price && (
                                                <span className="block text-xs text-muted-foreground line-through">
                                                    {formatGuaranies(item.price)}
                                                </span>
                                            )}
                                            {formatGuaranies(item.effective_price)}
                                        </td>
                                        <td className="py-2 pr-3 text-xs whitespace-nowrap">
                                            {item.warranty_months > 0
                                                ? `${item.warranty_months} meses`
                                                : '—'}
                                            {item.warranty_until && (
                                                <span
                                                    className={cn(
                                                        'block',
                                                        item.warranty_expired
                                                            ? 'text-muted-foreground'
                                                            : 'text-green-700 dark:text-green-400',
                                                    )}
                                                >
                                                    {item.warranty_expired ? 'Venció' : 'Hasta'}{' '}
                                                    {formatDate(item.warranty_until)}
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 pr-3">
                                            <Badge variant={STATUS_VARIANT[item.status]}>
                                                {item.status_label}
                                            </Badge>
                                            {item.completed_on && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatDate(item.completed_on)}
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2">
                                            {canManage && (
                                                <div className="flex flex-wrap justify-end gap-1">
                                                    {item.transitions
                                                        .filter((t) => t.value !== 'cancelado')
                                                        .map((t) => (
                                                            <Form
                                                                key={t.value}
                                                                {...patients.treatments.items.status.form(
                                                                    {
                                                                        ...ids,
                                                                        treatmentItem: item.id,
                                                                    },
                                                                )}
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
                                                                            variant="outline"
                                                                            disabled={processing}
                                                                        >
                                                                            {t.label}
                                                                        </Button>
                                                                    </>
                                                                )}
                                                            </Form>
                                                        ))}
                                                    {item.editable && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                setEditing(
                                                                    editing === item.id
                                                                        ? null
                                                                        : item.id,
                                                                )
                                                            }
                                                        >
                                                            Editar
                                                        </Button>
                                                    )}
                                                    {item.transitions.some(
                                                        (t) => t.value === 'cancelado',
                                                    ) && (
                                                        <ConfirmDialog
                                                            trigger={
                                                                <Button size="sm" variant="ghost">
                                                                    Cancelar
                                                                    <span className="sr-only">
                                                                        {' '}
                                                                        {item.procedure}
                                                                    </span>
                                                                </Button>
                                                            }
                                                            title={`¿Cancelar «${item.procedure}»?`}
                                                            description="Deja de sumar al saldo del paciente. Queda en el historial con el motivo."
                                                            confirmLabel="Cancelar tratamiento"
                                                            reasonLabel="Motivo"
                                                            hidden={{ status: 'cancelado' }}
                                                            form={patients.treatments.items.status.form(
                                                                { ...ids, treatmentItem: item.id },
                                                            )}
                                                            destructive
                                                        />
                                                    )}
                                                    {item.status === 'completado' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => setAdjusting(item)}
                                                        >
                                                            Ajustar
                                                        </Button>
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {editing !== null && (
                    <ItemForm
                        plan={plan}
                        item={plan.items.find((i) => i.id === editing)}
                        procedures={options.procedures}
                        headerId={header.id}
                        onDone={() => setEditing(null)}
                    />
                )}

                {canManage &&
                    (adding ? (
                        <ItemForm
                            plan={plan}
                            procedures={options.procedures}
                            headerId={header.id}
                            onDone={() => setAdding(false)}
                        />
                    ) : (
                        <div>
                            <Button size="sm" variant="outline" onClick={() => setAdding(true)}>
                                <Plus aria-hidden="true" />
                                Agregar tratamiento
                            </Button>
                        </div>
                    ))}

                {can.view_payments &&
                    (plan.installments.length > 0 || (canManage && plan.summary.balance > 0)) && (
                        <section
                            aria-label={`Cuotas del plan ${plan.title}`}
                            className="grid gap-2 border-t pt-3"
                        >
                            <h3 className="text-sm font-semibold">Cuotas</h3>
                            {plan.installments.length > 0 && (
                                <ul className="grid gap-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                    {plan.installments.map((c) => (
                                        <li
                                            key={c.id}
                                            className="flex justify-between gap-2 rounded-md border px-3 py-1.5"
                                        >
                                            <span>
                                                Cuota {c.number} · vence {formatDate(c.due_date)}
                                            </span>
                                            <span
                                                className={cn(
                                                    'text-right tabular-nums',
                                                    INSTALLMENT_STYLE[c.status],
                                                )}
                                            >
                                                {formatGuaranies(c.amount)}
                                                <span className="block text-xs">
                                                    {c.status_label}
                                                    {c.status === 'parcial' &&
                                                        ` (falta ${formatGuaranies(c.pending)})`}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {canManage && plan.summary.balance > 0 && (
                                <Form
                                    {...patients.treatments.installments.store.form(ids)}
                                    options={{ preserveScroll: true }}
                                    className="flex flex-wrap items-end gap-2"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="w-32">
                                                <FormField
                                                    id={`cuotas-${plan.id}`}
                                                    label="Cantidad de cuotas"
                                                    error={errors.count}
                                                >
                                                    {(p) => (
                                                        <Input
                                                            {...p}
                                                            type="number"
                                                            name="count"
                                                            min={1}
                                                            max={36}
                                                            defaultValue={3}
                                                        />
                                                    )}
                                                </FormField>
                                            </div>
                                            <div className="w-44">
                                                <FormField
                                                    id={`cuotas-fecha-${plan.id}`}
                                                    label="Primer vencimiento"
                                                    error={errors.first_due}
                                                >
                                                    {(p) => (
                                                        <Input
                                                            {...p}
                                                            type="date"
                                                            name="first_due"
                                                            min={todayLocal()}
                                                            defaultValue={todayLocal()}
                                                        />
                                                    )}
                                                </FormField>
                                            </div>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                {plan.installments.length
                                                    ? 'Rehacer cuotas'
                                                    : 'Financiar saldo en cuotas'}
                                            </Button>
                                            <p className="w-full text-xs text-muted-foreground">
                                                Divide el saldo actual del plan (
                                                {formatGuaranies(plan.summary.balance)}) en cuotas
                                                mensuales.
                                            </p>
                                        </>
                                    )}
                                </Form>
                            )}
                        </section>
                    )}

                {can.manage && (
                    <Form
                        {...patients.treatments.plans.close.form(ids)}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button type="submit" size="sm" variant="ghost" disabled={processing}>
                                {plan.closed ? 'Reabrir plan' : 'Cerrar plan'}
                            </Button>
                        )}
                    </Form>
                )}
            </CardContent>

            {adjusting && (
                <AdjustDialog
                    item={adjusting}
                    plan={plan}
                    headerId={header.id}
                    types={options.adjustment_types}
                    onClose={() => setAdjusting(null)}
                />
            )}
        </Card>
    );
}

export default function Treatments(props: Props) {
    const { header, summary, plans, payments, expiring_warranties, options, can } = props;
    const [creating, setCreating] = useState(false);
    const [method, setMethod] = useState('efectivo');
    const openPlans = plans.filter((p) => !p.closed);

    return (
        <>
            <Head title={`Tratamientos y pagos — ${header.full_name}`} />
            <PatientLayout
                header={header}
                actions={
                    can.manage && (
                        <Button onClick={() => setCreating((v) => !v)} aria-expanded={creating}>
                            <Plus aria-hidden="true" />
                            Nuevo plan
                        </Button>
                    )
                }
            >
                {summary && <SummaryCards summary={summary} />}

                {expiring_warranties.length > 0 && (
                    <p
                        className="flex flex-wrap items-center gap-2 rounded-md border border-amber-500 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:bg-amber-950/30 dark:text-amber-50"
                        role="status"
                    >
                        <ShieldAlert className="size-4" aria-hidden="true" />
                        Garantías que vencen en los próximos 60 días:{' '}
                        {expiring_warranties
                            .map(
                                (w) =>
                                    `${w.procedure}${w.tooth_code ? ` (${w.tooth_code})` : ''} el ${formatDate(w.until)}`,
                            )
                            .join('; ')}
                        . Conviene agendar un control.
                    </p>
                )}

                {creating && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                <h2>Nuevo plan de tratamiento</h2>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...patients.treatments.plans.store.form(header.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setCreating(false)}
                                className="grid gap-3 sm:grid-cols-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <FormField
                                            id="plan-titulo"
                                            label="Título"
                                            error={errors.title}
                                            required
                                        >
                                            {(p) => (
                                                <Input
                                                    {...p}
                                                    name="title"
                                                    placeholder="Ej.: Rehabilitación sector posterior"
                                                />
                                            )}
                                        </FormField>
                                        <FormField
                                            id="plan-profesional"
                                            label="Profesional"
                                            error={errors.professional_id}
                                            required
                                        >
                                            {(p) => (
                                                <NativeSelect
                                                    {...p}
                                                    name="professional_id"
                                                    defaultValue={options.professionals[0]?.id}
                                                >
                                                    {options.professionals.map((o) => (
                                                        <option key={o.id} value={o.id}>
                                                            {o.name}
                                                        </option>
                                                    ))}
                                                </NativeSelect>
                                            )}
                                        </FormField>
                                        <div className="sm:col-span-2">
                                            <FormField
                                                id="plan-notas"
                                                label="Notas"
                                                error={errors.notes}
                                            >
                                                {(p) => <Textarea {...p} name="notes" rows={2} />}
                                            </FormField>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button type="submit" disabled={processing}>
                                                {processing && <Spinner />}
                                                Crear plan
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={() => setCreating(false)}
                                            >
                                                Cancelar
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {plans.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="Sin planes de tratamiento"
                        description="Cree un plan para armar el presupuesto del paciente."
                    />
                ) : (
                    plans.map((plan) => <PlanCard key={plan.id} plan={plan} props={props} />)
                )}

                {can.view_payments && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Wallet className="size-4" aria-hidden="true" />
                                <h2>Pagos</h2>
                            </CardTitle>
                            <CardDescription>
                                Los pagos no se borran: si hay un error, el administrador lo anula y
                                se registra de nuevo.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {can.register_payment && (
                                <Form
                                    {...patients.payments.store.form(header.id)}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="grid gap-3 rounded-md border bg-muted/30 p-3 sm:grid-cols-3"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <FormField
                                                id="pago-monto"
                                                label="Monto"
                                                error={errors.amount}
                                                required
                                            >
                                                {(p) => <MoneyInput {...p} name="amount" />}
                                            </FormField>
                                            <FormField
                                                id="pago-forma"
                                                label="Forma de pago"
                                                error={errors.method}
                                                required
                                            >
                                                {(p) => (
                                                    <NativeSelect
                                                        {...p}
                                                        name="method"
                                                        value={method}
                                                        onChange={(e) => setMethod(e.target.value)}
                                                    >
                                                        {options.methods.map((m) => (
                                                            <option key={m.value} value={m.value}>
                                                                {m.label}
                                                            </option>
                                                        ))}
                                                    </NativeSelect>
                                                )}
                                            </FormField>
                                            <FormField
                                                id="pago-fecha"
                                                label="Fecha"
                                                error={errors.paid_on}
                                                required
                                            >
                                                {(p) => (
                                                    <Input
                                                        {...p}
                                                        type="date"
                                                        name="paid_on"
                                                        max={todayLocal()}
                                                        defaultValue={todayLocal()}
                                                    />
                                                )}
                                            </FormField>
                                            <FormField
                                                id="pago-plan"
                                                label="Imputar al plan"
                                                error={errors.treatment_plan_id}
                                            >
                                                {(p) => (
                                                    <NativeSelect
                                                        {...p}
                                                        name="treatment_plan_id"
                                                        defaultValue={
                                                            openPlans.length === 1
                                                                ? openPlans[0].id
                                                                : ''
                                                        }
                                                    >
                                                        <option value="">
                                                            Sin plan (a cuenta)
                                                        </option>
                                                        {plans.map((pl) => (
                                                            <option key={pl.id} value={pl.id}>
                                                                {pl.title}
                                                            </option>
                                                        ))}
                                                    </NativeSelect>
                                                )}
                                            </FormField>
                                            <FormField
                                                id="pago-referencia"
                                                label="N.º de operación"
                                                error={errors.reference}
                                                required={
                                                    method === 'transferencia' || method === 'qr'
                                                }
                                            >
                                                {(p) => <Input {...p} name="reference" />}
                                            </FormField>
                                            <FormField
                                                id="pago-notas"
                                                label="Notas"
                                                error={errors.notes}
                                            >
                                                {(p) => <Input {...p} name="notes" />}
                                            </FormField>
                                            <div className="sm:col-span-3">
                                                <Button type="submit" disabled={processing}>
                                                    {processing && <Spinner />}
                                                    Registrar pago
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            )}

                            {payments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Todavía no hay pagos registrados.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <caption className="sr-only">Pagos del paciente</caption>
                                        <thead className="border-b text-muted-foreground">
                                            <tr>
                                                <th scope="col" className="py-2 pr-3 font-medium">
                                                    Recibo
                                                </th>
                                                <th scope="col" className="py-2 pr-3 font-medium">
                                                    Fecha
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="py-2 pr-3 text-right font-medium"
                                                >
                                                    Monto
                                                </th>
                                                <th scope="col" className="py-2 pr-3 font-medium">
                                                    Forma
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="hidden py-2 pr-3 font-medium md:table-cell"
                                                >
                                                    Plan
                                                </th>
                                                <th scope="col" className="py-2 font-medium">
                                                    <span className="sr-only">Acciones</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {payments.map((p) => (
                                                <tr
                                                    key={p.id}
                                                    className={cn(
                                                        'border-b align-top last:border-0',
                                                        p.voided && 'text-muted-foreground',
                                                    )}
                                                >
                                                    <td className="py-2 pr-3 tabular-nums">
                                                        N.º {p.receipt_number}
                                                        {p.voided && (
                                                            <Badge
                                                                variant="destructive"
                                                                className="ml-2"
                                                            >
                                                                Anulado
                                                            </Badge>
                                                        )}
                                                        {p.voided && (
                                                            <span className="block text-xs">
                                                                Motivo: {p.void_reason} (
                                                                {p.voided_by})
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-2 pr-3 whitespace-nowrap">
                                                        {formatLocalDateTime(p.paid_at)}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            'py-2 pr-3 text-right tabular-nums',
                                                            p.voided && 'line-through',
                                                        )}
                                                    >
                                                        {formatGuaranies(p.amount)}
                                                    </td>
                                                    <td className="py-2 pr-3">
                                                        {p.method}
                                                        {p.reference && (
                                                            <span className="block text-xs text-muted-foreground">
                                                                Op. {p.reference}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="hidden py-2 pr-3 md:table-cell">
                                                        {p.plan ?? '—'}
                                                    </td>
                                                    <td className="py-2">
                                                        <div className="flex justify-end gap-1">
                                                            <Button
                                                                asChild
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                <a
                                                                    href={p.receipt_url}
                                                                    target="_blank"
                                                                    rel="noopener"
                                                                >
                                                                    <FileText aria-hidden="true" />
                                                                    Recibo
                                                                    <span className="sr-only">
                                                                        {' '}
                                                                        N.º {p.receipt_number} (PDF)
                                                                    </span>
                                                                </a>
                                                            </Button>
                                                            {can.void_payment && !p.voided && (
                                                                <ConfirmDialog
                                                                    trigger={
                                                                        <Button
                                                                            size="sm"
                                                                            variant="ghost"
                                                                        >
                                                                            Anular
                                                                            <span className="sr-only">
                                                                                {' '}
                                                                                recibo{' '}
                                                                                {p.receipt_number}
                                                                            </span>
                                                                        </Button>
                                                                    }
                                                                    title={`¿Anular el recibo N.º ${p.receipt_number}?`}
                                                                    description="El pago deja de contar para el saldo. Queda registrado como anulado con el motivo."
                                                                    confirmLabel="Anular pago"
                                                                    reasonLabel="Motivo"
                                                                    form={patients.payments.void.form(
                                                                        {
                                                                            patient: header.id,
                                                                            payment: p.id,
                                                                        },
                                                                    )}
                                                                    destructive
                                                                />
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </PatientLayout>
        </>
    );
}

Treatments.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
