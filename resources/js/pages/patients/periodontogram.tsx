import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { PatientLayout } from '@/components/patients/patient-layout';
import { EvolutionChart } from '@/components/periodontal/evolution-chart';
import { Textarea } from '@/components/textarea';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { todayLocal } from '@/lib/agenda';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import patients from '@/routes/patients';
import type { PatientHeader } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Activity, Plus } from 'lucide-react';
import { useState } from 'react';

type ToothData = {
    is_missing: boolean;
    probing_depth: (number | null)[] | null;
    recession: (number | null)[] | null;
    bleeding: boolean[] | null;
    plaque: boolean[] | null;
    mobility: number | null;
};

type Summary = {
    teeth: number;
    sites: number;
    bleeding_pct: number;
    plaque_pct: number;
    mean_depth: number;
    sites_4_plus: number;
    sites_6_plus: number;
};

type Props = {
    header: PatientHeader;
    teeth: number[];
    records: { id: number; recorded_on: string; professional: string; summary: Summary }[];
    selected: {
        id: number;
        recorded_on: string;
        professional: string;
        notes: string | null;
        measurements: Record<string, ToothData>;
    } | null;
    can_record: boolean;
};

const UPPER = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
const LOWER = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
const BUCCAL = [0, 1, 2];
const LINGUAL = [3, 4, 5];
const SITE_NAMES = [
    'mesiovestibular',
    'vestibular',
    'distovestibular',
    'mesiolingual',
    'lingual',
    'distolingual',
];

const blank = (missing = false): ToothData => ({
    is_missing: missing,
    probing_depth: [null, null, null, null, null, null],
    recession: [null, null, null, null, null, null],
    bleeding: [false, false, false, false, false, false],
    plaque: [false, false, false, false, false, false],
    mobility: null,
});

const depthClass = (d: number | null | undefined) =>
    d == null
        ? ''
        : d >= 6
          ? 'bg-red-600 text-white'
          : d >= 4
            ? 'bg-amber-300 text-amber-950 dark:bg-amber-700 dark:text-amber-50'
            : '';

