<?php

namespace App\Actions\Users;

use App\Data\StaffUserData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Da de alta a un integrante del personal. No se define contraseña: el
 * usuario recibe un enlace para crearla.
 */
class CreateStaffUser
{
    public function __construct(
        private readonly SyncUserRoles $syncRoles,
        private readonly SendPasswordSetupLink $sendSetupLink,
    ) {}

    public function handle(StaffUserData $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = new User([
                'name' => $data->name,
                'email' => Str::lower($data->email),
                'phone' => $data->phone,
                'license_number' => $data->licenseNumber,
                'is_active' => true,
                'password' => Str::password(40),
            ]);
            $user->email_verified_at = now();
            $user->save();

            $this->syncRoles->handle($user, $data->roles);

            return $user;
        });

        $this->sendSetupLink->handle($user);

        return $user;
    }
}
