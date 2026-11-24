import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import admin from '@/routes/admin';
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

type TypeRow = {
    id: number;
    name: string;
    duration_minutes: number;
    color: string;
    is_cleaning: boolean;
    is_active: boolean;
};
type RoomRow = { id: number; name: string; is_active: boolean };
type Props = {
    types: TypeRow[];
    rooms: RoomRow[];
    config: { opens_at: string; closes_at: string; working_days: number[]; slot_minutes: number };
};

const DAYS = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

function TypeForm({ type, onDone }: { type?: TypeRow; onDone?: () => void }) {
    const [cleaning, setCleaning] = useState(type?.is_cleaning ?? false);
    const [active, setActive] = useState(type?.is_active ?? true);
    const id = type ? `t${type.id}` : 'nuevo';

    return (
        <Form
            {...(type
                ? admin.appointmentTypes.update.form(type.id)
                : admin.appointmentTypes.store.form())}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            resetOnSuccess={!type}
            className="grid gap-3 sm:grid-cols-[2fr_1fr_auto] sm:items-end"
        >
            {({ errors, processing }) => (
                <>
                    <FormField id={`${id}-name`} label="Nombre" error={errors.name} required>
                        {(p) => <Input {...p} name="name" defaultValue={type?.name} />}
                    </FormField>
                    <FormField
                        id={`${id}-duration`}
                        label="Duración (min)"
                        error={errors.duration_minutes}
                        required
                    >
                        {(p) => (
                            <Input
                                {...p}
                                type="number"
                                min={5}
                                max={480}
                                step={5}
                                name="duration_minutes"
                                defaultValue={type?.duration_minutes ?? 30}
                            />
                        )}
                    </FormField>
                    <FormField id={`${id}-color`} label="Color" error={errors.color} required>
                        {(p) => (
                            <Input
                                {...p}
                                type="color"
                                name="color"
                                className="h-9 w-16 p-1"
                                defaultValue={type?.color ?? '#0e7c86'}
                            />
                        )}
                    </FormField>
                    <div className="flex flex-wrap items-center gap-4 sm:col-span-3">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id={`${id}-cleaning`}
                                checked={cleaning}
                                onCheckedChange={(c) => setCleaning(c === true)}
                            />
                            <Label htmlFor={`${id}-cleaning`}>Es una limpieza</Label>
                            <input type="hidden" name="is_cleaning" value={cleaning ? '1' : '0'} />
                        </div>
                        {type && (
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
                            {type ? 'Guardar' : 'Agregar tipo'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function RoomForm({ room }: { room?: RoomRow }) {
    const [active, setActive] = useState(room?.is_active ?? true);
    const id = room ? `r${room.id}` : 'nuevo-consultorio';

    return (
        <Form
            {...(room ? admin.rooms.update.form(room.id) : admin.rooms.store.form())}
            options={{ preserveScroll: true }}
            resetOnSuccess={!room}
            className="flex flex-wrap items-end gap-3"
        >
            {({ errors, processing }) => (
                <>
                    <div className="min-w-48 flex-1">
                        <FormField id={`${id}-name`} label="Nombre" error={errors.name} required>
                            {(p) => <Input {...p} name="name" defaultValue={room?.name} />}
                        </FormField>
                    </div>
                    {room && (
                        <div className="flex items-center gap-2 pb-2">
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
                        {room ? 'Guardar' : 'Agregar consultorio'}
                    </Button>
                </>
            )}
        </Form>
    );
}

export default function AgendaSettings({ types, rooms, config }: Props) {
    return (
        <>
            <Head title="Configuración de la agenda" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Configuración de la agenda"
                    description="Tipos de cita con su duración y consultorios disponibles."
                />

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            <h2>Horario de atención</h2>
                        </CardTitle>
                        <CardDescription>
                            Se configura en el archivo .env (AGENDA_OPENS_AT, AGENDA_CLOSES_AT,
                            AGENDA_WORKING_DAYS).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-sm">
                        De {config.opens_at} a {config.closes_at},{' '}
                        {config.working_days.map((d) => DAYS[d - 1]).join(', ')}. Turnos de{' '}
                        {config.slot_minutes} minutos.
                    </CardContent>
                </Card>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            <h2>Tipos de cita</h2>
                        </CardTitle>
                        <CardDescription>
                            La duración se usa como valor inicial al agendar. Marque «Es una
                            limpieza» para que no se envíe el recordatorio de limpieza a quien ya la
                            tiene agendada.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {types.map((t) => (
                            <details key={t.id} className="rounded-md border px-3 py-2">
                                <summary className="flex cursor-pointer items-center gap-2">
                                    <span
                                        className="inline-block size-3 rounded-full"
                                        style={{ background: t.color }}
                                        aria-hidden="true"
                                    />
                                    <span className="font-medium">{t.name}</span>
                                    <span className="text-sm text-muted-foreground">
                                        {t.duration_minutes} min
                                    </span>
                                    {t.is_cleaning && <Badge variant="outline">Limpieza</Badge>}
                                    {!t.is_active && <Badge variant="secondary">Inactivo</Badge>}
                                </summary>
                                <div className="mt-3">
                                    <TypeForm type={t} />
                                </div>
                            </details>
                        ))}
                        <div className="rounded-md border border-dashed p-3">
                            <h3 className="mb-2 text-sm font-semibold">Nuevo tipo</h3>
                            <TypeForm />
                        </div>
                    </CardContent>
                </Card>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            <h2>Consultorios</h2>
                        </CardTitle>
                        <CardDescription>
                            Sillones o boxes. Una cita no puede superponerse con otra en el mismo
                            consultorio.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {rooms.map((r) => (
                            <RoomForm key={r.id} room={r} />
                        ))}
                        <div className="rounded-md border border-dashed p-3">
                            <RoomForm />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AgendaSettings.layout = {
    breadcrumbs: [{ title: 'Configuración de la agenda', href: admin.agendaSettings() }],
};
