export type Option = { value: string; label: string };

export type StaffUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    license_number: string | null;
    is_active: boolean;
    roles: string[];
    two_factor_enabled: boolean;
    last_login_at: string | null;
};

export type AuditEntry = {
    id: number;
    created_at: string;
    user: string | null;
    event: string;
    event_label: string;
    model: string | null;
    model_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
};
