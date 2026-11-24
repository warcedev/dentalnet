import { AppointmentDetailsDialog } from '@/components/agenda/appointment-details-dialog';
import {
    AppointmentFormDialog,
    type AppointmentDraft,
} from '@/components/agenda/appointment-form-dialog';
import { BlockDialog } from '@/components/agenda/block-dialog';
import { MonthView } from '@/components/agenda/month-view';
import { TimeGrid, type GridColumn } from '@/components/agenda/time-grid';
import { EmptyState } from '@/components/empty-state';
import { NativeSelect } from '@/components/native-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    addDays,
    addMonths,
    dayLabel,
    fromMinutes,
    isoWeekday,
    monthLabel,
    nowMinutesLocal,
    splitDateTime,
    startOfWeek,
    toMinutes,
    todayLocal,
} from '@/lib/agenda';
import { agenda } from '@/routes';
import waitingList from '@/routes/waiting-list';
import type {
    AgendaAppointment,
    AgendaConfig,
    AgendaView,
    AppointmentTypeOption,
    NamedOption,
    PatientOption,
    ScheduleBlockItem,
} from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ListTodo,
    Plus,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type Props = {
    view: AgendaView;
    date: string;
    range: { from: string; to: string };
    filters: {
        agrupar: 'profesional' | 'consultorio';
        profesional: number | null;
        consultorio: number | null;
    };
    appointments: AgendaAppointment[];
    blocks: ScheduleBlockItem[];
    professionals: NamedOption[];
    rooms: NamedOption[];
    types: AppointmentTypeOption[];
    config: AgendaConfig;
    can_manage: boolean;
    prefill: {
        patient: PatientOption;
        appointment_type_id?: number | null;
        professional_id?: number | null;
        waiting_list_entry_id?: number;
    } | null;
    patient_options?: PatientOption[];
};

type Query = Partial<{
    vista: AgendaView;
    fecha: string;
    agrupar: string;
    profesional: number | null;
    consultorio: number | null;
}>;

function overlapsDay(block: ScheduleBlockItem, date: string) {
    return splitDateTime(block.starts_at).date <= date && splitDateTime(block.ends_at).date >= date;
}

function withWho(b: ScheduleBlockItem): ScheduleBlockItem {
    const who = [b.professional?.name, b.room?.name].filter(Boolean).join(', ');

    return { ...b, reason: who ? `${b.reason} (${who})` : `${b.reason} (toda la clínica)` };
}

function nextFreeTime(config: AgendaConfig) {
    const step = config.slot_minutes;
    const next = Math.ceil(nowMinutesLocal() / step) * step;

    return fromMinutes(
        Math.min(Math.max(next, toMinutes(config.opens_at)), toMinutes(config.closes_at) - step),
    );
}

