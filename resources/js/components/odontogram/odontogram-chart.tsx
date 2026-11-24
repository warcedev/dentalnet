import {
    describeTooth,
    neighbour,
    rowsFor,
    sideOf,
    STATUS_STYLES,
    SURFACES,
    TOOTH_ABBREVIATIONS,
} from '@/lib/odontogram';
import { IMPLANT, occlusalPaths, postPath, shapeFor, TOOTH_BOX } from '@/lib/tooth-shapes';
import type { Chart, ChartEntry, Dentition, Surface, ToothInfo, ToothState } from '@/types';
import { useRef, useState, type KeyboardEvent } from 'react';

const CELL = 46;
const ROW_HEIGHT = 142;
const BLOCK_GAP = 12;
const MIDLINE_GAP = 16;

/** Colores del diente sano (iguales en tema claro y oscuro, como un diente real). */
const ENAMEL = '#fdfbf6';
const ROOT = '#f1e6cf';
const OUTLINE = '#8b8478';

type Props = {
    teeth: ToothInfo[];
    chart: Chart;
    dentition: Dentition;
    selected: number | null;
    selectedSurface: Surface | null;
    onSelect: (code: number, surface: Surface | null) => void;
};

const colorOf = (entry: ChartEntry | undefined) =>
    entry ? STATUS_STYLES[entry.status].fill : undefined;

/** Diente en vista vestibular con sus tratamientos de diente completo. */
function AnatomicalTooth({ info, state }: { info: ToothInfo; state: ToothState | undefined }) {
    const shape = shapeFor(info);
    const find = (condition: string) => state?.tooth.find((e) => e.condition === condition);

    const crown = find('corona');
    const rootCanal = find('endodoncia');
    const implant = find('implante');
    const post = find('perno');
    const bridge = find('puente');
    const prosthesis = find('protesis');
    const extraction = find('extraccion');
    const absent = state?.absent ?? false;
    const showImplant = implant && !absent;

    return (
        <>
            <g opacity={absent ? 0.3 : 1}>
                {showImplant ? (
                    <g>
                        <path
                            d={IMPLANT.body}
                            fill="#cbd5e1"
                            stroke={colorOf(implant)}
                            strokeWidth={1.6}
                        />
                        {IMPLANT.threads.map((d) => (
                            <path key={d} d={d} stroke={colorOf(implant)} strokeWidth={1} />
                        ))}
                    </g>
                ) : (
                    shape.roots.map((d) => (
                        <path key={d} d={d} fill={ROOT} stroke={OUTLINE} strokeWidth={1} />
                    ))
                )}

                {rootCanal &&
                    !showImplant &&
                    shape.canals.map((d) => (
                        <path
                            key={d}
                            d={d}
                            stroke={colorOf(rootCanal)}
                            strokeWidth={3}
                            strokeLinecap="round"
                        />
                    ))}

                {post && !showImplant && (
                    <path
                        d={postPath(shape)}
                        stroke={colorOf(post)}
                        strokeWidth={4.5}
                        strokeLinecap="round"
                    />
                )}

                <path
                    d={shape.crown}
                    fill={crown ? colorOf(crown) : ENAMEL}
                    fillOpacity={crown ? 0.85 : 1}
                    stroke={prosthesis ? colorOf(prosthesis) : OUTLINE}
                    strokeWidth={prosthesis ? 2.2 : 1.1}
                    strokeDasharray={prosthesis ? '3 2' : undefined}
                />

                {bridge && (
                    <rect
                        x={-4}
                        y={54}
                        width={TOOTH_BOX.width + 8}
                        height={6}
                        rx={2}
                        fill={colorOf(bridge)}
                        opacity={0.9}
                    />
                )}
            </g>

            {/* La X va fuera del grupo transparente para verse con nitidez. */}
            {(absent || (extraction && extraction.status !== 'tratado')) && (
                <g
                    stroke={absent ? '#1d4ed8' : colorOf(extraction)}
                    strokeWidth={3}
                    strokeLinecap="round"
                >
                    <line x1={4} y1={6} x2={36} y2={70} />
                    <line x1={36} y1={6} x2={4} y2={70} />
                </g>
            )}
        </>
    );
}

