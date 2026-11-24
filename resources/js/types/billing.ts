export type MoneySummary = { proposed: number; billed: number; paid: number; balance: number };

export type TreatmentItemRow = {
    id: number;
    procedure: string;
    description: string | null;
    tooth_code: number | null;
    surface: string | null;
    surface_label: string | null;
    price: number;
    effective_price: number;
    warranty_months: number;
    warranty_until: string | null;
    warranty_expired: boolean;
    status: 'propuesto' | 'aceptado' | 'en_curso' | 'completado' | 'cancelado';
    status_label: string;
    transitions: { value: string; label: string }[];
    completed_on: string | null;
    cancellation_reason: string | null;
    editable: boolean;
    adjustments: {
        id: number;
        type: string;
        old_value: string | null;
        new_value: string | null;
        is_money: boolean;
        reason: string;
        author: string;
        created_at: string;
    }[];
};

export type InstallmentRow = {
    id: number;
    number: number;
    due_date: string;
    amount: number;
    paid: number;
    pending: number;
    status: 'pagada' | 'parcial' | 'pendiente' | 'vencida';
    status_label: string;
};

export type TreatmentPlanRow = {
    id: number;
    title: string;
    professional: string;
    notes: string | null;
    closed: boolean;
    created_at: string | null;
    summary: MoneySummary;
    installments: InstallmentRow[];
    items: TreatmentItemRow[];
};

export type PaymentRow = {
    id: number;
    receipt_number: number;
    amount: number;
    method: string;
    reference: string | null;
    paid_at: string;
    plan: string | null;
    received_by: string;
    voided: boolean;
    void_reason: string | null;
    voided_by: string | null;
    receipt_url: string;
};

export type ProcedureOption = {
    id: number;
    name: string;
    default_price: number;
    warranty_months: number;
};
