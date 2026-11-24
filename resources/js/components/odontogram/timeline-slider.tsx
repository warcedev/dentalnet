import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { ChevronLeft, ChevronRight, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Deslizador por las fechas en que cambió el odontograma. El último punto es "hoy".
 */
export function TimelineSlider({
    dates,
    asOf,
    isToday,
    onChange,
    loading,
}: {
    dates: string[];
    asOf: string;
    isToday: boolean;
    onChange: (date: string | null) => void;
    loading: boolean;
}) {
    const points = dates.filter(
        (d) => d < new Date().toLocaleDateString('en-CA', { timeZone: 'America/Asuncion' }),
    );
    const steps = [...points, 'hoy'];
    const indexOf = (date: string, today: boolean) => {
        if (today) {
            return steps.length - 1;
        }

        const exact = steps.indexOf(date);

        if (exact !== -1) {
            return exact;
        }

        // Fecha libre: el último cambio anterior o igual.
        const before = points.filter((d) => d <= date).length - 1;

        return Math.max(0, before);
    };

    const [index, setIndex] = useState(indexOf(asOf, isToday));

    useEffect(() => {
        setIndex(indexOf(asOf, isToday));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [asOf, isToday]);

    const go = (i: number) => {
        const bounded = Math.min(Math.max(i, 0), steps.length - 1);
        setIndex(bounded);
        onChange(steps[bounded] === 'hoy' ? null : steps[bounded]);
    };

    const label = steps[index] === 'hoy' ? 'Hoy' : formatDate(steps[index]);

    if (points.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                La línea de tiempo aparece cuando hay registros en fechas anteriores.
            </p>
        );
    }

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between gap-2">
                <label htmlFor="linea-tiempo" className="text-sm font-medium">
                    Línea de tiempo: <span className="tabular-nums">{label}</span>
                    {loading && (
                        <span className="ml-2 text-xs text-muted-foreground">cargando…</span>
                    )}
                </label>
                {!isToday && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => go(steps.length - 1)}
                    >
                        <RotateCcw aria-hidden="true" />
                        Volver a hoy
                    </Button>
                )}
            </div>
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    onClick={() => go(index - 1)}
                    disabled={index === 0}
                    aria-label="Fecha anterior"
                >
                    <ChevronLeft aria-hidden="true" />
                </Button>
                <input
                    id="linea-tiempo"
                    type="range"
                    min={0}
                    max={steps.length - 1}
                    step={1}
                    value={index}
                    aria-valuetext={label}
                    onChange={(e) => go(Number(e.target.value))}
                    className="h-2 flex-1 cursor-pointer accent-[var(--primary)]"
                />
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    onClick={() => go(index + 1)}
                    disabled={index === steps.length - 1}
                    aria-label="Fecha siguiente"
                >
                    <ChevronRight aria-hidden="true" />
                </Button>
            </div>
            <p className="text-xs text-muted-foreground">
                {formatDate(points[0])} → hoy · {points.length}{' '}
                {points.length === 1 ? 'fecha con cambios' : 'fechas con cambios'}
            </p>
        </div>
    );
}
