<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'administrador';
    case Dentist = 'odontologo';
    case Reception = 'recepcion';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Dentist => 'Odontólogo',
            self::Reception => 'Recepción',
        };
    }

    /**
     * Matriz de permisos por rol. Es la única fuente de verdad: el seeder la
     * sincroniza con la base y las pruebas la verifican.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Suposición: el administrador es el responsable del consultorio y
            // tiene acceso completo. Si es personal no clínico, se le puede
            // quitar el acceso clínico creando otro rol.
            self::Admin => Permission::cases(),

            self::Dentist => [
                Permission::PatientsView,
                Permission::PatientsCreate,
                Permission::PatientsUpdate,
                Permission::ClinicalAlertsView,
                Permission::ClinicalView,
                Permission::ClinicalUpdate,
                Permission::ConsentsCollect,
                Permission::OdontogramView,
                Permission::OdontogramRecord,
                Permission::ConsultationsView,
                Permission::ConsultationsRecord,
                Permission::PrescriptionsIssue,
                Permission::DocumentsView,
                Permission::DocumentsUpload,
                Permission::AppointmentsView,
                Permission::AppointmentsManage,
                Permission::TreatmentsView,
                Permission::TreatmentsManage,
                Permission::PaymentsView,
                Permission::ReportsView,
            ],

            // Recepción ve las alertas (alergias, riesgos) pero no la ficha
            // clínica completa ni las notas de evolución.
            self::Reception => [
                Permission::PatientsView,
                Permission::PatientsCreate,
                Permission::PatientsUpdate,
                Permission::ClinicalAlertsView,
                Permission::ConsentsCollect,
                Permission::AppointmentsView,
                Permission::AppointmentsManage,
                Permission::RemindersManage,
                Permission::TreatmentsView,
                Permission::PaymentsView,
                Permission::PaymentsRegister,
            ],
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
