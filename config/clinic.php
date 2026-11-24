<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos regionales
    |--------------------------------------------------------------------------
    */

    'currency' => 'PYG',
    'date_format' => 'd/m/Y',
    'datetime_format' => 'd/m/Y H:i',

    /*
    |--------------------------------------------------------------------------
    | Seguridad
    |--------------------------------------------------------------------------
    |
    | require_admin_2fa: los administradores deben tener la verificación en dos
    | pasos confirmada para usar el sistema (salvo las pantallas de seguridad).
    |
    */

    'require_admin_2fa' => (bool) env('CLINIC_REQUIRE_ADMIN_2FA', true),

    /*
    |--------------------------------------------------------------------------
    | Reglas clínicas
    |--------------------------------------------------------------------------
    |
    | cleaning_interval_months: la próxima limpieza recomendada se calcula
    | desde la última limpieza registrada en el odontograma.
    |
    */

    'cleaning_interval_months' => (int) env('CLINIC_CLEANING_INTERVAL_MONTHS', 6),

    /*
    |--------------------------------------------------------------------------
    | Datos del consultorio (se usan en recordatorios y documentos)
    |--------------------------------------------------------------------------
    */

    'address' => env('CLINIC_ADDRESS', ''),
    'phone' => env('CLINIC_PHONE', ''),

    /*
    |--------------------------------------------------------------------------
    | Agenda
    |--------------------------------------------------------------------------
    |
    | Horario de atención (las citas deben caer dentro), días hábiles
    | (1 = lunes … 7 = domingo) y granularidad de la grilla en minutos.
    |
    */

    'agenda' => [
        'opens_at' => env('AGENDA_OPENS_AT', '07:00'),
        'closes_at' => env('AGENDA_CLOSES_AT', '20:00'),
        'working_days' => array_map('intval', explode(',', (string) env('AGENDA_WORKING_DAYS', '1,2,3,4,5,6'))),
        'slot_minutes' => (int) env('AGENDA_SLOT_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recordatorios
    |--------------------------------------------------------------------------
    */

    'reminders' => [
        // Canal activo. Solo "email" está implementado en esta versión.
        'channel' => env('REMINDERS_CHANNEL', 'email'),
        // Aviso de próxima cita: cuántos días antes.
        'upcoming_days_before' => (int) env('REMINDERS_UPCOMING_DAYS', 3),
        // Confirmación: cuántas horas antes.
        'confirmation_hours_before' => (int) env('REMINDERS_CONFIRMATION_HOURS', 24),
        // Limpieza: avisar desde N días antes de la fecha recomendada.
        'cleaning_days_before' => (int) env('REMINDERS_CLEANING_DAYS', 7),
        // Control de tratamiento: días después del registro "en seguimiento".
        'control_after_days' => (int) env('REMINDERS_CONTROL_DAYS', 30),
        // Garantía: avisar este número de días antes de que venza.
        'warranty_days_before' => (int) env('REMINDERS_WARRANTY_DAYS', 30),
        // Hora local de envío para recordatorios que no dependen de una cita.
        'send_hour' => (int) env('REMINDERS_SEND_HOUR', 9),
        'max_attempts' => (int) env('REMINDERS_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentos
    |--------------------------------------------------------------------------
    |
    | Tipos aceptados (se verifica el contenido real del archivo, no la
    | extensión) y tamaño máximo en kilobytes.
    |
    */

    'documents' => [
        'max_kb' => (int) env('DOCUMENTS_MAX_KB', 15360),
        'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Respaldos
    |--------------------------------------------------------------------------
    */

    'backup' => [
        'path' => env('BACKUP_PATH', 'storage/app/backups'),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
        // Si se define, el .zip se cifra con AES-256 (recomendado).
        'password' => env('BACKUP_PASSWORD'),
        // Carpetas (relativas a la raíz del proyecto) que se incluyen junto con la base.
        'include' => [
            'storage/app/private',
        ],
    ],

    /*
    | Solo para pruebas locales: servir los archivos compilados aunque exista
    | public/hot de un Vite de desarrollo (por ejemplo VITE_HOT_FILE=/nonexistent).
    */
    'vite_hot_file' => env('VITE_HOT_FILE'),

];
