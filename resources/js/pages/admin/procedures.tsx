import { FormField } from '@/components/form-field';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatGuaranies } from '@/lib/format';
import admin from '@/routes/admin';
import type { Option } from '@/types';
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

type ProcedureRow = {
    id: number;
    name: string;
    default_price: number;
    warranty_months: number;
    requires_anesthesia: boolean;
    tooth_condition: string | null;
    is_active: boolean;
};

function ProcedureForm({
    procedure,
    conditions,
}: {
    procedure?: ProcedureRow;
    conditions: Option[];
}) {
    const [anesthesia, setAnesthesia] = useState(procedure?.requires_anesthesia ?? false);
    const [active, setActive] = useState(procedure?.is_active ?? true);
    const id = procedure ? `proc-${procedure.id}` : 'proc-nuevo';

    return (
        <Form
            {...(procedure
                ? admin.procedures.update.form(procedure.id)
                : admin.procedures.store.form())}
            options={{ preserveScroll: true }}
            resetOnSuccess={!procedure}
            className="grid gap-3 sm:grid-cols-4"
        >
            {({ errors, processing }) => (
                <>
                    <div className="sm:col-span-2">
                        <FormField id={`${id}-name`} label="Nombre" error={errors.name} required>
                            {(p) => <Input {...p} name="name" defaultValue={procedure?.name} />}
                        </FormField>
                    </div>
                    <FormField
                        id={`${id}-price`}
                        label="Precio de referencia"
                        error={errors.default_price}
                        required
                    >
                        {(p) => (
                            <MoneyInput
                                {...p}
                                name="default_price"
                                defaultValue={procedure?.default_price ?? 0}
                            />
                        )}
                    </FormField>
                    <FormField
                        id={`${id}-warranty`}
                        label="Garantía (meses)"
                        error={errors.warranty_months}
                        required
                    >
                        {(p) => (
                            <Input
                                {...p}
                                type="number"
                                min={0}
                                max={240}
                                name="warranty_months"
                                defaultValue={procedure?.warranty_months ?? 0}
                            />
                        )}
                    </FormField>
                    <div className="sm:col-span-2">
                        <FormField
                            id={`${id}-condition`}
                            label="Registrar en el odontograma como"
                            hint="Al hacerse en una consulta, se marca en el odontograma con esta condición."
                            error={errors.tooth_condition}
                        >
                            {(p) => (
                                <NativeSelect
                                    {...p}
                                    name="tooth_condition"
                                    defaultValue={procedure?.tooth_condition ?? ''}
                                >
                                    <option value="">No registrar</option>
                                    {conditions.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            )}
                        </FormField>
                    </div>
                    <div className="flex flex-wrap items-center gap-4 sm:col-span-2">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id={`${id}-anesthesia`}
                                checked={anesthesia}
                                onCheckedChange={(c) => setAnesthesia(c === true)}
                            />
                            <Label htmlFor={`${id}-anesthesia`}>Requiere anestesia</Label>
                            <input
                                type="hidden"
                                name="requires_anesthesia"
                                value={anesthesia ? '1' : '0'}
                            />
                        </div>
                        {procedure && (
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id={`${id}-active`}
                                    checked={active}
                                    onCheckedChange={(c) => setActive(c === true)}
                                />
                                <Label htmlFor={`${id}-active`}>Activo</Label>
                                <input type="hidden" name="is_active" value={active ? '1' : '0'} />
                            </div>
                        )}
                        <Button type="submit" size="sm" disabled={processing}>
                            {procedure ? 'Guardar' : 'Agregar procedimiento'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function Procedures({
    procedures,
    conditions,
}: {
    procedures: ProcedureRow[];
    conditions: Option[];
}) {
    return (
        <>
            <Head title="Procedimientos" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Procedimientos"
                    description="Catálogo con precios de referencia en guaraníes. El precio se puede ajustar en cada presupuesto."
                />
                <Card className="max-w-4xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            <h2>Catálogo</h2>
                        </CardTitle>
                        <CardDescription>
                            «Requiere anestesia» activa la confirmación de alergias en la consulta
                            (regla 6).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {procedures.map((p) => (
                            <details key={p.id} className="rounded-md border px-3 py-2">
                                <summary className="flex cursor-pointer flex-wrap items-center gap-2">
                                    <span className="font-medium">{p.name}</span>
                                    <span className="text-sm text-muted-foreground tabular-nums">
                                        {formatGuaranies(p.default_price)}
                                    </span>
                                    {p.warranty_months > 0 && (
                                        <Badge variant="outline">
                                            Garantía {p.warranty_months} meses
                                        </Badge>
                                    )}
                                    {p.requires_anesthesia && (
                                        <Badge variant="outline">Anestesia</Badge>
                                    )}
                                    {!p.is_active && <Badge variant="secondary">Inactivo</Badge>}
                                </summary>
                                <div className="mt-3">
                                    <ProcedureForm procedure={p} conditions={conditions} />
                                </div>
                            </details>
                        ))}
                        <div className="rounded-md border border-dashed p-3">
                            <h3 className="mb-2 text-sm font-semibold">Nuevo procedimiento</h3>
                            <ProcedureForm conditions={conditions} />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Procedures.layout = {
    breadcrumbs: [{ title: 'Procedimientos', href: admin.procedures.index() }],
};
