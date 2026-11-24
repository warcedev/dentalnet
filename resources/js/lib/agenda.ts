/**
 * Utilidades de la agenda. Las fechas se manejan como texto local
 * ("AAAA-MM-DD" y "HH:mm") y los cálculos se hacen en UTC para que la zona
 * horaria del navegador no corra los días.
 */

export const WEEKDAYS = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
export const WEEKDAYS_SHORT = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
export const MONTHS = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
];

const toUtc = (date: string) => {
    const [y, m, d] = date.split('-').map(Number);

    return new Date(Date.UTC(y, m - 1, d));
};

const fromUtc = (d: Date) => d.toISOString().slice(0, 10);

export function addDays(date: string, days: number): string {
    const d = toUtc(date);
    d.setUTCDate(d.getUTCDate() + days);

    return fromUtc(d);
}

export function addMonths(date: string, months: number): string {
    const d = toUtc(date);
    const day = d.getUTCDate();
    d.setUTCDate(1);
    d.setUTCMonth(d.getUTCMonth() + months);
    const last = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 0)).getUTCDate();
    d.setUTCDate(Math.min(day, last));

    return fromUtc(d);
}

/** 1 = lunes … 7 = domingo (ISO). */
export function isoWeekday(date: string): number {
    const day = toUtc(date).getUTCDay();

    return day === 0 ? 7 : day;
}

export function startOfWeek(date: string): string {
    return addDays(date, 1 - isoWeekday(date));
}

/** Semanas (de lunes a domingo) que cubren el mes de la fecha. */
export function monthGrid(date: string): string[][] {
    const first = `${date.slice(0, 7)}-01`;
    let cursor = startOfWeek(first);
    const month = date.slice(0, 7);
    const weeks: string[][] = [];

    while (weeks.length === 0 || cursor.slice(0, 7) <= month) {
        const week = Array.from({ length: 7 }, (_, i) => addDays(cursor, i));
        weeks.push(week);
        cursor = addDays(cursor, 7);

        if (cursor.slice(0, 7) > month) {
            break;
        }
    }

    return weeks;
}

export function todayLocal(): string {
    return new Date().toLocaleDateString('en-CA', { timeZone: 'America/Asuncion' });
}

export function nowMinutesLocal(): number {
    const [h, m] = new Date()
        .toLocaleTimeString('en-GB', {
            timeZone: 'America/Asuncion',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        })
        .split(':')
        .map(Number);

    return h * 60 + m;
}

/** "10:30" → 630 */
export function toMinutes(time: string): number {
    const [h, m] = time.split(':').map(Number);

    return h * 60 + m;
}

/** 630 → "10:30" */
export function fromMinutes(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

/** "2026-05-01 10:30" → { date: "2026-05-01", time: "10:30", minutes: 630 } */
export function splitDateTime(value: string): { date: string; time: string; minutes: number } {
    const [date, time] = value.split(' ');

    return { date, time, minutes: toMinutes(time) };
}

export function dayLabel(date: string, withYear = false): string {
    const [y, m, d] = date.split('-');

    return `${WEEKDAYS[isoWeekday(date) - 1]} ${d}/${m}${withYear ? `/${y}` : ''}`;
}

export function monthLabel(date: string): string {
    const [y, m] = date.split('-').map(Number);

    return `${MONTHS[m - 1]} ${y}`;
}

export type Interval = { id: number; start: number; end: number };

/**
 * Reparte en carriles los eventos que se superponen para dibujarlos lado a
 * lado. Devuelve, por id, el carril y la cantidad de carriles de su grupo.
 */
export function layoutLanes(items: Interval[]): Map<number, { lane: number; lanes: number }> {
    const sorted = [...items].sort((a, b) => a.start - b.start || b.end - a.end);
    const result = new Map<number, { lane: number; lanes: number }>();
    let group: Interval[] = [];
    let laneEnds: number[] = [];
    let groupEnd = -Infinity;

    const flush = () => {
        const lanes = laneEnds.length;

        for (const item of group) {
            const entry = result.get(item.id);

            if (entry) {
                entry.lanes = lanes;
            }
        }

        group = [];
        laneEnds = [];
    };

    for (const item of sorted) {
        if (item.start >= groupEnd) {
            flush();
            groupEnd = -Infinity;
        }

        let lane = laneEnds.findIndex((end) => end <= item.start);

        if (lane === -1) {
            lane = laneEnds.length;
            laneEnds.push(item.end);
        } else {
            laneEnds[lane] = item.end;
        }

        result.set(item.id, { lane, lanes: 1 });
        group.push(item);
        groupEnd = Math.max(groupEnd, item.end);
    }

    flush();

    return result;
}
