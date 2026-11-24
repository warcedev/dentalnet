<?php

use App\Exceptions\ImmutableRecordException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditChainVerifier;
use App\Services\Audit\AuditLogger;
use App\Support\Database\ImmutableTable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

describe('registro de cambios', function () {
    it('registra quién, qué, cuándo, IP y valores anteriores y nuevos', function () {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Nombre Viejo']);

        $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
            ->put(route('admin.users.update', $target), [
                'name' => 'Nombre Nuevo',
                'email' => $target->email,
                'phone' => $target->phone,
                'roles' => ['recepcion'],
                'is_active' => true,
            ]);

        $log = AuditLog::where('event', 'updated')->where('auditable_id', $target->id)->latest('id')->firstOrFail();

        expect($log->user_id)->toBe($admin->id)
            ->and($log->auditable_type)->toBe(User::class)
            ->and($log->ip_address)->toBe('10.0.0.7')
            ->and($log->route)->toBe('admin.users.update')
            ->and($log->old_values)->toBe(['name' => 'Nombre Viejo'])
            ->and($log->new_values)->toBe(['name' => 'Nombre Nuevo'])
            ->and($log->created_at)->not->toBeNull();
    });

    it('nunca guarda contraseñas ni secretos en la auditoría', function () {
        $user = User::factory()->withTwoFactor()->create();
        $user->update(['password' => 'otra-clave-segura']);

        $raw = DB::table('audit_logs')->pluck('new_values')->implode(' ').DB::table('audit_logs')->pluck('old_values')->implode(' ');

        expect($raw)->not->toContain('password')
            ->and($raw)->not->toContain('two_factor_secret')
            ->and($raw)->not->toContain('remember_token');
    });

    it('enmascara los atributos cifrados', function () {
        $model = new class extends User
        {
            protected $table = 'users';

            protected function casts(): array
            {
                return [...parent::casts(), 'phone' => 'encrypted'];
            }
        };

        $model->forceFill(['name' => 'X', 'email' => 'x@consultorio.test', 'password' => 'secreta', 'phone' => '0981000000'])->save();

        $log = AuditLog::where('event', 'created')->latest('id')->first();
        expect($log->new_values['phone'])->toBe('[cifrado]');
    });

    it('registra inicios de sesión y los intentos fallidos', function () {
        $user = User::factory()->create(['email' => 'ana@consultorio.test']);

        $this->post(route('login.store'), ['email' => 'ana@consultorio.test', 'password' => 'incorrecta']);
        $this->post(route('login.store'), ['email' => 'ana@consultorio.test', 'password' => 'password']);

        expect(AuditLog::where('event', 'login_failed')->exists())->toBeTrue()
            ->and(AuditLog::where('event', 'login')->where('user_id', $user->id)->exists())->toBeTrue()
            ->and($user->fresh()->last_login_at)->not->toBeNull();
    });

    it('no registra nada si no cambió ningún dato auditable', function () {
        $user = User::factory()->create();
        $before = AuditLog::count();

        $user->touch();

        expect(AuditLog::count())->toBe($before);
    });
});

describe('inmutabilidad', function () {
    it('el modelo impide modificar o borrar registros', function () {
        $log = app(AuditLogger::class)->record('login');

        expect(fn () => $log->update(['event' => 'otro']))->toThrow(ImmutableRecordException::class)
            ->and(fn () => $log->delete())->toThrow(ImmutableRecordException::class);
    });

    it('la base de datos rechaza UPDATE y DELETE directos', function () {
        app(AuditLogger::class)->record('login');

        expect(fn () => DB::table('audit_logs')->update(['event' => 'alterado']))->toThrow(QueryException::class)
            ->and(fn () => DB::table('audit_logs')->delete())->toThrow(QueryException::class)
            ->and(DB::table('audit_logs')->count())->toBe(1);
    });

    it('no hay ruta para modificar la auditoría y solo el administrador la ve', function () {
        $this->actingAs(User::factory()->dentist()->create())->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs(User::factory()->reception()->create())->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.audit.index'))->assertOk();
    });
});

describe('cadena de integridad', function () {
    it('encadena cada registro con el anterior', function () {
        $logger = app(AuditLogger::class);
        $first = $logger->record('login');
        $second = $logger->record('logout');

        expect($first->previous_hash)->toBeNull()
            ->and($second->previous_hash)->toBe($first->hash)
            ->and(app(AuditChainVerifier::class)->verify())->toBe([]);

        $this->artisan('audit:verify')->assertSuccessful();
    });

    it('detecta un registro alterado por fuera del sistema', function () {
        $logger = app(AuditLogger::class);
        $logger->record('login', new: ['email' => 'a@b.c']);
        $tampered = $logger->record('logout');
        $logger->record('login');

        // Simula a alguien con acceso directo a la base que desactiva la protección.
        ImmutableTable::release('audit_logs');
        DB::table('audit_logs')->where('id', $tampered->id)->update(['event' => 'login']);
        ImmutableTable::protect('audit_logs');

        $problems = app(AuditChainVerifier::class)->verify();

        expect($problems)->toHaveCount(1)
            ->and($problems[0]['id'])->toBe($tampered->id);

        $this->artisan('audit:verify')->assertFailed();
    });

    it('detecta un registro eliminado', function () {
        $logger = app(AuditLogger::class);
        $logger->record('login');
        $deleted = $logger->record('logout');
        $logger->record('login');

        ImmutableTable::release('audit_logs');
        DB::table('audit_logs')->where('id', $deleted->id)->delete();
        ImmutableTable::protect('audit_logs');

        expect(app(AuditChainVerifier::class)->verify())->not->toBe([]);
    });
});