/** Vista oclusal redonda con las 5 superficies seleccionables. */
function OcclusalView({
    code,
    state,
    cy,
    radius,
    selected,
    selectedSurface,
    onSurface,
}: {
    code: number;
    state: ToothState | undefined;
    cy: number;
    radius: number;
    selected: boolean;
    selectedSurface: Surface | null;
    onSurface: (surface: Surface) => void;
}) {
    const paths = occlusalPaths(20, cy, radius, radius * 0.4);

    return (
        <g opacity={state?.absent ? 0.35 : 1}>
            {SURFACES.map((surface) => {
                const entry = state?.surfaces[surface];
                const isSelected = selected && selectedSurface === surface;

                return (
                    <path
                        key={surface}
                        d={paths[sideOf(code, surface)]}
                        data-surface={surface}
                        fill={colorOf(entry) ?? ENAMEL}
                        stroke={isSelected ? 'var(--ring)' : OUTLINE}
                        strokeWidth={isSelected ? 2.5 : 0.9}
                        className="cursor-pointer transition-opacity hover:opacity-75"
                        onClick={(e) => {
                            e.stopPropagation();
                            onSurface(surface);
                        }}
                    />
                );
            })}
        </g>
    );
}

/**
 * Odontograma anatómico en SVG: cada pieza con su forma (incisivo, canino,
 * premolar, molar), raíces hacia afuera de la boca y, junto a la corona, la
 * vista oclusal con las 5 superficies.
 *
 * Teclado: un solo punto de tabulación; las flechas recorren los dientes y
 * Enter o Espacio selecciona. Con el mouse se puede elegir la superficie.
 */
