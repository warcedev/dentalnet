import { formatDate } from '@/lib/format';
import { useState } from 'react';

type Point = { date: string; value: number };

const W = 320;
const H = 150;
const PAD = { top: 14, right: 14, bottom: 26, left: 34 };

function niceMax(value: number, minimum: number): number {
    const target = Math.max(value, minimum);
    const step = target <= 5 ? 1 : target <= 10 ? 2 : target <= 50 ? 10 : 20;

    return Math.ceil(target / step) * step;
}

/**
 * Evolución de una sola medida en el tiempo (una serie: sin leyenda, el
 * título la nombra). Línea de 2 px, puntos de 8 px y valor al pasar el mouse
 * o enfocar el punto con el teclado.
 */
export function EvolutionChart({
    title,
    unit,
    points,
    minMax,
}: {
    title: string;
    unit: string;
    points: Point[];
    minMax: number;
}) {
    const [active, setActive] = useState<number | null>(null);

    if (points.length === 0) {
        return null;
    }

    const max = niceMax(Math.max(...points.map((p) => p.value)), minMax);
    const innerW = W - PAD.left - PAD.right;
    const innerH = H - PAD.top - PAD.bottom;
    const x = (i: number) =>
        PAD.left + (points.length === 1 ? innerW / 2 : (i / (points.length - 1)) * innerW);
    const y = (v: number) => PAD.top + innerH - (v / max) * innerH;
    const ticks = [0, max / 2, max];
    const path = points
        .map((p, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(p.value).toFixed(1)}`)
        .join(' ');
    const fmt = (v: number) => `${v.toLocaleString('es-PY', { maximumFractionDigits: 1 })}${unit}`;
    const last = points[points.length - 1];

    return (
        <figure className="grid gap-1">
            <figcaption className="flex items-baseline justify-between gap-2 text-sm">
                <span className="font-medium">{title}</span>
                <span className="text-muted-foreground tabular-nums">
                    Último: {fmt(last.value)}
                </span>
            </figcaption>
            <div className="relative">
                <svg
                    viewBox={`0 0 ${W} ${H}`}
                    className="h-auto w-full"
                    role="img"
                    aria-label={`${title}: ${points.map((p) => `${formatDate(p.date)} ${fmt(p.value)}`).join('; ')}`}
                >
                    {ticks.map((t) => (
                        <g key={t}>
                            <line
                                x1={PAD.left}
                                x2={W - PAD.right}
                                y1={y(t)}
                                y2={y(t)}
                                stroke="var(--border)"
                                strokeWidth={1}
                            />
                            <text
                                x={PAD.left - 6}
                                y={y(t) + 3}
                                textAnchor="end"
                                fontSize={9}
                                fill="var(--muted-foreground)"
                            >
                                {t.toLocaleString('es-PY', { maximumFractionDigits: 1 })}
                            </text>
                        </g>
                    ))}
                    {points.length > 1 && (
                        <path
                            d={path}
                            fill="none"
                            stroke="var(--primary)"
                            strokeWidth={2}
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                    )}
                    {active !== null && (
                        <line
                            x1={x(active)}
                            x2={x(active)}
                            y1={PAD.top}
                            y2={PAD.top + innerH}
                            stroke="var(--muted-foreground)"
                            strokeDasharray="3 3"
                        />
                    )}
                    {points.map((p, i) => (
                        <g key={p.date + i}>
                            <circle
                                cx={x(i)}
                                cy={y(p.value)}
                                r={4.5}
                                fill="var(--primary)"
                                stroke="var(--card)"
                                strokeWidth={2}
                            />
                            {/* Área de interacción más grande que el punto */}
                            <circle
                                cx={x(i)}
                                cy={y(p.value)}
                                r={14}
                                fill="transparent"
                                tabIndex={0}
                                aria-label={`${formatDate(p.date)}: ${fmt(p.value)}`}
                                onMouseEnter={() => setActive(i)}
                                onMouseLeave={() => setActive(null)}
                                onFocus={() => setActive(i)}
                                onBlur={() => setActive(null)}
                                className="cursor-default outline-none"
                            />
                        </g>
                    ))}
                    {[0, points.length - 1]
                        .filter((v, i, a) => a.indexOf(v) === i)
                        .map((i) => (
                            <text
                                key={i}
                                x={x(i)}
                                y={H - 8}
                                textAnchor={
                                    points.length === 1 ? 'middle' : i === 0 ? 'start' : 'end'
                                }
                                fontSize={9}
                                fill="var(--muted-foreground)"
                            >
                                {formatDate(points[i].date)}
                            </text>
                        ))}
                </svg>
                {active !== null && (
                    <div
                        role="status"
                        className="pointer-events-none absolute -translate-x-1/2 -translate-y-full rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap shadow-md"
                        style={{
                            left: `${(x(active) / W) * 100}%`,
                            top: `${(y(points[active].value) / H) * 100}%`,
                            marginTop: -10,
                        }}
                    >
                        <span className="text-muted-foreground">
                            {formatDate(points[active].date)}
                        </span>{' '}
                        <span className="font-medium tabular-nums">
                            {fmt(points[active].value)}
                        </span>
                    </div>
                )}
            </div>
        </figure>
    );
}
