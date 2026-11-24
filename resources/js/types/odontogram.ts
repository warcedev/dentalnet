export type Surface = 'vestibular' | 'lingual' | 'mesial' | 'distal' | 'oclusal';

export type EventStatus =
    'diagnosticado' | 'en_tratamiento' | 'tratado' | 'fallido' | 'en_seguimiento';

export type ToothInfo = {
    code: number;
    dentition: 'permanente' | 'temporal';
    quadrant: number;
    position: number;
    arch: 'superior' | 'inferior';
    type: 'incisivo' | 'canino' | 'premolar' | 'molar';
};

export type ChartEntry = {
    id: number;
    tooth_code: number;
    surface: Surface | null;
    scope: 'superficie' | 'diente' | 'boca';
    condition: string;
    condition_label: string;
    status: EventStatus;
    status_label: string;
    date: string;
};

export type ToothState = {
    absent: boolean;
    tooth: ChartEntry[];
    surfaces: Partial<Record<Surface, ChartEntry>>;
};

/** Estado del odontograma indexado por código FDI (solo dientes con registros). */
export type Chart = Record<string, ToothState>;

export type ToothEventRow = {
    id: number;
    tooth_code: number | null;
    surface: Surface | null;
    surface_label: string | null;
    condition: string;
    condition_label: string;
    status: EventStatus;
    status_label: string;
    detected_on: string;
    treated_on: string | null;
    notes: string | null;
    professional: string | null;
    created_at: string;
    corrects_event_id: number | null;
    correction_reason: string | null;
    is_void: boolean;
    is_superseded: boolean;
};

export type ConditionOption = {
    value: string;
    label: string;
    scope: 'superficie' | 'diente' | 'boca';
};

export type Dentition = 'permanente' | 'temporal' | 'mixta';
