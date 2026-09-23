<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StaffIdentityService
{
    public function ensure(User $user): string
    {
        if (! $user->isAdmin()) {
            throw new InvalidArgumentException('Only admin accounts can receive a staff ID.');
        }

        if (is_string($user->staff_id) && $user->staff_id !== '') {
            return $user->staff_id;
        }

        return DB::transaction(function () use ($user): string {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (is_string($locked->staff_id) && $locked->staff_id !== '') {
                $user->setAttribute('staff_id', $locked->staff_id);

                return $locked->staff_id;
            }

            $staffId = sprintf('VA-STF-%06d', $locked->getKey());
            $locked->forceFill(['staff_id' => $staffId])->save();
            $user->setAttribute('staff_id', $staffId);

            return $staffId;
        });
    }

    public function recordLogin(User $user, ?string $ipAddress): void
    {
        $this->ensure($user);

        $user->forceFill([
            'admin_last_login_at' => now(),
            'admin_last_login_ip' => $ipAddress,
        ])->save();
    }
}
