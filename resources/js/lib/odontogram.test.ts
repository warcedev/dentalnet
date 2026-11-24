import { describe, expect, it } from 'vitest';
import {
    describeTooth,
    neighbour,
    PERMANENT_ROWS,
    rowsFor,
    sideOf,
    surfaceLabel,
} from './odontogram';

describe('sideOf', () => {
    it('dibuja vestibular hacia afuera de la arcada', () => {
        expect(sideOf(16, 'vestibular')).toBe('top');
        expect(sideOf(46, 'vestibular')).toBe('bottom');
        expect(sideOf(16, 'lingual')).toBe('bottom');
        expect(sideOf(36, 'lingual')).toBe('top');
    });

    it('dibuja mesial hacia la línea media en cada lado', () => {
        expect(sideOf(11, 'mesial')).toBe('right');
        expect(sideOf(21, 'mesial')).toBe('left');
        expect(sideOf(41, 'mesial')).toBe('right');
        expect(sideOf(31, 'mesial')).toBe('left');
        expect(sideOf(55, 'distal')).toBe('left');
        expect(sideOf(65, 'distal')).toBe('right');
    });
});

describe('surfaceLabel', () => {
    it('usa palatina arriba e incisal en anteriores', () => {
        expect(surfaceLabel(16, 'lingual')).toBe('Palatina');
        expect(surfaceLabel(46, 'lingual')).toBe('Lingual');
        expect(surfaceLabel(11, 'oclusal')).toBe('Incisal');
        expect(surfaceLabel(16, 'oclusal')).toBe('Oclusal');
    });
});

describe('rowsFor', () => {
    it('incluye las 32 piezas permanentes y las 20 temporales', () => {
        expect(rowsFor('permanente').flat()).toHaveLength(32);
        expect(rowsFor('temporal').flat()).toHaveLength(20);
        expect(rowsFor('mixta').flat()).toHaveLength(52);
    });
});

describe('neighbour', () => {
    it('se mueve con las flechas dentro y entre filas', () => {
        expect(neighbour(PERMANENT_ROWS, 11, 'ArrowRight')).toBe(21);
        expect(neighbour(PERMANENT_ROWS, 18, 'ArrowLeft')).toBeNull();
        expect(neighbour(PERMANENT_ROWS, 16, 'ArrowDown')).toBe(46);
        expect(neighbour(PERMANENT_ROWS, 46, 'ArrowUp')).toBe(16);
        expect(neighbour(PERMANENT_ROWS, 13, 'End')).toBe(28);
    });

    it('mantiene la posición relativa entre filas de distinto largo', () => {
        const rows = rowsFor('mixta');

        expect(neighbour(rows, 18, 'ArrowDown')).toBe(55);
        expect(neighbour(rows, 28, 'ArrowDown')).toBe(65);
    });
});

describe('describeTooth', () => {
    it('resume el estado del diente para lectores de pantalla', () => {
        const text = describeTooth(
            16,
            {
                code: 16,
                dentition: 'permanente',
                quadrant: 1,
                position: 6,
                arch: 'superior',
                type: 'molar',
            },
            {
                absent: false,
                tooth: [
                    {
                        id: 2,
                        tooth_code: 16,
                        surface: null,
                        scope: 'diente',
                        condition: 'endodoncia',
                        condition_label: 'Endodoncia',
                        status: 'tratado',
                        status_label: 'Tratado',
                        date: '2026-01-10',
                    },
                ],
                surfaces: {
                    oclusal: {
                        id: 1,
                        tooth_code: 16,
                        surface: 'oclusal',
                        scope: 'superficie',
                        condition: 'caries',
                        condition_label: 'Caries',
                        status: 'diagnosticado',
                        status_label: 'Diagnosticado',
                        date: '2026-01-05',
                    },
                },
            },
        );

        expect(text).toBe(
            'Diente 16, molar superior: Endodoncia (tratado); Oclusal: Caries (diagnosticado)',
        );
    });

    it('indica cuando no hay registros', () => {
        expect(describeTooth(21, undefined, undefined)).toBe('Diente 21: sin registros');
    });
});
