import { FormField } from '@/components/form-field';
import { NativeSelect } from '@/components/native-select';
import { Textarea } from '@/components/textarea';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { surfaceLabel, SURFACES } from '@/lib/odontogram';
import type { ConditionOption, Option, Surface } from '@/types';
import { Form } from '@inertiajs/react';
import { useId, useState, type ComponentProps } from 'react';

type FormTarget = Pick<ComponentProps<typeof Form>, 'action' | 'method'>;

export type ToothEventDefaults = {
    surface?: Surface | null;
    condition?: string;
    status?: string;
    detected_on?: string;
    treated_on?: string | null;
    notes?: string | null;
};

const today = () => new Date().toLocaleDateString('en-CA', { timeZone: 'America/Asuncion' });

/**
 * Formulario de un evento del odontograma. Se usa para registrar y para
 * corregir (con `withReason`). El alcance (superficie o diente completo)
 * filtra las condiciones disponibles.
 */
export function ToothEventForm({
    form,
    toothCode,
    defaults = {},
    conditions,
    statuses,
    withReason = false,
    submitLabel,
    onSuccess,
    onCancel,
}: {
    form: FormTarget;
    toothCode: number;
    defaults?: ToothEventDefaults;
    conditions: ConditionOption[];
    statuses: Option[];
    withReason?: boolean;
    submitLabel: string;
    onSuccess?: () => void;
    onCancel?: () => void;
}) {
    const [scope, setScope] = useState<'superficie' | 'diente'>(
        defaults.surface ? 'superficie' : defaults.condition ? 'diente' : 'superficie',
    );
    const [surface, setSurface] = useState<Surface>(defaults.surface ?? 'oclusal');
    const [status, setStatus] = useState(defaults.status ?? 'diagnosticado');
    const available = conditions.filter((c) => c.scope === scope);
    const needsTreatmentDate = status === 'tratado' || status === 'fallido';
    const idPrefix = `evento-${useId().replace(/:/g, '')}`;

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            onSuccess={onSuccess}
            resetOnSuccess
            className="grid gap-3"
        >
            {({ errors, processing }) => (
                <>
                    <input type="hidden" name="tooth_code" value={toothCode} />
                    <input
                        type="hidden"
                        name="surface"
                        value={scope === 'superficie' ? surface : ''}
                    />

                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">Aplicar a</legend>
                        <div className="flex flex-wrap gap-3 text-sm">
                            {(['superficie', 'diente'] as const).map((s) => (
                                <div key={s} className="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        id={`${idPrefix}-scope-${s}`}
                                        name={`${idPrefix}-scope`}
                                        checked={scope === s}
                                        onChange={() => setScope(s)}
                                        className="accent-[var(--primary)]"
                                    />
                                    <label htmlFor={`${idPrefix}-scope-${s}`}>
                                        {s === 'superficie' ? 'Una superficie' : 'Diente completo'}
                                    </label>
                                </div>
                            ))}
                        </div>
                    </fieldset>

                    {scope === 'superficie' && (
                        <FormField
                            id={`${idPrefix}-surface`}
                            label="Superficie"
                            error={errors.surface}
                            required
                        >
                            {(p) => (
                                <NativeSelect
                                    {...p}
                                    value={surface}
                                    onChange={(e) => setSurface(e.target.value as Surface)}
                                >
                                    {SURFACES.map((s) => (
                                        <option key={s} value={s}>
                                            {surfaceLabel(toothCode, s)}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                    )}

                    <FormField
                        id={`${idPrefix}-condition`}
                        label="Condición"
                        error={errors.condition}
                        required
                    >
                        {(p) => (
                            <NativeSelect
                                {...p}
                                key={scope}
                                name="condition"
                                defaultValue={
                                    available.some((c) => c.value === defaults.condition)
                                        ? defaults.condition
                                        : available[0]?.value
                                }
                            >
                                {available.map((c) => (
                                    <option key={c.value} value={c.value}>
                                        {c.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>

                    <FormField
                        id={`${idPrefix}-status`}
                        label="Estado"
                        error={errors.status}
                        required
                    >
                        {(p) => (
                            <NativeSelect
                                {...p}
                                name="status"
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                            >
                                {statuses.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        )}
                    </FormField>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <FormField
                            id={`${idPrefix}-detected`}
                            label="Fecha de detección"
                            error={errors.detected_on}
                            required
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    type="date"
                                    name="detected_on"
                                    max={today()}
                                    defaultValue={defaults.detected_on ?? today()}
                                />
                            )}
                        </FormField>
                        <FormField
                            id={`${idPrefix}-treated`}
                            label="Fecha de tratamiento"
                            error={errors.treated_on}
                            required={needsTreatmentDate}
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    type="date"
                                    name="treated_on"
                                    max={today()}
                                    defaultValue={
                                        defaults.treated_on ?? (needsTreatmentDate ? today() : '')
                                    }
                                    key={needsTreatmentDate ? 'req' : 'opt'}
                                />
                            )}
                        </FormField>
                    </div>

                    <FormField id={`${idPrefix}-notes`} label="Notas" error={errors.notes}>
                        {(p) => (
                            <Textarea
                                {...p}
                                name="notes"
                                rows={2}
                                defaultValue={defaults.notes ?? ''}
                            />
                        )}
                    </FormField>

                    {withReason && (
                        <FormField
                            id={`${idPrefix}-reason`}
                            label="Motivo de la corrección"
                            error={errors.reason}
                            hint="El registro original no se borra: queda en el historial junto con este motivo."
                            required
                        >
                            {(p) => <Input {...p} name="reason" />}
                        </FormField>
                    )}

                    {errors.event && (
                        <p role="alert" className="text-sm text-destructive">
                            {errors.event}
                        </p>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" size="sm" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                        {onCancel && (
                            <Button type="button" size="sm" variant="ghost" onClick={onCancel}>
                                Cancelar
                            </Button>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}
