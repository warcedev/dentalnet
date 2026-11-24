import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField } from '@/components/form-field';
import { OdontogramChart, OdontogramLegend } from '@/components/odontogram/odontogram-chart';
import { TimelineSlider } from '@/components/odontogram/timeline-slider';
import { ToothEventForm } from '@/components/odontogram/tooth-event-form';
import { PatientLayout } from '@/components/patients/patient-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDate, formatDateTime } from '@/lib/format';
import { describeTooth, STATUS_STYLES, surfaceLabel } from '@/lib/odontogram';
import { cn } from '@/lib/utils';
import patients from '@/routes/patients';
import type {
    Chart,
    ConditionOption,
    Dentition,
    Option,
    PatientHeader,
    Surface,
    ToothEventRow,
    ToothInfo,
} from '@/types';
import { Form, Head, router } from '@inertiajs/react';
import { CalendarClock, History, Sparkles } from 'lucide-react';
import { useMemo, useRef, useState, type ReactNode } from 'react';

type Props = {
    header: PatientHeader;
    as_of: string;
    is_today: boolean;
    chart: Chart;
    teeth: ToothInfo[];
    timeline: string[];
    events: ToothEventRow[];
    insights: {
        last_cleaning: string | null;
        next_cleaning: string | null;
        cleaning_overdue: boolean;
        last_by_tooth: Record<
            string,
            { date: string; condition: string; surface: string | null; event_id: number }
        >;
        repairs: {
            id: number;
            date: string;
            tooth_code: number | null;
            surface: string | null;
            condition: string;
            professional: string | null;
        }[];
    };
    options: { conditions: ConditionOption[]; statuses: Option[]; surfaces: Surface[] };
    can_record: boolean;
};

const today = () => new Date().toLocaleDateString('en-CA', { timeZone: 'America/Asuncion' });

function defaultDentition(age: number): Dentition {
    if (age < 6) {
        return 'temporal';
    }

    return age <= 12 ? 'mixta' : 'permanente';
}

function StatusDot({ status }: { status: keyof typeof STATUS_STYLES }) {
    return (
        <span
            className="inline-block size-2.5 shrink-0 rounded-full"
            style={{ background: STATUS_STYLES[status].fill }}
            aria-hidden="true"
        />
    );
}

function Section({
    title,
    description,
    icon,
    children,
    className,
}: {
    title: string;
    description?: ReactNode;
    icon?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    {icon}
                    <h2>{title}</h2>
                </CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent className="grid gap-4">{children}</CardContent>
        </Card>
    );
}

