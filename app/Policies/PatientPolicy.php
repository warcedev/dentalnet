<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::PatientsView->value);
    }

    /** Un paciente archivado solo lo ve quien puede archivar/restaurar. */
    public function view(User $actor, Patient $patient): bool
    {
        return $patient->trashed()
            ? $actor->can(Permission::PatientsArchive->value)
            : $actor->can(Permission::PatientsView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::PatientsCreate->value);
    }

    public function update(User $actor, Patient $patient): bool
    {
        return ! $patient->trashed() && $actor->can(Permission::PatientsUpdate->value);
    }

    /** Archivar (borrado lógico). */
    public function delete(User $actor, Patient $patient): bool
    {
        return ! $patient->trashed() && $actor->can(Permission::PatientsArchive->value);
    }

    public function restore(User $actor, Patient $patient): bool
    {
        return $patient->trashed() && $actor->can(Permission::PatientsArchive->value);
    }

    /** Nunca se borra físicamente un paciente. */
    public function forceDelete(User $actor, Patient $patient): bool
    {
        return false;
    }

    public function viewAlerts(User $actor, Patient $patient): bool
    {
        return $actor->can(Permission::ClinicalAlertsView->value);
    }

    public function viewClinicalRecord(User $actor, Patient $patient): bool
    {
        return $actor->can(Permission::ClinicalView->value);
    }

    public function updateClinicalRecord(User $actor, Patient $patient): bool
    {
        return ! $patient->trashed() && $actor->can(Permission::ClinicalUpdate->value);
    }

    public function viewOdontogram(User $actor, Patient $patient): bool
    {
        return $actor->can(Permission::OdontogramView->value);
    }

    public function recordOdontogram(User $actor, Patient $patient): bool
    {
        return ! $patient->trashed() && $actor->can(Permission::OdontogramRecord->value);
    }

    public function viewDocuments(User $actor, Patient $patient): bool
    {
        return $actor->can(Permission::DocumentsView->value)
            || $actor->can(Permission::TreatmentsView->value);
    }

    public function uploadDocuments(User $actor, Patient $patient): bool
    {
        return ! $patient->trashed() && $actor->can(Permission::DocumentsUpload->value);
    }

    public function viewPrescriptions(User $actor, Patient $patient): bool
    {
        return $actor->can(Permission::ClinicalView->value) || $actor->can(Permission::PrescriptionsIssue->value);
    }

    public function issuePrescriptions(User $actor, Patient $patient): bool
    {
        return ! $patient->trashed() && $actor->can(Permission::PrescriptionsIssue->value);
    }

    public function manageLabOrders(User $actor, Patient $patient): bool
    {
        return ! $patient->trashed() && $actor->can(Permission::TreatmentsManage->value);
    }

    public function managePortal(User $actor, Patient $patient): bool
    {
        return ! $patient->trashed() && $actor->can(Permission::PatientsUpdate->value);
    }

    public function collectConsent(User $actor, Patient $patient): bool
    {
        return ! $patient->trashed() && $actor->can(Permission::ConsentsCollect->value);
    }

    public function viewConsents(User $actor, Patient $patient): bool
    {
        return $actor->can(Permission::ConsentsCollect->value) || $actor->can(Permission::ClinicalView->value);
    }
}
