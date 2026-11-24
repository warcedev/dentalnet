import { describe, expect, it } from 'vitest';
import {
    formatDate,
    formatDateTime,
    formatGuaranies,
    formatLocalDateTime,
    parseGuaranies,
} from './format';

describe('formatDate', () => {
    it('usa dd/mm/aaaa', () => {
        expect(formatDate('2026-03-05T15:00:00Z')).toBe('05/03/2026');
    });

    it('no corre un día las fechas sin hora', () => {
        expect(formatDate('2026-01-01')).toBe('01/01/2026');
    });

    it('convierte a la hora de Asunción (UTC-3)', () => {
        // 01:30 UTC del 1/1 es todavía 31/12 en Asunción.
        expect(formatDateTime('2026-01-01T01:30:00Z')).toBe('31/12/2025 22:30');
    });

    it('muestra un guion para valores vacíos o inválidos', () => {
        expect(formatDate(null)).toBe('—');
        expect(formatDate('no-es-fecha')).toBe('—');
        expect(formatDateTime(undefined, 'Nunca')).toBe('Nunca');
    });
});

describe('formatLocalDateTime', () => {
    it('formatea la hora local sin convertir zonas', () => {
        expect(formatLocalDateTime('2026-05-01 10:30')).toBe('01/05/2026 10:30');
        expect(formatLocalDateTime(null)).toBe('—');
    });
});

describe('formatGuaranies', () => {
    it.each([
        [0, 'Gs. 0'],
        [500, 'Gs. 500'],
        [1250000, 'Gs. 1.250.000'],
        [-75000, '-Gs. 75.000'],
        [1000.6, 'Gs. 1.001'],
    ])('%d → %s', (amount, expected) => {
        expect(formatGuaranies(amount)).toBe(expected);
    });

    it('muestra un guion si no hay monto', () => {
        expect(formatGuaranies(null)).toBe('—');
        expect(formatGuaranies(Number.NaN)).toBe('—');
    });
});

describe('parseGuaranies', () => {
    it.each([
        ['1.250.000', 1250000],
        ['Gs. 75.000', 75000],
        ['gs 300', 300],
        ['  42  ', 42],
    ])('%s → %d', (input, expected) => {
        expect(parseGuaranies(input)).toBe(expected);
    });

    it('rechaza decimales y texto', () => {
        expect(parseGuaranies('1.250,50')).toBeNull();
        expect(parseGuaranies('abc')).toBeNull();
        expect(parseGuaranies('')).toBeNull();
    });
});
