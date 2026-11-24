<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'clinic:create-admin';

    protected $description = 'Crea un usuario administrador (para la instalación inicial)';

    public function handle(): int
    {
        $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $name = text('Nombre completo', required: true);
        $email = mb_strtolower(text('Correo electrónico', required: true, validate: fn ($v) => Validator::make(
            ['email' => $v], ['email' => ['email:rfc', 'unique:users,email']],
            attributes: ['email' => 'correo electrónico'],
        )->errors()->first('email') ?: null));
        $pass = password('Contraseña', required: true, validate: fn ($v) => Validator::make(
            ['password' => $v], ['password' => [Password::defaults()]],
            attributes: ['password' => 'contraseña'],
        )->errors()->first('password') ?: null);

        $user = new User(['name' => $name, 'email' => $email, 'password' => $pass, 'is_active' => true]);
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole(Role::Admin->value);

        $this->info("Administrador {$email} creado. Al ingresar deberá activar la verificación en dos pasos.");

        return self::SUCCESS;
    }
}
