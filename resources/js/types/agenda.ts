export type AgendaView = 'dia' | 'semana' | 'mes' | 'lista';

export type AppointmentStatusValue =
    'programada' | 'confirmada' | 'en_curso' | 'atendida' | 'cancelada' | 'no_asistio';

export type AgendaAppointment = {
    id: number;
    /** Hora local "AAAA-MM-DD HH:mm". */
    starts_at: string;
    ends_at: string;
    duration: number;
    status: AppointmentStatusValue;
    status_label: string;
    transitions: { value: AppointmentStatusValue; label: string }[];
    notes: string | null;
    cancellation_reason: string | null;
    patient: { id: number; name: string; phone: string | null; has_allergies: boolean };
    professional: { id: number; name: string };
    room: { id: number; name: string };
    type: { id: number; name: string; color: string };
};

export type ScheduleBlockItem = {
    id: number;
    starts_at: string;
    ends_at: string;
    reason: string;
    professional: { id: number; name: string } | null;
    room: { id: number; name: string } | null;
};

export type NamedOption = { id: number; name: string };

export type AppointmentTypeOption = NamedOption & { duration_minutes: number; color: string };

export type PatientOption = { id: number; name: string; document: string; phone: string | null };

export type AgendaConfig = {
    opens_at: string;
    closes_at: string;
    slot_minutes: number;
    working_days: number[];
};
