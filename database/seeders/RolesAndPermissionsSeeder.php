<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sincroniza roles y permisos con los enums. Es idempotente: se puede
 * ejecutar en producción después de cada actualización.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        // Elimina permisos que ya no existen en el catálogo.
        PermissionModel::query()->whereNotIn('name', Permission::values())->delete();

        // La caché de spatie quedó con la lista anterior: sin esto, los roles
        // no "ven" los permisos recién creados.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')
                ->syncPermissions(array_map(fn (Permission $p) => $p->value, $role->permissions()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
