<?php

namespace App\Support;

use App\Models\Allergy;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Consent;
use App\Models\ConsentSignature;
use App\Models\Consultation;
use App\Models\ConsultationAddendum;
use App\Models\Document;
use App\Models\Installment;
use App\Models\LabOrder;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\PatientCondition;
use App\Models\Payment;
use App\Models\PeriodontalRecord;
use App\Models\Prescription;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\ScheduleBlock;
use App\Models\ToothEvent;
use App\Models\TreatmentAdjustment;
use App\Models\TreatmentItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\WaitingListEntry;

/**
 * Textos en español para mostrar la auditoría.
 */
final class AuditLabels
{
    /** @var array<string, string> */
    public const EVENTS = [
        'created' => 'Alta',
        'updated' => 'Modificación',
        'deleted' => 'Eliminación',
        'archived' => 'Archivado',
        'restored' => 'Restauración',
        'login' => 'Inicio de sesión',
        'logout' => 'Cierre de sesión',
        'login_failed' => 'Intento de acceso fallido',
        'login_locked' => 'Acceso bloqueado por intentos',
        'password_reset' => 'Contraseña restablecida',
        'password_link_sent' => 'Enlace de contraseña enviado',
        'roles_updated' => 'Cambio de roles',
        'portal_invited' => 'Invitación al portal',
        'portal_login' => 'Ingreso al portal',
        'portal_login_failed' => 'Intento fallido en el portal',
        'portal_password_set' => 'Contraseña del portal creada',
    ];

    /** @var array<class-string, string> */
    public const MODELS = [
        User::class => 'Usuario',
        Patient::class => 'Paciente',
        MedicalHistory::class => 'Antecedentes médicos',
        Allergy::class => 'Alergia',
        PatientCondition::class => 'Condición de riesgo',
        Consent::class => 'Plantilla de consentimiento',
        ConsentSignature::class => 'Consentimiento firmado',
        ToothEvent::class => 'Odontograma',
        Appointment::class => 'Cita',
        AppointmentType::class => 'Tipo de cita',
        Room::class => 'Consultorio',
        ScheduleBlock::class => 'Bloqueo de horario',
        WaitingListEntry::class => 'Lista de espera',
        Procedure::class => 'Procedimiento',
        TreatmentPlan::class => 'Plan de tratamiento',
        TreatmentItem::class => 'Tratamiento',
        TreatmentAdjustment::class => 'Ajuste de tratamiento',
        Installment::class => 'Cuota',
        Payment::class => 'Pago',
        Consultation::class => 'Consulta',
        ConsultationAddendum::class => 'Adenda de consulta',
        Document::class => 'Documento',
        LabOrder::class => 'Orden de laboratorio',
        Prescription::class => 'Receta / indicaciones',
        PeriodontalRecord::class => 'Periodontograma',
        PatientAccount::class => 'Cuenta del portal',
    ];

    public static function event(string $event): string
    {
        return self::EVENTS[$event] ?? $event;
    }

    public static function model(?string $type): ?string
    {
        return $type === null ? null : (self::MODELS[$type] ?? class_basename($type));
    }
}
