<?php

namespace App\Http\Presenters;

use App\Enums\Permission;
use App\Models\Patient;
use App\Models\User;
use App\Services\Clinical\PatientAlerts;

/**
 * Datos del paciente para la interfaz. El encabezado (con las alertas
 * clínicas) se envía en todas las pantallas del paciente.
 */
class PatientPresenter
{
    public function __construct(private readonly PatientAlerts $alerts) {}

    /** @return array<string, mixed> */
    public function header(Patient $patient, User $user): array
    {
        return [
            'id' => $patient->id,
            'full_name' => $patient->full_name,
            'document' => $patient->document_type->label().' '.$patient->document_number,
            'birth_date' => $patient->birth_date->toDateString(),
            'age' => $patient->age,
            'is_minor' => $patient->is_minor,
            'sex' => $patient->sex->label(),
            'phone' => $patient->phone,
            'archived' => $patient->trashed(),
            'alerts' => $user->can('viewAlerts', $patient) ? $this->alerts->for($patient) : null,
            'can' => [
                'update' => $user->can('update', $patient),
                'archive' => $user->can('delete', $patient),
                'restore' => $user->can('restore', $patient),
                'view_clinical' => $user->can('viewClinicalRecord', $patient),
                'view_odontogram' => $user->can('viewOdontogram', $patient),
                'view_treatments' => $user->can(Permission::TreatmentsView->value) || $user->can(Permission::PaymentsView->value),
                'view_consultations' => $user->can(Permission::ConsultationsView->value),
                'view_documents' => $user->can('viewDocuments', $patient),
                'view_periodontogram' => $user->can('viewOdontogram', $patient),
                'update_clinical' => $user->can('updateClinicalRecord', $patient),
                'view_consents' => $user->can('viewConsents', $patient),
                'collect_consent' => $user->can('collectConsent', $patient),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function details(Patient $patient): array
    {
        return [
            ...$patient->only([
                'first_name', 'last_name', 'document_number', 'phone', 'phone_alt', 'email', 'address', 'city',
                'occupation', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
                'insurer', 'insurance_number', 'guardian_name', 'guardian_document', 'guardian_phone',
                'administrative_notes', 'accepts_communications',
            ]),
            'id' => $patient->id,
            'document_type' => $patient->document_type->value,
            'birth_date' => $patient->birth_date->toDateString(),
            'sex' => $patient->sex->value,
            'guardian_relationship' => $patient->guardian_relationship?->value,
            'created_at' => $patient->created_at?->toIso8601String(),
        ];
    }
}