function EventItem({
    event,
    header,
    options,
    canCorrect,
}: {
    event: ToothEventRow;
    header: PatientHeader;
    options: Props['options'];
    canCorrect: boolean;
}) {
    const [correcting, setCorrecting] = useState(false);
    const inactive = event.is_superseded || event.is_void;
    const where =
        event.tooth_code === null
            ? 'Boca completa'
            : `Diente ${event.tooth_code}${event.surface_label ? ` · ${event.surface_label}` : ''}`;

    return (
        <li
            className={cn(
                'grid gap-1 rounded-md border px-3 py-2 text-sm',
                inactive && 'border-dashed opacity-70',
            )}
        >
            <div className="flex flex-wrap items-center gap-2">
                <StatusDot status={event.status} />
                <span className={cn('font-medium', inactive && 'line-through')}>
                    {event.is_void ? 'Anulación' : event.condition_label}
                </span>
                <span className="text-muted-foreground">
                    {where} · {event.status_label}
                </span>
                {event.is_superseded && <Badge variant="secondary">Corregido</Badge>}
                {event.corrects_event_id && !event.is_void && (
                    <Badge variant="outline">Corrección de #{event.corrects_event_id}</Badge>
                )}
            </div>
            <p className="text-xs text-muted-foreground">
                Detectado {formatDate(event.detected_on)}
                {event.treated_on && ` · Tratado ${formatDate(event.treated_on)}`} ·{' '}
                {event.professional ?? '—'} · cargado {formatDateTime(event.created_at)} · #
                {event.id}
            </p>
            {event.notes && <p className="text-xs">{event.notes}</p>}
            {event.correction_reason && (
                <p className="text-xs">Motivo: {event.correction_reason}</p>
            )}

            {canCorrect && !inactive && event.tooth_code !== null && (
                <div className="flex flex-wrap gap-2 pt-1">
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setCorrecting((v) => !v)}
                        aria-expanded={correcting}
                    >
                        Corregir<span className="sr-only"> registro {event.id}</span>
                    </Button>
                    <ConfirmDialog
                        trigger={
                            <Button size="sm" variant="ghost">
                                Anular<span className="sr-only"> registro {event.id}</span>
                            </Button>
                        }
                        title="¿Anular este registro?"
                        description="Use esta opción si el registro se cargó por error. No se borra: queda en el historial marcado como anulado."
                        confirmLabel="Anular registro"
                        reasonLabel="Motivo"
                        hidden={{ void: '1' }}
                        form={patients.odontogram.correct.form({
                            patient: header.id,
                            toothEvent: event.id,
                        })}
                        destructive
                    />
                </div>
            )}

            {correcting && event.tooth_code !== null && (
                <div className="mt-2 rounded-md bg-muted/40 p-3">
                    <ToothEventForm
                        form={patients.odontogram.correct.form({
                            patient: header.id,
                            toothEvent: event.id,
                        })}
                        toothCode={event.tooth_code}
                        defaults={{
                            surface: event.surface,
                            condition: event.condition,
                            status: event.status,
                            detected_on: event.detected_on,
                            treated_on: event.treated_on,
                            notes: event.notes,
                        }}
                        conditions={options.conditions}
                        statuses={options.statuses}
                        withReason
                        submitLabel="Guardar corrección"
                        onSuccess={() => setCorrecting(false)}
                        onCancel={() => setCorrecting(false)}
                    />
                </div>
            )}
        </li>
    );
}

