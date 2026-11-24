import type { ToothInfo } from '@/types';

/**
 * Siluetas anatómicas (vista vestibular) dibujadas en una caja de 40 × 72
 * con la corona abajo (y 44–72) y las raíces arriba (y 0–46). Es la
 * orientación del maxilar superior; el inferior se dibuja espejado.
 */
export const TOOTH_BOX = { width: 40, height: 72, cervical: 44 };

export type ToothShape = {
    crown: string;
    roots: string[];
    /** Conductos radiculares (se pintan cuando hay endodoncia). */
    canals: string[];
};

const INCISOR: ToothShape = {
    crown: 'M12 44 C10.5 52 10.5 62 12 70 Q20 73 28 70 C29.5 62 29.5 52 28 44 Z',
    roots: ['M13 45 C13 30 16 13 20 5 C24 13 27 30 27 45 Z'],
    canals: ['M20 44 L20 10'],
};

const CANINE: ToothShape = {
    crown: 'M11 44 C9 54 11 63 20 72 C29 63 31 54 29 44 Z',
    roots: ['M13 45 C13 28 16 7 20 0 C24 7 27 28 27 45 Z'],
    canals: ['M20 44 L20 5'],
};

const PREMOLAR_UPPER: ToothShape = {
    crown: 'M9 44 C7 54 8 63 11 68 Q15 72.5 20 68 Q25 72.5 29 68 C32 63 33 54 31 44 Z',
    roots: [
        'M10 45 C10 32 12 16 15 8 C18 16 19.5 32 19.5 45 Z',
        'M20.5 45 C20.5 32 22 16 25 8 C28 16 30 32 30 45 Z',
    ],
    canals: ['M15 44 L15 13', 'M25 44 L25 13'],
};

const PREMOLAR_LOWER: ToothShape = {
    crown: 'M9 44 C7 54 8 63 11 68 Q15 72.5 20 68 Q25 72.5 29 68 C32 63 33 54 31 44 Z',
    roots: ['M12 45 C12 30 15 12 20 4 C25 12 28 30 28 45 Z'],
    canals: ['M20 44 L20 9'],
};

const MOLAR_CROWN =
    'M3 44 C1 54 2 64 5 69 Q9.5 73 14 69 Q20 73 26 69 Q30.5 73 35 69 C38 64 39 54 37 44 Z';

const MOLAR_UPPER: ToothShape = {
    crown: MOLAR_CROWN,
    roots: [
        'M5 45 C4 32 5 18 8 9 C11 18 13 32 14.5 45 Z',
        'M15.5 45 C15.5 30 17.5 15 20 6 C22.5 15 24.5 30 24.5 45 Z',
        'M25.5 45 C27 32 29 18 32 9 C35 18 36 32 35 45 Z',
    ],
    canals: ['M9.5 44 L8.5 13', 'M20 44 L20 11', 'M30.5 44 L31.5 13'],
};

const MOLAR_LOWER: ToothShape = {
    crown: MOLAR_CROWN,
    roots: [
        'M5 45 C4 32 6 16 10 6 C14 16 16.5 32 17 45 Z',
        'M23 45 C23.5 32 26 16 30 6 C34 16 36 32 35 45 Z',
    ],
    canals: ['M10.5 44 L10 11', 'M29.5 44 L30 11'],
};

export function shapeFor(tooth: Pick<ToothInfo, 'type' | 'arch'>): ToothShape {
    const upper = tooth.arch === 'superior';

    switch (tooth.type) {
        case 'incisivo':
            return INCISOR;
        case 'canino':
            return CANINE;
        case 'premolar':
            return upper ? PREMOLAR_UPPER : PREMOLAR_LOWER;
        default:
            return upper ? MOLAR_UPPER : MOLAR_LOWER;
    }
}

/** Implante: tornillo en lugar de las raíces. */
export const IMPLANT = {
    body: 'M14 46 L26 46 L25 12 Q20 4 15 12 Z',
    threads: [14, 20, 26, 32, 38].map((y) => `M14.6 ${y} L25.4 ${y + 2.5}`),
};

/** Perno: trazo grueso dentro del primer conducto de la pieza. */
export function postPath(shape: ToothShape): string {
    const x = Number(/^M\s*([\d.]+)/.exec(shape.canals[0] ?? '')?.[1] ?? 20);

    return `M${x} 45 L${x} 24`;
}

/**
 * Vista oclusal redonda: un círculo central (oclusal/incisal) y cuatro
 * sectores. Devuelve el path de cada lado de la figura.
 */
export function occlusalPaths(cx: number, cy: number, outer: number, inner: number) {
    const point = (r: number, deg: number) => {
        const rad = (deg * Math.PI) / 180;

        return `${(cx + r * Math.cos(rad)).toFixed(2)} ${(cy + r * Math.sin(rad)).toFixed(2)}`;
    };

    const sector = (from: number, to: number) =>
        `M ${point(outer, from)} A ${outer} ${outer} 0 0 1 ${point(outer, to)} L ${point(inner, to)} A ${inner} ${inner} 0 0 0 ${point(inner, from)} Z`;

    return {
        top: sector(-135, -45),
        right: sector(-45, 45),
        bottom: sector(45, 135),
        left: sector(135, 225),
        center: `M ${cx - inner} ${cy} A ${inner} ${inner} 0 1 1 ${cx + inner} ${cy} A ${inner} ${inner} 0 1 1 ${cx - inner} ${cy} Z`,
    };
}
