<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Personal de demostración. Todos usan la contraseña "password".
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de demostración no se cargan en producción.');
        }

        $staff = [
            ['Laura Ortiz', 'admin@consultorio.test', [Role::Admin], null, true],
            ['Dra. María Benítez', 'maria.benitez@consultorio.test', [Role::Dentist, Role::Admin], 'RP-10234', true],
            ['Dr. Carlos Giménez', 'carlos.gimenez@consultorio.test', [Role::Dentist], 'RP-11871', true],
            ['Ana Duarte', 'recepcion@consultorio.test', [Role::Reception], null, true],
            ['Pedro Ramírez', 'pedro.ramirez@consultorio.test', [Role::Reception], null, false],
        ];

        foreach ($staff as [$name, $email, $roles, $license, $active]) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => $email,
                'license_number' => $license,
                'is_active' => $active,
            ]);

            $user->syncRoles(array_map(fn (Role $r) => $r->value, $roles));
        }
    }
}
