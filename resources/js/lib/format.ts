/**
 * Formatos regionales del consultorio: Paraguay, español, guaraníes.
 * Todas las fechas se muestran en la zona horaria America/Asuncion,
 * independientemente de la configuración del equipo.
 */

export const TIME_ZONE = 'America/Asuncion';
const LOCALE = 'es-PY';

const dateFormatter = new Intl.DateTimeFormat(LOCALE, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: TIME_ZONE,
});

const timeFormatter = new Intl.DateTimeFormat(LOCALE, {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: TIME_ZONE,
});

function toDate(value: string | Date | null | undefined): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    // Una fecha sin hora ("2026-11-20") se interpreta como día calendario,
    // no como medianoche UTC (que en Asunción sería el día anterior).
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [y, m, d] = value.split('-').map(Number);

        return new Date(Date.UTC(y, m - 1, d, 12));
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

/** dd/mm/aaaa */
export function formatDate(value: string | Date | null | undefined, fallback = '—'): string {
    const date = toDate(value);

    return date ? dateFormatter.format(date) : fallback;
}

/** dd/mm/aaaa HH:mm */
export function formatDateTime(value: string | Date | null | undefined, fallback = '—'): string {
    const date = toDate(value);

    return date ? `${dateFormatter.format(date)} ${timeFormatter.format(date)}` : fallback;
}

/** "2026-05-01 10:30" (hora local enviada por el servidor) → "01/05/2026 10:30". */
export function formatLocalDateTime(value: string | null | undefined, fallback = '—'): string {
    const match = value ? /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(value) : null;

    return match ? `${match[3]}/${match[2]}/${match[1]} ${match[4]}:${match[5]}` : fallback;
}

/** Gs. 1.250.000 — los montos son enteros (el guaraní no usa decimales). */
export function formatGuaranies(amount: number | null | undefined, fallback = '—'): string {
    if (amount === null || amount === undefined || !Number.isFinite(amount)) {
        return fallback;
    }

    const rounded = Math.round(amount);
    const digits = Math.abs(rounded)
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${rounded < 0 ? '-' : ''}Gs. ${digits}`;
}

/** Convierte "1.250.000" o "Gs. 1.250.000" a 1250000. Devuelve null si no es válido. */
export function parseGuaranies(input: string): number | null {
    const clean = input.replace(/gs\.?/i, '').replace(/[\s.]/g, '');

    if (!/^-?\d+$/.test(clean)) {
        return null;
    }

    return Number.parseInt(clean, 10);
}