/** Vista de solo lectura del examen seleccionado. */
function RecordTable({
    arch,
    measurements,
}: {
    arch: number[];
    measurements: Record<string, ToothData>;
}) {
    const rows: { label: string; sites: number[]; kind: 'depth' | 'bleed' }[] = [
        { label: 'Sondaje vestibular', sites: BUCCAL, kind: 'depth' },
        { label: 'Sangrado / placa vestibular', sites: BUCCAL, kind: 'bleed' },
        { label: 'Sondaje palatino / lingual', sites: LINGUAL, kind: 'depth' },
        { label: 'Sangrado / placa palatino / lingual', sites: LINGUAL, kind: 'bleed' },
    ];

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[60rem] border-collapse text-center text-xs">
                <thead>
                    <tr>
                        <th
                            scope="col"
                            className="w-40 py-1 text-left font-medium text-muted-foreground"
                        >
                            Pieza
                        </th>
                        {arch.map((c) => (
                            <th key={c} scope="col" className="py-1 font-medium tabular-nums">
                                {c}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th
                            scope="row"
                            className="py-1 text-left font-normal text-muted-foreground"
                        >
                            Movilidad
                        </th>
                        {arch.map((c) => (
                            <td key={c} className="py-1 tabular-nums">
                                {measurements[c]?.is_missing
                                    ? '—'
                                    : (measurements[c]?.mobility ?? '')}
                            </td>
                        ))}
                    </tr>
                    {rows.map((row) => (
                        <tr key={row.label} className="border-t">
                            <th
                                scope="row"
                                className="py-1 text-left font-normal text-muted-foreground"
                            >
                                {row.label}
                            </th>
                            {arch.map((c) => {
                                const m = measurements[c];

                                if (m?.is_missing) {
                                    return (
                                        <td
                                            key={c}
                                            className="bg-muted/50 py-1 text-muted-foreground"
                                        >
                                            <span className="sr-only">Ausente</span>✕
                                        </td>
                                    );
                                }

                                return (
                                    <td key={c} className="py-1">
                                        <span className="inline-flex gap-0.5">
                                            {row.sites.map((s) =>
                                                row.kind === 'depth' ? (
                                                    <span
                                                        key={s}
                                                        className={cn(
                                                            'inline-block w-4 rounded tabular-nums',
                                                            depthClass(m?.probing_depth?.[s]),
                                                        )}
                                                    >
                                                        {m?.probing_depth?.[s] ?? '·'}
                                                    </span>
                                                ) : (
                                                    <span
                                                        key={s}
                                                        className="inline-flex w-4 justify-center gap-px"
                                                        aria-label={`${SITE_NAMES[s]}: ${m?.bleeding?.[s] ? 'sangra' : 'no sangra'}, ${m?.plaque?.[s] ? 'con placa' : 'sin placa'}`}
                                                    >
                                                        <span
                                                            className={cn(
                                                                'size-1.5 rounded-full',
                                                                m?.bleeding?.[s]
                                                                    ? 'bg-red-600'
                                                                    : 'bg-border',
                                                            )}
                                                        />
                                                        <span
                                                            className={cn(
                                                                'size-1.5 rounded-full',
                                                                m?.plaque?.[s]
                                                                    ? 'bg-sky-600'
                                                                    : 'bg-border',
                                                            )}
                                                        />
                                                    </span>
                                                ),
                                            )}
                                        </span>
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Grilla de carga de un arco (16 piezas). */
function EntryGrid({
    arch,
    data,
    onChange,
}: {
    arch: number[];
    data: Record<number, ToothData>;
    onChange: (code: number, value: ToothData) => void;
}) {
    const set = (code: number, field: 'probing_depth' | 'recession', site: number, raw: string) => {
        const t = data[code];
        const list = [...(t[field] ?? [])];
        list[site] =
            raw === '' ? null : Math.max(field === 'recession' ? -5 : 0, Math.min(15, Number(raw)));
        onChange(code, { ...t, [field]: list });
    };
    const toggle = (code: number, field: 'bleeding' | 'plaque', site: number) => {
        const t = data[code];
        const list = [...(t[field] ?? [])];
        list[site] = !list[site];
        onChange(code, { ...t, [field]: list });
    };

    const numberRow = (label: string, field: 'probing_depth' | 'recession', sites: number[]) => (
        <tr className="border-t">
            <th
                scope="row"
                className="py-1 pr-2 text-left font-normal whitespace-nowrap text-muted-foreground"
            >
                {label}
            </th>
            {arch.map((c) => (
                <td key={c} className="py-1">
                    {!data[c].is_missing && (
                        <span className="inline-flex gap-0.5">
                            {sites.map((s) => (
                                <input
                                    key={s}
                                    type="number"
                                    inputMode="numeric"
                                    min={field === 'recession' ? -5 : 0}
                                    max={15}
                                    aria-label={`Pieza ${c}, ${SITE_NAMES[s]}: ${label.toLowerCase()}`}
                                    value={data[c][field]?.[s] ?? ''}
                                    onChange={(e) => set(c, field, s, e.target.value)}
                                    className={cn(
                                        'h-6 w-6 [appearance:textfield] rounded border border-input bg-background p-0 text-center text-xs tabular-nums focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-inner-spin-button]:appearance-none',
                                        field === 'probing_depth' &&
                                            depthClass(data[c][field]?.[s]),
                                    )}
                                />
                            ))}
                        </span>
                    )}
                </td>
            ))}
        </tr>
    );

    const toggleRow = (label: string, field: 'bleeding' | 'plaque', sites: number[]) => (
        <tr>
            <th
                scope="row"
                className="py-1 pr-2 text-left font-normal whitespace-nowrap text-muted-foreground"
            >
                {label}
            </th>
            {arch.map((c) => (
                <td key={c} className="py-1">
                    {!data[c].is_missing && (
                        <span className="inline-flex gap-0.5">
                            {sites.map((s) => {
                                const on = !!data[c][field]?.[s];

                                return (
                                    <button
                                        key={s}
                                        type="button"
                                        aria-pressed={on}
                                        aria-label={`Pieza ${c}, ${SITE_NAMES[s]}: ${field === 'bleeding' ? 'sangrado' : 'placa'}`}
                                        onClick={() => toggle(c, field, s)}
                                        className={cn(
                                            'size-6 rounded border text-[10px] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            on
                                                ? field === 'bleeding'
                                                    ? 'border-red-600 bg-red-600 text-white'
                                                    : 'border-sky-600 bg-sky-600 text-white'
                                                : 'border-input bg-background',
                                        )}
                                    >
                                        {on ? (field === 'bleeding' ? 'S' : 'P') : ''}
                                    </button>
                                );
                            })}
                        </span>
                    )}
                </td>
            ))}
        </tr>
    );

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[66rem] border-collapse text-center text-xs">
                <thead>
                    <tr>
                        <th
                            scope="col"
                            className="py-1 text-left font-medium text-muted-foreground"
                        >
                            Pieza
                        </th>
                        {arch.map((c) => (
                            <th key={c} scope="col" className="py-1 font-semibold tabular-nums">
                                {c}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th
                            scope="row"
                            className="py-1 pr-2 text-left font-normal text-muted-foreground"
                        >
                            Ausente
                        </th>
                        {arch.map((c) => (
                            <td key={c} className="py-1">
                                <input
                                    type="checkbox"
                                    aria-label={`Pieza ${c} ausente`}
                                    checked={data[c].is_missing}
                                    onChange={(e) =>
                                        onChange(c, e.target.checked ? blank(true) : blank(false))
                                    }
                                    className="size-4 accent-[var(--primary)]"
                                />
                            </td>
                        ))}
                    </tr>
                    <tr>
                        <th
                            scope="row"
                            className="py-1 pr-2 text-left font-normal text-muted-foreground"
                        >
                            Movilidad (0–3)
                        </th>
                        {arch.map((c) => (
                            <td key={c} className="py-1">
                                {!data[c].is_missing && (
                                    <input
                                        type="number"
                                        min={0}
                                        max={3}
                                        aria-label={`Pieza ${c}: movilidad`}
                                        value={data[c].mobility ?? ''}
                                        onChange={(e) =>
                                            onChange(c, {
                                                ...data[c],
                                                mobility:
                                                    e.target.value === ''
                                                        ? null
                                                        : Math.max(
                                                              0,
                                                              Math.min(3, Number(e.target.value)),
                                                          ),
                                            })
                                        }
                                        className="h-6 w-8 [appearance:textfield] rounded border border-input bg-background p-0 text-center text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                )}
                            </td>
                        ))}
                    </tr>
                    {numberRow('Sondaje V (mm)', 'probing_depth', BUCCAL)}
                    {numberRow('Recesión V (mm)', 'recession', BUCCAL)}
                    {toggleRow('Sangrado V', 'bleeding', BUCCAL)}
                    {toggleRow('Placa V', 'plaque', BUCCAL)}
                    {numberRow('Sondaje P/L (mm)', 'probing_depth', LINGUAL)}
                    {numberRow('Recesión P/L (mm)', 'recession', LINGUAL)}
                    {toggleRow('Sangrado P/L', 'bleeding', LINGUAL)}
                    {toggleRow('Placa P/L', 'plaque', LINGUAL)}
                </tbody>
            </table>
        </div>
    );
}

function NewExam({
    header,
    teeth,
    previous,
    onDone,
}: {
    header: PatientHeader;
    teeth: number[];
    previous: Props['selected'];
    onDone: () => void;
}) {
    // Las piezas ausentes del examen anterior se precargan; el resto se mide de nuevo.
    const form = useForm<{ recorded_on: string; notes: string; teeth: Record<number, ToothData> }>({
        recorded_on: todayLocal(),
        notes: '',
        teeth: Object.fromEntries(
            teeth.map((c) => [c, blank(!!previous?.measurements[c]?.is_missing)]),
        ),
    });
    const update = (code: number, value: ToothData) =>
        form.setData('teeth', { ...form.data.teeth, [code]: value });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    <h2>Nuevo examen periodontal</h2>
                </CardTitle>
                <CardDescription>
                    Seis puntos por pieza: mesial, medio y distal, por vestibular y por
                    palatino/lingual. Profundidades de 4 mm o más se marcan en amarillo y de 6 mm o
                    más en rojo. Deje vacías las piezas que no evaluó.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(patients.periodontogram.store.url(header.id), {
                            preserveScroll: true,
                            onSuccess: onDone,
                        });
                    }}
                    className="grid gap-4"
                >
                    <div className="grid gap-3 sm:grid-cols-[12rem_1fr]">
                        <FormField
                            id="perio-fecha"
                            label="Fecha del examen"
                            error={form.errors.recorded_on}
                            required
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    type="date"
                                    max={todayLocal()}
                                    value={form.data.recorded_on}
                                    onChange={(e) => form.setData('recorded_on', e.target.value)}
                                />
                            )}
                        </FormField>
                        <FormField id="perio-notas" label="Notas" error={form.errors.notes}>
                            {(p) => (
                                <Textarea
                                    {...p}
                                    rows={1}
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                />
                            )}
                        </FormField>
                    </div>
                    <section aria-label="Maxilar superior" className="grid gap-1">
                        <h3 className="text-sm font-semibold">Maxilar superior</h3>
                        <EntryGrid arch={UPPER} data={form.data.teeth} onChange={update} />
                    </section>
                    <section aria-label="Maxilar inferior" className="grid gap-1">
                        <h3 className="text-sm font-semibold">Maxilar inferior</h3>
                        <EntryGrid arch={LOWER} data={form.data.teeth} onChange={update} />
                    </section>
                    <InputError
                        message={
                            form.errors.teeth ??
                            Object.entries(form.errors).find(([k]) => k.startsWith('teeth.'))?.[1]
                        }
                    />
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Guardar examen
                        </Button>
                        <Button type="button" variant="ghost" onClick={onDone}>
                            Cancelar
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

export default function Periodontogram({ header, teeth, records, selected, can_record }: Props) {
    const [creating, setCreating] = useState(false);
    const latest = records[records.length - 1];

    return (
        <>
            <Head title={`Periodontograma — ${header.full_name}`} />
            <PatientLayout
                header={header}
                actions={
                    can_record &&
                    !creating && (
                        <Button onClick={() => setCreating(true)}>
                            <Plus aria-hidden="true" />
                            Nuevo examen
                        </Button>
                    )
                }
            >
                {creating && (
                    <NewExam
                        header={header}
                        teeth={teeth}
                        previous={selected}
                        onDone={() => setCreating(false)}
                    />
                )}

                {records.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 py-10 text-center">
                            <Activity
                                className="size-10 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p className="font-medium">Sin exámenes periodontales</p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {latest && (
                            <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {[
                                    {
                                        label: 'Sangrado al sondaje',
                                        value: `${latest.summary.bleeding_pct.toLocaleString('es-PY')} %`,
                                    },
                                    {
                                        label: 'Índice de placa',
                                        value: `${latest.summary.plaque_pct.toLocaleString('es-PY')} %`,
                                    },
                                    {
                                        label: 'Profundidad media',
                                        value: `${latest.summary.mean_depth.toLocaleString('es-PY')} mm`,
                                    },
                                    {
                                        label: 'Sitios ≥ 4 mm / ≥ 6 mm',
                                        value: `${latest.summary.sites_4_plus} / ${latest.summary.sites_6_plus}`,
                                    },
                                ].map((k) => (
                                    <div key={k.label} className="rounded-xl border p-4">
                                        <dt className="text-sm text-muted-foreground">{k.label}</dt>
                                        <dd className="text-xl font-semibold tabular-nums">
                                            {k.value}
                                        </dd>
                                        <dd className="text-xs text-muted-foreground">
                                            Último examen, {formatDate(latest.recorded_on)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}

                        {records.length > 1 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        <h2>Evolución</h2>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-6 md:grid-cols-2">
                                    <EvolutionChart
                                        title="Sangrado al sondaje"
                                        unit=" %"
                                        minMax={20}
                                        points={records.map((r) => ({
                                            date: r.recorded_on,
                                            value: r.summary.bleeding_pct,
                                        }))}
                                    />
                                    <EvolutionChart
                                        title="Profundidad de sondaje media"
                                        unit=" mm"
                                        minMax={4}
                                        points={records.map((r) => ({
                                            date: r.recorded_on,
                                            value: r.summary.mean_depth,
                                        }))}
                                    />
                                </CardContent>
                            </Card>
                        )}

                        {selected && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        <h2>Examen del {formatDate(selected.recorded_on)}</h2>
                                    </CardTitle>
                                    <CardDescription>
                                        {selected.professional}
                                        {selected.notes && ` · ${selected.notes}`} · Sondaje:
                                        amarillo ≥ 4 mm, rojo ≥ 6 mm. Puntos: rojo sangrado, azul
                                        placa.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <section aria-label="Maxilar superior">
                                        <RecordTable
                                            arch={UPPER}
                                            measurements={selected.measurements}
                                        />
                                    </section>
                                    <section aria-label="Maxilar inferior">
                                        <RecordTable
                                            arch={LOWER}
                                            measurements={selected.measurements}
                                        />
                                    </section>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    <h2>Exámenes</h2>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <caption className="sr-only">
                                            Exámenes periodontales con sus indicadores
                                        </caption>
                                        <thead className="border-b text-muted-foreground">
                                            <tr>
                                                <th scope="col" className="py-2 pr-3 font-medium">
                                                    Fecha
                                                </th>
                                                <th scope="col" className="py-2 pr-3 font-medium">
                                                    Profesional
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="py-2 pr-3 text-right font-medium"
                                                >
                                                    Sangrado
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="py-2 pr-3 text-right font-medium"
                                                >
                                                    Placa
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="py-2 pr-3 text-right font-medium"
                                                >
                                                    Prof. media
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="py-2 text-right font-medium"
                                                >
                                                    ≥ 4 / ≥ 6 mm
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {[...records].reverse().map((r) => (
                                                <tr
                                                    key={r.id}
                                                    className={cn(
                                                        'border-b last:border-0',
                                                        selected?.id === r.id && 'bg-accent',
                                                    )}
                                                >
                                                    <td className="py-2 pr-3">
                                                        <Link
                                                            href={patients.periodontogram(
                                                                header.id,
                                                                { query: { registro: r.id } },
                                                            )}
                                                            preserveScroll
                                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                                            aria-current={
                                                                selected?.id === r.id
                                                                    ? 'true'
                                                                    : undefined
                                                            }
                                                        >
                                                            {formatDate(r.recorded_on)}
                                                        </Link>
                                                    </td>
                                                    <td className="py-2 pr-3">{r.professional}</td>
                                                    <td className="py-2 pr-3 text-right tabular-nums">
                                                        {r.summary.bleeding_pct.toLocaleString(
                                                            'es-PY',
                                                        )}{' '}
                                                        %
                                                    </td>
                                                    <td className="py-2 pr-3 text-right tabular-nums">
                                                        {r.summary.plaque_pct.toLocaleString(
                                                            'es-PY',
                                                        )}{' '}
                                                        %
                                                    </td>
                                                    <td className="py-2 pr-3 text-right tabular-nums">
                                                        {r.summary.mean_depth.toLocaleString(
                                                            'es-PY',
                                                        )}{' '}
                                                        mm
                                                    </td>
                                                    <td className="py-2 text-right tabular-nums">
                                                        {r.summary.sites_4_plus} /{' '}
                                                        {r.summary.sites_6_plus}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}
            </PatientLayout>
        </>
    );
}

Periodontogram.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
