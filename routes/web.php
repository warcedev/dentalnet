<?php

use App\Http\Controllers\Admin\AgendaSettingsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ConsentTemplateController;
use App\Http\Controllers\Admin\ProcedureController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Agenda\AgendaController;
use App\Http\Controllers\Agenda\AppointmentController;
use App\Http\Controllers\Agenda\ReminderController;
use App\Http\Controllers\Agenda\ScheduleBlockController;
use App\Http\Controllers\Agenda\WaitingListController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Patients\ClinicalRecordController;
use App\Http\Controllers\Patients\ConsentSignatureController;
use App\Http\Controllers\Patients\ConsultationController;
use App\Http\Controllers\Patients\DocumentController;
use App\Http\Controllers\Patients\OdontogramController;
use App\Http\Controllers\Patients\PatientController;
use App\Http\Controllers\Patients\PaymentController;
use App\Http\Controllers\Patients\PeriodontogramController;
use App\Http\Controllers\Patients\PortalAccessController;
use App\Http\Controllers\Patients\TreatmentController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Middleware\EnsurePortalAccountIsActive;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inicio')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('inicio', DashboardController::class)->name('dashboard');

    // Agenda
    Route::get('agenda', [AgendaController::class, 'index'])->name('agenda');
    Route::post('agenda/citas', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::put('agenda/citas/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::post('agenda/citas/{appointment}/estado', [AppointmentController::class, 'status'])->name('appointments.status');
    Route::post('agenda/bloqueos', [ScheduleBlockController::class, 'store'])->name('blocks.store');
    Route::delete('agenda/bloqueos/{scheduleBlock}', [ScheduleBlockController::class, 'destroy'])->name('blocks.destroy');
    Route::get('agenda/lista-espera', [WaitingListController::class, 'index'])->name('waiting-list.index');
    Route::post('agenda/lista-espera', [WaitingListController::class, 'store'])->name('waiting-list.store');
    Route::post('agenda/lista-espera/{waitingListEntry}/descartar', [WaitingListController::class, 'discard'])->name('waiting-list.discard');

    // Reportes
    Route::get('reportes', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reportes/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('reportes/{section}.csv', [ReportController::class, 'csv'])->name('reports.csv');

    // Recordatorios
    Route::get('recordatorios', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('recordatorios/ejecutar', [ReminderController::class, 'run'])->middleware('throttle:6,1')->name('reminders.run');
    Route::post('recordatorios/{reminder}/reintentar', [ReminderController::class, 'retry'])->name('reminders.retry');

    // Pacientes
    Route::get('pacientes', [PatientController::class, 'index'])->name('patients.index');
    Route::get('pacientes/nuevo', [PatientController::class, 'create'])->name('patients.create');
    Route::post('pacientes', [PatientController::class, 'store'])->name('patients.store');
    Route::get('pacientes/{patient}', [PatientController::class, 'show'])->withTrashed()->name('patients.show');
    Route::get('pacientes/{patient}/editar', [PatientController::class, 'edit'])->name('patients.edit');
    Route::put('pacientes/{patient}', [PatientController::class, 'update'])->name('patients.update');
    Route::delete('pacientes/{patient}', [PatientController::class, 'destroy'])->name('patients.archive');
    Route::post('pacientes/{patient}/restaurar', [PatientController::class, 'restore'])->withTrashed()->name('patients.restore');

    // Ficha clínica
    Route::scopeBindings()->prefix('pacientes/{patient}')->name('patients.')->group(function () {
        Route::get('ficha-clinica', [ClinicalRecordController::class, 'show'])->withTrashed()->name('clinical');
        Route::post('antecedentes', [ClinicalRecordController::class, 'storeHistory'])->name('history.store');
        Route::post('alergias', [ClinicalRecordController::class, 'storeAllergy'])->name('allergies.store');
        Route::post('alergias/{allergy}/desactivar', [ClinicalRecordController::class, 'deactivateAllergy'])->name('allergies.deactivate');
        Route::post('condiciones', [ClinicalRecordController::class, 'storeCondition'])->name('conditions.store');
        Route::post('condiciones/{condition}/desactivar', [ClinicalRecordController::class, 'deactivateCondition'])->name('conditions.deactivate');

        Route::get('odontograma', [OdontogramController::class, 'show'])->withTrashed()->name('odontogram');
        Route::post('odontograma/eventos', [OdontogramController::class, 'store'])->name('odontogram.store');
        Route::post('odontograma/eventos/{toothEvent}/corregir', [OdontogramController::class, 'correct'])->name('odontogram.correct');

        // Tratamientos y cobros
        Route::get('tratamientos', [TreatmentController::class, 'index'])->withTrashed()->name('treatments');
        Route::post('tratamientos', [TreatmentController::class, 'storePlan'])->name('treatments.plans.store');
        Route::post('tratamientos/{treatmentPlan}/cerrar', [TreatmentController::class, 'closePlan'])->name('treatments.plans.close');
        Route::post('tratamientos/{treatmentPlan}/items', [TreatmentController::class, 'storeItem'])->name('treatments.items.store');
        Route::put('tratamientos/{treatmentPlan}/items/{treatmentItem}', [TreatmentController::class, 'updateItem'])->name('treatments.items.update');
        Route::post('tratamientos/{treatmentPlan}/items/{treatmentItem}/estado', [TreatmentController::class, 'itemStatus'])->name('treatments.items.status');
        Route::post('tratamientos/{treatmentPlan}/items/{treatmentItem}/ajustes', [TreatmentController::class, 'adjust'])->name('treatments.items.adjust');
        Route::post('tratamientos/{treatmentPlan}/cuotas', [TreatmentController::class, 'storeInstallments'])->name('treatments.installments.store');
        Route::post('pagos', [PaymentController::class, 'store'])->middleware('throttle:30,1')->name('payments.store');
        Route::post('pagos/{payment}/anular', [PaymentController::class, 'void'])->name('payments.void');
        Route::get('pagos/{payment}/recibo.pdf', [PaymentController::class, 'receipt'])->withTrashed()->name('payments.receipt');

        // Documentos, laboratorio y recetas
        Route::get('documentos', [DocumentController::class, 'index'])->withTrashed()->name('documents');
        Route::post('documentos', [DocumentController::class, 'store'])->middleware('throttle:30,1')->name('documents.store');
        Route::get('documentos/{document}/archivo', [DocumentController::class, 'file'])->withTrashed()->name('documents.file');
        Route::delete('documentos/{document}', [DocumentController::class, 'archive'])->name('documents.archive');
        Route::post('laboratorio', [DocumentController::class, 'storeLabOrder'])->name('lab-orders.store');
        Route::post('laboratorio/{labOrder}/estado', [DocumentController::class, 'labOrderStatus'])->name('lab-orders.status');
        Route::post('recetas', [DocumentController::class, 'storePrescription'])->name('prescriptions.store');
        Route::get('recetas/{prescription}.pdf', [DocumentController::class, 'prescriptionPdf'])->withTrashed()->name('prescriptions.pdf');

        // Periodontograma
        Route::get('periodontograma', [PeriodontogramController::class, 'index'])->withTrashed()->name('periodontogram');
        Route::post('periodontograma', [PeriodontogramController::class, 'store'])->name('periodontogram.store');

        // Portal del paciente
        Route::post('portal/invitar', [PortalAccessController::class, 'invite'])->middleware('throttle:10,1')->name('portal.invite');
        Route::post('portal/activo', [PortalAccessController::class, 'toggle'])->name('portal.toggle');

        // Consultas
        Route::get('consultas', [ConsultationController::class, 'index'])->withTrashed()->name('consultations');
        Route::post('consultas', [ConsultationController::class, 'store'])->name('consultations.store');
        Route::post('consultas/{consultation}/adendas', [ConsultationController::class, 'storeAddendum'])->name('consultations.addenda.store');

        Route::get('consentimientos', [ConsentSignatureController::class, 'index'])->withTrashed()->name('consents.index');
        Route::get('consentimientos/firmar', [ConsentSignatureController::class, 'create'])->name('consents.create');
        Route::post('consentimientos', [ConsentSignatureController::class, 'store'])->middleware('throttle:20,1')->name('consents.store');
        Route::get('consentimientos/{consentSignature}', [ConsentSignatureController::class, 'show'])->withTrashed()->name('consents.show');
        Route::get('consentimientos/{consentSignature}/firma.png', [ConsentSignatureController::class, 'image'])->withTrashed()->name('consents.image');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('agenda', [AgendaSettingsController::class, 'index'])->name('agenda-settings');
        Route::post('agenda/tipos', [AgendaSettingsController::class, 'storeType'])->name('appointment-types.store');
        Route::put('agenda/tipos/{appointmentType}', [AgendaSettingsController::class, 'updateType'])->name('appointment-types.update');
        Route::post('agenda/consultorios', [AgendaSettingsController::class, 'storeRoom'])->name('rooms.store');
        Route::put('agenda/consultorios/{room}', [AgendaSettingsController::class, 'updateRoom'])->name('rooms.update');

        Route::get('procedimientos', [ProcedureController::class, 'index'])->name('procedures.index');
        Route::post('procedimientos', [ProcedureController::class, 'store'])->name('procedures.store');
        Route::put('procedimientos/{procedure}', [ProcedureController::class, 'update'])->name('procedures.update');

        Route::get('consentimientos', [ConsentTemplateController::class, 'index'])->name('consents.index');
        Route::get('consentimientos/nuevo', [ConsentTemplateController::class, 'create'])->name('consents.create');
        Route::post('consentimientos', [ConsentTemplateController::class, 'store'])->name('consents.store');
        Route::get('consentimientos/{consent}/editar', [ConsentTemplateController::class, 'edit'])->name('consents.edit');
        Route::put('consentimientos/{consent}', [ConsentTemplateController::class, 'update'])->name('consents.update');
        Route::post('consentimientos/{consent}/desactivar', [ConsentTemplateController::class, 'deactivate'])->name('consents.deactivate');

        Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
        Route::get('usuarios/nuevo', [UserController::class, 'create'])->name('users.create');
        Route::post('usuarios', [UserController::class, 'store'])->name('users.store');
        Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->name('users.edit');
        Route::put('usuarios/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('usuarios/{user}/enlace-contrasena', [UserController::class, 'sendPasswordLink'])
            ->middleware('throttle:10,1')
            ->name('users.password-link');

        Route::get('auditoria', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

/*
|--------------------------------------------------------------------------
| Portal del paciente (guard "patient", separado del personal)
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:patient')->group(function () {
        Route::get('ingresar', [PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('ingresar', [PortalAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
        Route::get('olvide-mi-contrasena', [PortalAuthController::class, 'showForgot'])->name('password.request');
        Route::post('olvide-mi-contrasena', [PortalAuthController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('contrasena/{token}', [PortalAuthController::class, 'editPassword'])->name('password.edit');
        Route::post('contrasena', [PortalAuthController::class, 'updatePassword'])->middleware('throttle:10,1')->name('password.update');
    });

    Route::middleware(['auth:patient', EnsurePortalAccountIsActive::class])->group(function () {
        Route::get('/', [PortalController::class, 'home'])->name('home');
        Route::get('odontograma', [PortalController::class, 'odontogram'])->name('odontogram');
        Route::get('historial', [PortalController::class, 'history'])->name('history');
        Route::get('documentos', [PortalController::class, 'documents'])->name('documents');
        Route::get('recetas/{prescription}.pdf', [PortalController::class, 'prescriptionPdf'])->name('prescriptions.pdf');
        Route::get('recibos/{payment}.pdf', [PortalController::class, 'receiptPdf'])->name('receipts.pdf');
        Route::post('salir', [PortalAuthController::class, 'logout'])->name('logout');
    });
});

require __DIR__.'/settings.php';
