<?php

namespace App\Actions\Users;

use App\Data\StaffUserData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateStaffUser
{
    public function __construct(
        private readonly SyncUserRoles $syncRoles,
        private readonly EnsureAnActiveAdminRemains $adminGuard,
    ) {}

    public function handle(User $actor, User $target, StaffUserData $data): User
    {
        $this->adminGuard->handle($actor, $target, $data->roles, $data->isActive);

        return DB::transaction(function () use ($target, $data) {
            $target->update([
                'name' => $data->name,
                'email' => Str::lower($data->email),
                'phone' => $data->phone,
                'license_number' => $data->licenseNumber,
                'is_active' => $data->isActive,
            ]);

            $this->syncRoles->handle($target, $data->roles);

            return $target;
        });
    }
}
