import { describe, expect, it } from 'vitest';
import {
    addDays,
    addMonths,
    dayLabel,
    fromMinutes,
    isoWeekday,
    layoutLanes,
    monthGrid,
    startOfWeek,
    toMinutes,
} from './agenda';

describe('fechas', () => {
    it('suma días y meses sin desbordar', () => {
        expect(addDays('2026-12-31', 1)).toBe('2027-01-01');
        expect(addDays('2026-03-01', -1)).toBe('2026-02-28');
        expect(addMonths('2026-01-31', 1)).toBe('2026-02-28');
    });

    it('calcula el lunes de la semana', () => {
        expect(isoWeekday('2026-11-22')).toBe(7); // domingo
        expect(startOfWeek('2026-11-22')).toBe('2026-11-16');
        expect(startOfWeek('2026-11-16')).toBe('2026-11-16');
    });

    it('arma la grilla del mes de lunes a domingo', () => {
        const weeks = monthGrid('2026-11-15');

        expect(weeks[0][0]).toBe('2026-10-26');
        expect(weeks.at(-1)?.at(-1)).toBe('2026-12-06');
        expect(weeks.every((w) => w.length === 7)).toBe(true);
    });

    it('formatea en español', () => {
        expect(dayLabel('2026-11-23')).toBe('lunes 23/11');
        expect(toMinutes('10:30')).toBe(630);
        expect(fromMinutes(605)).toBe('10:05');
    });
});

describe('layoutLanes', () => {
    it('pone en carriles distintos las citas que se superponen', () => {
        const lanes = layoutLanes([
            { id: 1, start: 600, end: 660 },
            { id: 2, start: 630, end: 690 },
            { id: 3, start: 700, end: 730 },
        ]);

        expect(lanes.get(1)).toEqual({ lane: 0, lanes: 2 });
        expect(lanes.get(2)).toEqual({ lane: 1, lanes: 2 });
        expect(lanes.get(3)).toEqual({ lane: 0, lanes: 1 });
    });

    it('reutiliza un carril libre y no superpone citas consecutivas', () => {
        const lanes = layoutLanes([
            { id: 1, start: 600, end: 720 },
            { id: 2, start: 600, end: 630 },
            { id: 3, start: 630, end: 660 },
        ]);

        expect(lanes.get(1)?.lane).toBe(0);
        expect(lanes.get(2)?.lane).toBe(1);
        expect(lanes.get(3)?.lane).toBe(1);
        expect(lanes.get(3)?.lanes).toBe(2);
    });
});
