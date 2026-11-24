import type { ChartEntry, Dentition, EventStatus, Surface, ToothInfo, ToothState } from '@/types';

/**
 * Filas del odontograma tal como las ve el profesional frente al paciente:
 * el lado derecho del paciente (cuadrantes 1, 4, 5 y 8) queda a la izquierda.
 */
export const PERMANENT_ROWS: number[][] = [
    [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28],
    [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38],
];

export const TEMPORARY_ROWS: number[][] = [
    [55, 54, 53, 52, 51, 61, 62, 63, 64, 65],
    [85, 84, 83, 82, 81, 71, 72, 73, 74, 75],
];

export function rowsFor(dentition: Dentition): number[][] {
    switch (dentition) {
        case 'temporal':
            return TEMPORARY_ROWS;
        case 'mixta':
            return [PERMANENT_ROWS[0], TEMPORARY_ROWS[0], TEMPORARY_ROWS[1], PERMANENT_ROWS[1]];
        default:
            return PERMANENT_ROWS;
    }
}

export const isUpper = (code: number) => [1, 2, 5, 6].includes(Math.floor(code / 10));

/** Cuadrantes que se dibujan a la izquierda (lado derecho del paciente). */
const isLeftSide = (code: number) => [1, 4, 5, 8].includes(Math.floor(code / 10));

export const isAnterior = (code: number) => code % 10 <= 3;

export type Side = 'top' | 'bottom' | 'left' | 'right' | 'center';

/**
 * Dónde se dibuja cada superficie. Vestibular siempre hacia afuera de la
 * arcada; mesial siempre hacia la línea media.
 */
export function sideOf(code: number, surface: Surface): Side {
    switch (surface) {
        case 'oclusal':
            return 'center';
        case 'vestibular':
            return isUpper(code) ? 'top' : 'bottom';
        case 'lingual':
            return isUpper(code) ? 'bottom' : 'top';
        case 'mesial':
            return isLeftSide(code) ? 'right' : 'left';
        case 'distal':
            return isLeftSide(code) ? 'left' : 'right';
    }
}

export function surfaceLabel(code: number | null, surface: Surface): string {
    switch (surface) {
        case 'vestibular':
            return 'Vestibular';
        case 'lingual':
            return code !== null && isUpper(code) ? 'Palatina' : 'Lingual';
        case 'mesial':
            return 'Mesial';
        case 'distal':
            return 'Distal';
        case 'oclusal':
            return code !== null && isAnterior(code) ? 'Incisal' : 'Oclusal';
    }
}

export const SURFACES: Surface[] = ['vestibular', 'lingual', 'mesial', 'distal', 'oclusal'];

/** Colores por estado (convención: rojo pendiente, azul realizado). */
export const STATUS_STYLES: Record<EventStatus, { fill: string; label: string }> = {
    diagnosticado: { fill: '#dc2626', label: 'Diagnosticado (pendiente)' },
    en_tratamiento: { fill: '#ea580c', label: 'En tratamiento' },
    tratado: { fill: '#2563eb', label: 'Tratado' },
    fallido: { fill: '#7c3aed', label: 'Fallido' },
    en_seguimiento: { fill: '#ca8a04', label: 'En seguimiento' },
};

/** Abreviaturas para las condiciones de diente completo. */
export const TOOTH_ABBREVIATIONS: Record<string, string> = {
    corona: 'CO',
    endodoncia: 'TC',
    implante: 'IMP',
    puente: 'PU',
    protesis: 'PR',
    perno: 'PE',
    extraccion: 'EX',
    ausente: 'AU',
};

const TYPE_NAMES: Record<ToothInfo['type'], string> = {
    incisivo: 'incisivo',
    canino: 'canino',
    premolar: 'premolar',
    molar: 'molar',
};

function describeEntry(entry: ChartEntry): string {
    return `${entry.condition_label} (${entry.status_label.toLowerCase()})`;
}

/** Texto para lectores de pantalla con todo el estado del diente. */
export function describeTooth(
    code: number,
    info: ToothInfo | undefined,
    state: ToothState | undefined,
): string {
    const name = info
        ? `${TYPE_NAMES[info.type]} ${info.arch}${info.dentition === 'temporal' ? ' temporal' : ''}`
        : '';
    const parts: string[] = [];

    if (state?.absent) {
        parts.push('ausente');
    }

    for (const entry of state?.tooth ?? []) {
        parts.push(describeEntry(entry));
    }

    for (const surface of SURFACES) {
        const entry = state?.surfaces[surface];

        if (entry) {
            parts.push(`${surfaceLabel(code, surface)}: ${describeEntry(entry)}`);
        }
    }

    return `Diente ${code}${name ? `, ${name}` : ''}: ${parts.length ? parts.join('; ') : 'sin registros'}`;
}

/** Movimiento con flechas entre dientes (tabindex itinerante). */
export function neighbour(rows: number[][], current: number, key: string): number | null {
    const r = rows.findIndex((row) => row.includes(current));

    if (r === -1) {
        return null;
    }

    const c = rows[r].indexOf(current);

    switch (key) {
        case 'ArrowLeft':
            return rows[r][c - 1] ?? null;
        case 'ArrowRight':
            return rows[r][c + 1] ?? null;
        case 'ArrowUp':
        case 'ArrowDown': {
            const target = rows[r + (key === 'ArrowUp' ? -1 : 1)];

            if (!target) {
                return null;
            }

            // Misma posición relativa en la otra fila (sirve si tienen distinto largo).
            const ratio = rows[r].length > 1 ? c / (rows[r].length - 1) : 0;

            return target[Math.round(ratio * (target.length - 1))] ?? null;
        }
        case 'Home':
            return rows[r][0];
        case 'End':
            return rows[r][rows[r].length - 1];
        default:
            return null;
    }
}