export default function Agenda(props: Props) {
    const {
        view,
        date,
        filters,
        appointments,
        blocks,
        professionals,
        rooms,
        types,
        config,
        can_manage,
        prefill,
        patient_options,
    } = props;
    const [formOpen, setFormOpen] = useState(false);
    const [draft, setDraft] = useState<AppointmentDraft>({ date, time: config.opens_at });
    const [editing, setEditing] = useState<AgendaAppointment | null>(null);
    const [details, setDetails] = useState<AgendaAppointment | null>(null);
    const [blockOpen, setBlockOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);

    const go = (changes: Query) => {
        const query = {
            vista: view,
            fecha: date,
            agrupar: filters.agrupar,
            profesional: filters.profesional,
            consultorio: filters.consultorio,
            ...changes,
        };
        const clean = Object.fromEntries(
            Object.entries(query).filter(([, v]) => v !== null && v !== undefined && v !== ''),
        );
        router.get(
            agenda.url({ query: clean }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const step = (direction: 1 | -1) => {
        if (view === 'mes') {
            go({ fecha: addMonths(date, direction) });
        } else {
            go({ fecha: addDays(date, view === 'semana' ? 7 * direction : direction) });
        }
    };

    const openCreate = (initial: Partial<AppointmentDraft> = {}) => {
        setEditing(null);
        setDraft({
            date: date < todayLocal() ? todayLocal() : date,
            time: nextFreeTime(config),
            ...initial,
        });
        setFormKey((k) => k + 1);
        setFormOpen(true);
    };

    // Llegada desde la ficha del paciente o la lista de espera: abrir el formulario precargado.
    useEffect(() => {
        if (prefill && can_manage) {
            openCreate({
                patient: prefill.patient,
                appointment_type_id: prefill.appointment_type_id ?? undefined,
                professional_id: prefill.professional_id ?? undefined,
                waiting_list_entry_id: prefill.waiting_list_entry_id,
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [prefill?.patient.id, prefill?.waiting_list_entry_id]);

    const columns = useMemo<GridColumn[]>(() => {
        const dayBlocks = (d: string) => blocks.filter((b) => overlapsDay(b, d)).map(withWho);

        if (view === 'dia') {
            const isGeneral = (b: ScheduleBlockItem) => !b.professional && !b.room;

            if (filters.agrupar === 'consultorio') {
                return rooms.map((r) => ({
                    key: `r${r.id}`,
                    label: r.name,
                    date,
                    defaults: { room_id: r.id },
                    appointments: appointments.filter((a) => a.room.id === r.id),
                    blocks: dayBlocks(date).filter((b) => isGeneral(b) || b.room?.id === r.id),
                }));
            }

            return professionals.map((p) => ({
                key: `p${p.id}`,
                label: p.name,
                date,
                defaults: { professional_id: p.id },
                appointments: appointments.filter((a) => a.professional.id === p.id),
                blocks: dayBlocks(date).filter((b) => isGeneral(b) || b.professional?.id === p.id),
            }));
        }

        const monday = startOfWeek(date);

        return Array.from({ length: 7 }, (_, i) => addDays(monday, i))
            .filter((d) => config.working_days.includes(isoWeekday(d)))
            .map((d) => ({
                key: d,
                label: dayLabel(d),
                date: d,
                defaults: {
                    professional_id: filters.profesional ?? undefined,
                    room_id: filters.consultorio ?? undefined,
                },
                appointments: appointments.filter((a) => splitDateTime(a.starts_at).date === d),
                blocks: dayBlocks(d),
            }));
    }, [view, filters, appointments, blocks, professionals, rooms, date, config.working_days]);

    const title =
        view === 'mes'
            ? monthLabel(date)
            : view === 'semana'
              ? `Semana del ${dayLabel(startOfWeek(date))} al ${dayLabel(addDays(startOfWeek(date), 6))}`
              : dayLabel(date, true);

    const isClosedDay =
        view !== 'mes' && view !== 'semana' && !config.working_days.includes(isoWeekday(date));
    // En la vista mensual la grilla muestra días de los meses vecinos: se cuentan solo los del mes.
    const active = appointments.filter(
        (a) =>
            a.status !== 'cancelada' &&
            (view !== 'mes' || a.starts_at.slice(0, 7) === date.slice(0, 7)),
    );

    return (
        <>
            <Head title="Agenda" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Agenda</h1>
                        <p
                            className="text-sm text-muted-foreground first-letter:uppercase"
                            aria-live="polite"
                        >
                            {title} · {active.length} {active.length === 1 ? 'cita' : 'citas'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can_manage && (
                            <>
                                <Button onClick={() => openCreate()}>
                                    <Plus aria-hidden="true" />
                                    Nueva cita
                                </Button>
                                <Button variant="outline" onClick={() => setBlockOpen(true)}>
                                    <Ban aria-hidden="true" />
                                    Bloquear horario
                                </Button>
                            </>
                        )}
                        <Button asChild variant="outline">
                            <Link href={waitingList.index()}>
                                <ListTodo aria-hidden="true" />
                                Lista de espera
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-xl border p-3">
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => step(-1)}
                            aria-label="Anterior"
                        >
                            <ChevronLeft aria-hidden="true" />
                        </Button>
                        <Button variant="outline" onClick={() => go({ fecha: todayLocal() })}>
                            Hoy
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => step(1)}
                            aria-label="Siguiente"
                        >
                            <ChevronRight aria-hidden="true" />
                        </Button>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="agenda-fecha" className="text-xs">
                            Ir a la fecha
                        </Label>
                        <Input
                            id="agenda-fecha"
                            type="date"
                            className="h-9 w-40"
                            value={date}
                            onChange={(e) => e.target.value && go({ fecha: e.target.value })}
                        />
                    </div>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={view}
                        onValueChange={(v) => v && go({ vista: v as AgendaView })}
                        aria-label="Vista"
                    >
                        <ToggleGroupItem value="dia">Día</ToggleGroupItem>
                        <ToggleGroupItem value="semana">Semana</ToggleGroupItem>
                        <ToggleGroupItem value="mes">Mes</ToggleGroupItem>
                        <ToggleGroupItem value="lista">Lista</ToggleGroupItem>
                    </ToggleGroup>

                    {view === 'dia' ? (
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={filters.agrupar}
                            onValueChange={(v) => v && go({ agrupar: v })}
                            aria-label="Columnas"
                        >
                            <ToggleGroupItem value="profesional">Por profesional</ToggleGroupItem>
                            <ToggleGroupItem value="consultorio">Por consultorio</ToggleGroupItem>
                        </ToggleGroup>
                    ) : (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="agenda-profesional" className="text-xs">
                                    Profesional
                                </Label>
                                <NativeSelect
                                    id="agenda-profesional"
                                    className="h-9 w-44"
                                    value={filters.profesional ?? ''}
                                    onChange={(e) =>
                                        go({
                                            profesional: e.target.value
                                                ? Number(e.target.value)
                                                : null,
                                        })
                                    }
                                >
                                    <option value="">Todos</option>
                                    {professionals.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="agenda-consultorio" className="text-xs">
                                    Consultorio
                                </Label>
                                <NativeSelect
                                    id="agenda-consultorio"
                                    className="h-9 w-40"
                                    value={filters.consultorio ?? ''}
                                    onChange={(e) =>
                                        go({
                                            consultorio: e.target.value
                                                ? Number(e.target.value)
                                                : null,
                                        })
                                    }
                                >
                                    <option value="">Todos</option>
                                    {rooms.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        </>
                    )}
                </div>

                {isClosedDay && (
                    <p className="rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground">
                        El consultorio no atiende este día.
                    </p>
                )}

                {(view === 'semana' || (view === 'dia' && !isClosedDay)) && (
                    <TimeGrid
                        columns={columns}
                        config={config}
                        canManage={can_manage}
                        onAppointment={setDetails}
                        onSlot={(col, time) =>
                            openCreate({
                                date: col.date,
                                time,
                                professional_id: col.defaults.professional_id,
                                room_id: col.defaults.room_id,
                            })
                        }
                    />
                )}

                {view === 'mes' && (
                    <MonthView
                        date={date}
                        appointments={appointments}
                        workingDays={config.working_days}
                        onAppointment={setDetails}
                        onDay={(d) => go({ vista: 'dia', fecha: d })}
                    />
                )}

                {view === 'lista' &&
                    (appointments.length === 0 ? (
                        <EmptyState icon={CalendarDays} title="No hay citas este día" />
                    ) : (
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">
                                    Citas del {dayLabel(date, true)}
                                </caption>
                                <thead className="border-b bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            Hora
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            Paciente
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-4 py-3 font-medium md:table-cell"
                                        >
                                            Tipo
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-4 py-3 font-medium md:table-cell"
                                        >
                                            Profesional
                                        </th>
                                        <th
                                            scope="col"
                                            className="hidden px-4 py-3 font-medium lg:table-cell"
                                        >
                                            Consultorio
                                        </th>
                                        <th scope="col" className="px-4 py-3 font-medium">
                                            Estado
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {appointments.map((a) => (
                                        <tr key={a.id} className="border-b last:border-0">
                                            <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                {splitDateTime(a.starts_at).time}–
                                                {splitDateTime(a.ends_at).time}
                                            </td>
                                            <td className="px-4 py-3">
                                                <button
                                                    type="button"
                                                    onClick={() => setDetails(a)}
                                                    className="flex items-center gap-1 font-medium text-primary underline-offset-4 hover:underline"
                                                >
                                                    {a.patient.has_allergies && (
                                                        <AlertTriangle
                                                            className="size-3.5 text-red-600"
                                                            aria-label="Con alergias"
                                                        />
                                                    )}
                                                    {a.patient.name}
                                                </button>
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                {a.type.name}
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                {a.professional.name}
                                            </td>
                                            <td className="hidden px-4 py-3 lg:table-cell">
                                                {a.room.name}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        a.status === 'cancelada' ||
                                                        a.status === 'no_asistio'
                                                            ? 'destructive'
                                                            : 'outline'
                                                    }
                                                >
                                                    {a.status_label}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ))}

                {(view === 'dia' || view === 'semana') && (
                    <p className="text-xs text-muted-foreground">
                        Haga clic en un horario libre para agendar. Con teclado, use el botón «Nueva
                        cita». Las citas canceladas se ven en la vista Lista.
                    </p>
                )}
            </div>

            {formOpen && (
                <AppointmentFormDialog
                    key={formKey}
                    open={formOpen}
                    onOpenChange={setFormOpen}
                    appointment={editing}
                    draft={draft}
                    professionals={professionals}
                    rooms={rooms}
                    types={types}
                    patientOptions={patient_options}
                    config={config}
                />
            )}

            <AppointmentDetailsDialog
                appointment={details}
                canManage={can_manage}
                onClose={() => setDetails(null)}
                onReschedule={(a) => {
                    setDetails(null);
                    setEditing(a);
                    setDraft({
                        date: splitDateTime(a.starts_at).date,
                        time: splitDateTime(a.starts_at).time,
                    });
                    setFormKey((k) => k + 1);
                    setFormOpen(true);
                }}
            />

            {blockOpen && (
                <BlockDialog
                    open={blockOpen}
                    onOpenChange={setBlockOpen}
                    date={date}
                    professionals={professionals}
                    rooms={rooms}
                />
            )}
        </>
    );
}

Agenda.layout = {
    breadcrumbs: [{ title: 'Agenda', href: agenda() }],
};
