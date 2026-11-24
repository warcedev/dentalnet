export type AlertItem = {
    kind: 'alergia' | 'condicion';
    level: 'critical' | 'warning';
    label: string;
    detail: string | null;
};

export type PatientAlertsData = {
    has_allergies: boolean;
    items: AlertItem[];
};

export type PatientHeader = {
    id: number;
    full_name: string;
    document: string;
    birth_date: string;
    age: number;
    is_minor: boolean;
    sex: string;
    phone: string | null;
    archived: boolean;
    /** null = el usuario no tiene permiso para ver alertas. */
    alerts: PatientAlertsData | null;
    can: {
        update: boolean;
        archive: boolean;
        restore: boolean;
        view_clinical: boolean;
        view_odontogram: boolean;
        view_treatments: boolean;
        view_consultations: boolean;
        view_documents: boolean;
        view_periodontogram: boolean;
        update_clinical: boolean;
        view_consents: boolean;
        collect_consent: boolean;
    };
};

export type PatientDetails = {
    id: number;
    first_name: string;
    last_name: string;
    document_type: string;
    document_number: string;
    birth_date: string;
    sex: string;
    phone: string | null;
    phone_alt: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    occupation: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    emergency_contact_relationship: string | null;
    insurer: string | null;
    insurance_number: string | null;
    guardian_name: string | null;
    guardian_document: string | null;
    guardian_relationship: string | null;
    guardian_phone: string | null;
    administrative_notes: string | null;
    accepts_communications: boolean;
    created_at: string | null;
};

export type PatientListItem = {
    id: number;
    full_name: string;
    document: string;
    age: number;
    phone: string | null;
    allergies: number | null;
    conditions: number | null;
};

export type RecordLifecycle = {
    is_active: boolean;
    recorded_by: string | null;
    created_at: string | null;
    deactivated_by: string | null;
    deactivated_at: string | null;
    deactivation_reason: string | null;
};
