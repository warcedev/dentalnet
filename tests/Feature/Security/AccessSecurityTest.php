<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

describe('usuarios desactivados', function () {
    it('no pueden iniciar sesión aunque la contraseña sea correcta', function () {
        User::factory()->inactive()->create(['email' => 'baja@consultorio.test']);

        $this->post(route('login.store'), ['email' => 'baja@consultorio.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Su cuenta está desactivada. Contacte al administrador.']);

        $this->assertGuest();
    });

    it('pierden la sesión abierta en cuanto se los desactiva', function () {
        $user = User::factory()->dentist()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    });
});

describe('verificación en dos pasos para administradores', function () {
    beforeEach(fn () => config(['clinic.require_admin_2fa' => true]));

    it('redirige al administrador sin 2FA a la configuración de seguridad', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertRedirect(route('security.edit'));
        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('security.edit'));
    });

    it('deja pasar al administrador con 2FA confirmado', function () {
        $admin = User::factory()->admin()->withTwoFactor()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    });

    it('no afecta a odontólogos ni recepción', function () {
        $this->actingAs(User::factory()->dentist()->create())->get(route('dashboard'))->assertOk();
    });

    it('se puede desactivar por configuración', function () {
        config(['clinic.require_admin_2fa' => false]);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.users.index'))->assertOk();
    });
});

describe('protecciones generales', function () {
    it('limita los intentos de inicio de sesión', function () {
        User::factory()->create(['email' => 'ana@consultorio.test']);

        foreach (range(1, 5) as $_) {
            $this->post(route('login.store'), ['email' => 'ana@consultorio.test', 'password' => 'mala']);
        }

        $this->post(route('login.store'), ['email' => 'ana@consultorio.test', 'password' => 'mala'])
            ->assertTooManyRequests();
    });

    it('no ofrece registro público', function () {
        expect(Route::has('register'))->toBeFalse();
        $this->get('/register')->assertNotFound();
    });

    it('envía cabeceras de seguridad y evita el caché de páginas con datos', function () {
        $this->actingAs(User::factory()->dentist()->create())
            ->get(route('dashboard'))
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin');

        expect($this->actingAs(User::factory()->dentist()->create())->get(route('dashboard'))->headers->get('Cache-Control'))
            ->toContain('no-store');
    });

    it('expira la sesión tras 30 minutos de inactividad y la cifra', function () {
        expect(config('session.lifetime'))->toBe(30)
            ->and(config('session.encrypt'))->toBeTrue()
            ->and(config('session.http_only'))->toBeTrue();
    });

    it('usa español, guaraníes y la zona horaria de Asunción', function () {
        expect(config('app.locale'))->toBe('es')
            ->and(config('app.timezone'))->toBe('America/Asuncion')
            ->and(config('clinic.currency'))->toBe('PYG')
            ->and(__('validation.required', ['attribute' => 'nombre']))->toBe('El campo nombre es obligatorio.');
    });
});

describe('páginas de error', function () {
    it('muestra la página de error en español ante un acceso prohibido', function () {
        $this->actingAs(User::factory()->reception()->create())
            ->get(route('admin.users.index'))
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page->component('error')->where('status', 403));
    });

});