export default function Odontogram(props: Props) {
    const {
        header,
        as_of,
        is_today,
        chart,
        teeth,
        timeline,
        events,
        insights,
        options,
        can_record,
    } = props;
    const [dentition, setDentition] = useState<Dentition>(defaultDentition(header.age));
    const [selected, setSelected] = useState<number | null>(null);
    const [surface, setSurface] = useState<Surface | null>(null);
    const [loading, setLoading] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const infoByCode = useMemo(() => new Map(teeth.map((t) => [t.code, t])), [teeth]);

    const changeDate = (date: string | null) => {
        if (timer.current) {
            clearTimeout(timer.current);
        }

        timer.current = setTimeout(() => {
            router.get(
                patients.odontogram.url(header.id, date ? { query: { fecha: date } } : undefined),
                {},
                {
                    only: ['chart', 'as_of', 'is_today'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onStart: () => setLoading(true),
                    onFinish: () => setLoading(false),
                },
            );
        }, 200);
    };

    const selectedState = selected !== null ? chart[String(selected)] : undefined;
    const selectedEvents = selected !== null ? events.filter((e) => e.tooth_code === selected) : [];
    const lastIntervention =
        selected !== null ? insights.last_by_tooth[String(selected)] : undefined;
    const currentFindings = Object.entries(chart).flatMap(([code, state]) => [
        ...state.tooth.map((e) => ({ code: Number(code), where: 'Diente completo', entry: e })),
        ...Object.values(state.surfaces).map((e) => ({
            code: Number(code),
            where: e?.surface ? surfaceLabel(Number(code), e.surface) : '',
            entry: e!,
        })),
    ]);

    return (
        <>
            <Head title={`Odontograma — ${header.full_name}`} />
            <PatientLayout header={header}>
                <div className="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                    <Section
                        title={
                            is_today ? 'Odontograma actual' : `Odontograma al ${formatDate(as_of)}`
                        }
                        description={
                            is_today
                                ? 'Seleccione un diente (o una superficie con el mouse) para ver su historial y registrar.'
                                : 'Está viendo un estado pasado reconstruido a partir del historial. No se puede registrar en esta vista.'
                        }
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <ToggleGroup
                                type="single"
                                value={dentition}
                                onValueChange={(v) => v && setDentition(v as Dentition)}
                                aria-label="Dentición"
                                variant="outline"
                                size="sm"
                            >
                                <ToggleGroupItem value="permanente">Permanente</ToggleGroupItem>
                                <ToggleGroupItem value="mixta">Mixta</ToggleGroupItem>
                                <ToggleGroupItem value="temporal">Temporal</ToggleGroupItem>
                            </ToggleGroup>
                            {!is_today && <Badge variant="secondary">Vista histórica</Badge>}
                        </div>

                        <div
                            className={cn(
                                'overflow-x-auto rounded-lg border bg-card p-2 transition-opacity',
                                loading && 'opacity-60',
                            )}
                            aria-busy={loading}
                        >
                            <OdontogramChart
                                teeth={teeth}
                                chart={chart}
                                dentition={dentition}
                                selected={selected}
                                selectedSurface={surface}
                                onSelect={(code, s) => {
                                    setSelected(code);
                                    setSurface(s);
                                }}
                            />
                        </div>
                        <OdontogramLegend />
                        <TimelineSlider
                            dates={timeline}
                            asOf={as_of}
                            isToday={is_today}
                            onChange={changeDate}
                            loading={loading}
                        />

                        <details className="text-sm">
                            <summary className="cursor-pointer text-muted-foreground">
                                Ver el estado en forma de tabla ({currentFindings.length} hallazgos)
                            </summary>
                            {currentFindings.length === 0 ? (
                                <p className="mt-2 text-muted-foreground">
                                    Sin hallazgos a esta fecha.
                                </p>
                            ) : (
                                <table className="mt-2 w-full text-left text-sm">
                                    <caption className="sr-only">
                                        Hallazgos del odontograma al {formatDate(as_of)}
                                    </caption>
                                    <thead className="text-muted-foreground">
                                        <tr>
                                            <th scope="col" className="py-1 pr-2 font-medium">
                                                Diente
                                            </th>
                                            <th scope="col" className="py-1 pr-2 font-medium">
                                                Zona
                                            </th>
                                            <th scope="col" className="py-1 pr-2 font-medium">
                                                Condición
                                            </th>
                                            <th scope="col" className="py-1 pr-2 font-medium">
                                                Estado
                                            </th>
                                            <th scope="col" className="py-1 font-medium">
                                                Desde
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {currentFindings.map(({ code, where, entry }) => (
                                            <tr key={entry.id} className="border-t">
                                                <td className="py-1 pr-2 tabular-nums">{code}</td>
                                                <td className="py-1 pr-2">{where}</td>
                                                <td className="py-1 pr-2">
                                                    {entry.condition_label}
                                                </td>
                                                <td className="py-1 pr-2">{entry.status_label}</td>
                                                <td className="py-1">{formatDate(entry.date)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </details>
                    </Section>

                    <div className="grid content-start gap-4">
                        <Section
                            title={
                                selected === null
                                    ? 'Diente'
                                    : `Diente ${selected}${surface ? ` · ${surfaceLabel(selected, surface)}` : ''}`
                            }
                            description={
                                selected === null
                                    ? 'Seleccione un diente en el odontograma.'
                                    : undefined
                            }
                        >
                            {selected !== null && (
                                <>
                                    <p className="text-sm" aria-live="polite">
                                        {describeTooth(
                                            selected,
                                            infoByCode.get(selected),
                                            selectedState,
                                        )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Última intervención:{' '}
                                        {lastIntervention
                                            ? `${lastIntervention.condition}${lastIntervention.surface ? ` (${lastIntervention.surface})` : ''} el ${formatDate(lastIntervention.date)}`
                                            : 'sin intervenciones registradas'}
                                    </p>

                                    {can_record && is_today && (
                                        <div className="rounded-md border p-3">
                                            <h3 className="mb-2 text-sm font-semibold">
                                                Registrar
                                            </h3>
                                            <ToothEventForm
                                                key={`${selected}-${surface}`}
                                                form={patients.odontogram.store.form(header.id)}
                                                toothCode={selected}
                                                defaults={{ surface }}
                                                conditions={options.conditions}
                                                statuses={options.statuses}
                                                submitLabel="Guardar registro"
                                            />
                                        </div>
                                    )}
                                    {can_record && !is_today && (
                                        <p className="text-sm text-muted-foreground">
                                            Vuelva a la vista de hoy para registrar.
                                        </p>
                                    )}

                                    <div className="grid gap-2">
                                        <h3 className="flex items-center gap-2 text-sm font-semibold">
                                            <History className="size-4" aria-hidden="true" />
                                            Historial del diente
                                        </h3>
                                        {selectedEvents.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                Sin registros.
                                            </p>
                                        ) : (
                                            <ol className="grid gap-2">
                                                {selectedEvents.map((e) => (
                                                    <EventItem
                                                        key={e.id}
                                                        event={e}
                                                        header={header}
                                                        options={options}
                                                        canCorrect={can_record}
                                                    />
                                                ))}
                                            </ol>
                                        )}
                                    </div>
                                </>
                            )}
                        </Section>

                        <Section
                            title="Limpieza"
                            icon={<Sparkles className="size-4" aria-hidden="true" />}
                        >
                            <dl className="grid grid-cols-2 gap-2 text-sm">
                                <dt className="text-muted-foreground">Última</dt>
                                <dd>{formatDate(insights.last_cleaning, 'Sin registro')}</dd>
                                <dt className="text-muted-foreground">Próxima recomendada</dt>
                                <dd className="flex items-center gap-2">
                                    {formatDate(insights.next_cleaning, '—')}
                                    {insights.cleaning_overdue && (
                                        <Badge variant="destructive">Vencida</Badge>
                                    )}
                                </dd>
                            </dl>
                            {can_record && (
                                <Form
                                    {...patients.odontogram.store.form(header.id)}
                                    options={{ preserveScroll: true }}
                                    transform={(data) => ({
                                        ...data,
                                        detected_on: data.treated_on,
                                    })}
                                    className="flex flex-wrap items-end gap-2"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="condition"
                                                value="limpieza"
                                            />
                                            <input type="hidden" name="status" value="tratado" />
                                            <div className="min-w-40 flex-1">
                                                <FormField
                                                    id="cleaning-date"
                                                    label="Fecha de la limpieza"
                                                    error={errors.treated_on ?? errors.condition}
                                                >
                                                    {(p) => (
                                                        <Input
                                                            {...p}
                                                            type="date"
                                                            name="treated_on"
                                                            max={today()}
                                                            defaultValue={today()}
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
                                                {processing && <Spinner />}
                                                Registrar limpieza
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </Section>

                        <Section
                            title="Arreglos realizados"
                            icon={<CalendarClock className="size-4" aria-hidden="true" />}
                        >
                            {insights.repairs.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Todavía no hay tratamientos realizados.
                                </p>
                            ) : (
                                <ul className="grid gap-1 text-sm">
                                    {insights.repairs.map((r) => (
                                        <li
                                            key={r.id}
                                            className="flex justify-between gap-2 border-b py-1 last:border-0"
                                        >
                                            <span>
                                                {r.tooth_code
                                                    ? `${r.tooth_code}${r.surface ? ` ${r.surface}` : ''} · `
                                                    : ''}
                                                {r.condition}
                                            </span>
                                            <span className="text-muted-foreground tabular-nums">
                                                {formatDate(r.date)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Section>
                    </div>
                </div>

                <details className="rounded-xl border p-4">
                    <summary className="cursor-pointer font-medium">
                        Historial completo ({events.length} registros)
                    </summary>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Los registros nunca se modifican ni se borran: las correcciones y
                        anulaciones se agregan como registros nuevos.
                    </p>
                    {events.length === 0 ? (
                        <p className="mt-3 text-sm text-muted-foreground">Sin registros.</p>
                    ) : (
                        <ol className="mt-3 grid gap-2">
                            {events.map((e) => (
                                <EventItem
                                    key={e.id}
                                    event={e}
                                    header={header}
                                    options={options}
                                    canCorrect={can_record}
                                />
                            ))}
                        </ol>
                    )}
                </details>
            </PatientLayout>
        </>
    );
}

Odontogram.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