export function OdontogramChart({
    teeth,
    chart,
    dentition,
    selected,
    selectedSurface,
    onSelect,
}: Props) {
    const rows = rowsFor(dentition);
    const infoByCode = new Map(teeth.map((t) => [t.code, t]));
    const refs = useRef(new Map<number, SVGGElement>());
    const [focusable, setFocusable] = useState<number>(selected ?? rows[0][0]);
    const activeFocus = rows.flat().includes(focusable) ? focusable : rows[0][0];

    const longest = Math.max(...rows.map((r) => r.length));
    const width = longest * CELL + MIDLINE_GAP;
    const upperRows = rows.length / 2;
    // En la vista mixta se separan un poco más las filas de la misma arcada.
    const rowGap = rows.length > 2 ? 18 : 0;
    const pitch = ROW_HEIGHT + rowGap;
    // Fin de la arcada superior + la mitad del espacio entre arcadas.
    const occlusalPlane = upperRows * pitch - rowGap + BLOCK_GAP / 2;
    const height = rows.length * pitch - 2 * rowGap + BLOCK_GAP;

    const handleKey = (event: KeyboardEvent<SVGGElement>, code: number) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            onSelect(code, null);

            return;
        }

        const next = neighbour(rows, code, event.key);

        if (next !== null) {
            event.preventDefault();
            setFocusable(next);
            refs.current.get(next)?.focus();
        }
    };

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            className="h-auto w-full min-w-[640px] select-none"
            role="group"
            aria-label="Odontograma. Use las flechas para moverse entre dientes y Enter para seleccionar."
        >
            {/* Plano oclusal y línea media */}
            <line
                x1={0}
                y1={occlusalPlane}
                x2={width}
                y2={occlusalPlane}
                stroke="var(--border)"
                aria-hidden="true"
            />
            <line
                x1={width / 2}
                y1={0}
                x2={width / 2}
                y2={height}
                stroke="var(--border)"
                strokeDasharray="4 4"
                aria-hidden="true"
            />

            {rows.map((row, r) => {
                const upper = r < upperRows;
                const offset = ((longest - row.length) * CELL) / 2;
                const y = r * pitch + (upper ? 0 : BLOCK_GAP - rowGap);
                const half = row.length / 2;

                return (
                    <g key={r} transform={`translate(${offset}, ${y})`}>
                        {row.map((code, c) => {
                            const info = infoByCode.get(code);
                            const x = c * CELL + (c >= half ? MIDLINE_GAP : 0) + 3;
                            const state = chart[String(code)];
                            const isSelected = selected === code;
                            const temporary = info?.dentition === 'temporal';
                            const scale = temporary ? 0.8 : 1;
                            const shrink = TOOTH_BOX.height * (1 - scale);
                            const badges = (state?.tooth ?? []).filter(
                                (e) =>
                                    TOOTH_ABBREVIATIONS[e.condition] && e.condition !== 'ausente',
                            );

                            // Superior: número, raíces arriba, corona, vista oclusal. Inferior: al revés.
                            const layout = upper
                                ? {
                                      label: 11,
                                      tooth: `translate(${(TOOTH_BOX.width * (1 - scale)) / 2}, ${16 + shrink}) scale(${scale})`,
                                      occlusal: 106,
                                      badge: 136,
                                  }
                                : {
                                      label: 137,
                                      tooth: `translate(${(TOOTH_BOX.width * (1 - scale)) / 2}, ${52 + TOOTH_BOX.height * scale}) scale(${scale}, ${-scale})`,
                                      occlusal: 32,
                                      badge: 10,
                                  };

                            return (
                                <g
                                    key={code}
                                    ref={(el) => {
                                        if (el) {
                                            refs.current.set(code, el);
                                        } else {
                                            refs.current.delete(code);
                                        }
                                    }}
                                    role="button"
                                    tabIndex={code === activeFocus ? 0 : -1}
                                    aria-pressed={isSelected}
                                    aria-label={describeTooth(code, info, state)}
                                    data-tooth={code}
                                    transform={`translate(${x}, 0)`}
                                    className="group cursor-pointer outline-none"
                                    onClick={() => {
                                        setFocusable(code);
                                        onSelect(code, null);
                                    }}
                                    onKeyDown={(e) => handleKey(e, code)}
                                    onFocus={() => setFocusable(code)}
                                >
                                    <rect
                                        x={-3}
                                        y={1}
                                        width={TOOTH_BOX.width + 6}
                                        height={ROW_HEIGHT - 4}
                                        rx={8}
                                        fill={isSelected ? 'var(--accent)' : 'transparent'}
                                        stroke={isSelected ? 'var(--primary)' : 'transparent'}
                                        strokeWidth={2}
                                        className="group-hover:fill-[var(--accent)] group-focus-visible:stroke-[var(--ring)]"
                                    />
                                    <text
                                        x={TOOTH_BOX.width / 2}
                                        y={layout.label}
                                        textAnchor="middle"
                                        fontSize={11}
                                        fontWeight={isSelected ? 700 : 500}
                                        fill="currentColor"
                                    >
                                        {code}
                                    </text>

                                    {info && (
                                        <g transform={layout.tooth}>
                                            <AnatomicalTooth info={info} state={state} />
                                        </g>
                                    )}

                                    <OcclusalView
                                        code={code}
                                        state={state}
                                        cy={layout.occlusal}
                                        radius={temporary ? 12 : 15}
                                        selected={isSelected}
                                        selectedSurface={selectedSurface}
                                        onSurface={(surface) => {
                                            setFocusable(code);
                                            onSelect(code, surface);
                                        }}
                                    />

                                    {badges.length > 0 && (
                                        <text
                                            x={TOOTH_BOX.width / 2}
                                            y={layout.badge}
                                            textAnchor="middle"
                                            fontSize={8.5}
                                            fontWeight={700}
                                            fill={
                                                STATUS_STYLES[badges[badges.length - 1].status].fill
                                            }
                                        >
                                            {badges
                                                .map((b) => TOOTH_ABBREVIATIONS[b.condition])
                                                .join(' ')}
                                        </text>
                                    )}
                                </g>
                            );
                        })}
                    </g>
                );
            })}
        </svg>
    );
}

export function OdontogramLegend() {
    return (
        <div className="grid gap-1 text-xs text-muted-foreground">
            <ul aria-label="Colores por estado" className="flex flex-wrap gap-x-4 gap-y-1">
                {Object.entries(STATUS_STYLES).map(([status, style]) => (
                    <li key={status} className="flex items-center gap-1.5">
                        <span
                            className="inline-block size-3 rounded-full"
                            style={{ background: style.fill }}
                            aria-hidden="true"
                        />
                        {style.label}
                    </li>
                ))}
            </ul>
            <p>
                Corona pintada: corona · línea en la raíz: endodoncia · tornillo: implante · barra:
                puente · contorno punteado: prótesis · X azul: ausente · X de color: extracción
                indicada
            </p>
        </div>
    );
}
