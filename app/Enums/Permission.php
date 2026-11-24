<?php

namespace App\Enums;

/**
 * Catálogo completo de permisos del sistema.
 *
 * Se define completo desde la Fase 1 para que la matriz rol → permisos sea
 * estable; los módulos se van habilitando por fase.
 */
enum Permission: string
{
    // Administración
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';
    case SettingsManage = 'settings.manage';

    // Pacientes
    case PatientsView = 'patients.view';
    case PatientsCreate = 'patients.create';
    case PatientsUpdate = 'patients.update';
    case PatientsArchive = 'patients.archive';

    // Información clínica
    case ClinicalAlertsView = 'clinical-alerts.view';
    case ClinicalView = 'clinical.view';
    case ClinicalUpdate = 'clinical.update';
    case ConsentsCollect = 'consents.collect';
    case OdontogramView = 'odontogram.view';
    case OdontogramRecord = 'odontogram.record';
    case ConsultationsView = 'consultations.view';
    case ConsultationsRecord = 'consultations.record';
    case PrescriptionsIssue = 'prescriptions.issue';
    case DocumentsView = 'documents.view';
    case DocumentsUpload = 'documents.upload';

    // Agenda y recordatorios
    case AppointmentsView = 'appointments.view';
    case AppointmentsManage = 'appointments.manage';
    case RemindersManage = 'reminders.manage';

    // Tratamientos y cobros
    case TreatmentsView = 'treatments.view';
    case TreatmentsManage = 'treatments.manage';
    case PaymentsView = 'payments.view';
    case PaymentsRegister = 'payments.register';
    case PaymentsVoid = 'payments.void';

    // Reportes
    case ReportsView = 'reports.view';

    public function label(): string
    {
        return match ($this) {
            self::UsersManage => 'Gestionar usuarios',
            self::AuditView => 'Ver auditoría',
            self::SettingsManage => 'Configurar el consultorio',
            self::PatientsView => 'Ver pacientes',
            self::PatientsCreate => 'Registrar pacientes',
            self::PatientsUpdate => 'Editar datos de pacientes',
            self::PatientsArchive => 'Archivar pacientes',
            self::ClinicalAlertsView => 'Ver alertas clínicas',
            self::ClinicalView => 'Ver ficha clínica',
            self::ClinicalUpdate => 'Editar ficha clínica',
            self::ConsentsCollect => 'Gestionar consentimientos',
            self::OdontogramView => 'Ver odontograma',
            self::OdontogramRecord => 'Registrar en odontograma',
            self::ConsultationsView => 'Ver consultas',
            self::ConsultationsRecord => 'Registrar consultas',
            self::PrescriptionsIssue => 'Emitir recetas',
            self::DocumentsView => 'Ver documentos clínicos',
            self::DocumentsUpload => 'Cargar documentos clínicos',
            self::AppointmentsView => 'Ver agenda',
            self::AppointmentsManage => 'Gestionar citas',
            self::RemindersManage => 'Gestionar recordatorios',
            self::TreatmentsView => 'Ver planes de tratamiento',
            self::TreatmentsManage => 'Gestionar planes de tratamiento',
            self::PaymentsView => 'Ver cobros',
            self::PaymentsRegister => 'Registrar cobros',
            self::PaymentsVoid => 'Anular cobros',
            self::ReportsView => 'Ver reportes',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
