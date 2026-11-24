<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

/*
 * Verifica la matriz de permisos explícita de cada rol. Si alguien cambia
 * Role::permissions(), estas pruebas obligan a revisar la decisión.
 */

it('otorga al administrador todos los permisos', function () {
    $admin = User::factory()->admin()->create();

    foreach (Permission::cases() as $permission) {
        expect($admin->can($permission->value))->toBeTrue("Falta {$permission->value}");
    }
});

it('no permite a recepción ver la ficha clínica ni administrar', function (Permission $permission) {
    $reception = User::factory()->reception()->create();

    expect($reception->can($permission->value))->toBeFalse();
})->with([
    Permission::ClinicalView,
    Permission::ClinicalUpdate,
    Permission::ConsultationsView,
    Permission::OdontogramRecord,
    Permission::PrescriptionsIssue,
    Permission::DocumentsView,
    Permission::UsersManage,
    Permission::AuditView,
    Permission::PaymentsVoid,
    Permission::PatientsArchive,
    Permission::ReportsView,
]);

it('permite a recepción ver alertas clínicas, agenda y registrar cobros', function (Permission $permission) {
    $reception = User::factory()->reception()->create();

    expect($reception->can($permission->value))->toBeTrue();
})->with([
    Permission::ClinicalAlertsView,
    Permission::AppointmentsManage,
    Permission::PaymentsRegister,
    Permission::PatientsCreate,
]);

it('permite al odontólogo el trabajo clínico pero no la administración', function () {
    $dentist = User::factory()->dentist()->create();

    expect($dentist->can(Permission::ClinicalUpdate->value))->toBeTrue()
        ->and($dentist->can(Permission::OdontogramRecord->value))->toBeTrue()
        ->and($dentist->can(Permission::PrescriptionsIssue->value))->toBeTrue()
        ->and($dentist->can(Permission::UsersManage->value))->toBeFalse()
        ->and($dentist->can(Permission::AuditView->value))->toBeFalse()
        ->and($dentist->can(Permission::PaymentsVoid->value))->toBeFalse();
});

it('un usuario sin rol no tiene permisos', function () {
    $user = User::factory()->create();

    foreach (Permission::cases() as $permission) {
        expect($user->can($permission->value))->toBeFalse();
    }
});

it('todos los roles existen en la base tras el seeder', function () {
    expect(Spatie\Permission\Models\Role::pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::values())->sort()->values()->all());
});
