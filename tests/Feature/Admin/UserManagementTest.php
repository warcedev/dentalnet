<?php

use App\Actions\Users\EnsureAnActiveAdminRemains;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function staffPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Dra. Sofía Acosta',
        'email' => 'Sofia.Acosta@Consultorio.test',
        'phone' => '0981 123 456',
        'license_number' => 'RP-55555',
        'roles' => [Role::Dentist->value],
    ], $overrides);
}

describe('acceso', function () {
    it('permite al administrador ver el listado', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/index')->has('users.data', 1));
    });

    it('prohíbe el listado a odontólogos y recepción', function (string $state) {
        $user = User::factory()->{$state}()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.store'), staffPayload())->assertForbidden();
    })->with(['dentist', 'reception']);

    it('redirige al login a los invitados', function () {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    });
});

describe('alta', function () {
    it('crea el usuario con su rol, correo en minúsculas y envía el enlace de contraseña', function () {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), staffPayload())
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'sofia.acosta@consultorio.test')->firstOrFail();
        expect($user->hasRole(Role::Dentist->value))->toBeTrue()
            ->and($user->is_active)->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull();

        Notification::assertSentTo($user, ResetPassword::class);
    });

    it('exige registro profesional a los odontólogos', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), staffPayload(['license_number' => '']))
            ->assertSessionHasErrors(['license_number' => 'El registro profesional es obligatorio para los odontólogos.']);
    });

    it('valida en español el correo duplicado y el rol inválido', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'repetido@consultorio.test']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), staffPayload(['email' => 'repetido@consultorio.test', 'roles' => ['superusuario']]))
            ->assertSessionHasErrors(['email', 'roles.0']);

        expect(session('errors')->first('email'))->toContain('correo electrónico');
    });
});

describe('edición', function () {
    it('cambia roles y desactiva usuarios, dejando constancia en la auditoría', function () {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->reception()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => [Role::Reception->value, Role::Admin->value],
            'is_active' => false,
        ])->assertRedirect(route('admin.users.index'));

        $target->refresh();
        expect($target->is_active)->toBeFalse()
            ->and($target->hasRole(Role::Admin->value))->toBeTrue();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'roles_updated',
            'auditable_id' => $target->id,
            'user_id' => $admin->id,
        ]);
    });

    it('impide que un administrador se quite su propio rol o se desactive', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create(); // hay otro admin, igual no puede hacerlo sobre sí mismo

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [Role::Reception->value],
            'is_active' => true,
        ])->assertSessionHasErrors('roles');

        expect($admin->fresh()->isAdmin())->toBeTrue();
    });

    it('impide dejar el sistema sin administradores activos', function () {
        $onlyAdmin = User::factory()->admin()->create();
        User::factory()->admin()->inactive()->create();
        // Actor hipotético con permiso de gestión pero sin rol admin (p. ej. un rol futuro).
        $actor = User::factory()->create();

        expect(fn () => app(EnsureAnActiveAdminRemains::class)
            ->handle($actor, $onlyAdmin, [Role::Reception->value], true))
            ->toThrow(ValidationException::class, 'Debe quedar al menos un administrador activo.');

        expect(fn () => app(EnsureAnActiveAdminRemains::class)
            ->handle($actor, $onlyAdmin, [Role::Admin->value], false))
            ->toThrow(ValidationException::class);
    });

    it('permite desactivar a un administrador si queda otro activo', function () {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $this->actingAs($actor)->put(route('admin.users.update', $target), [
            'name' => $target->name, 'email' => $target->email,
            'roles' => [Role::Admin->value], 'is_active' => false,
        ])->assertSessionHasNoErrors();

        expect($target->fresh()->is_active)->toBeFalse();
    });

    it('no existe ruta para borrar usuarios', function () {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->delete("/admin/usuarios/{$target->id}")->assertStatus(405);
        expect($admin->can('delete', $target))->toBeFalse();
    });
});
