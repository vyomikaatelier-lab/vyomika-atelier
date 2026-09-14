<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StaffIdentityService;
use App\Support\AdminRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignInitialAdminOwner extends Command
{
    protected $signature = 'admin:assign-initial-owner {email}';

    protected $description = 'Explicitly assign the first Owner role to an existing active admin';

    public function handle(StaffIdentityService $identities): int
    {
        if (User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->where('admin_role', AdminRole::OWNER)
            ->exists()) {
            $this->error('An active Owner already exists. Use the Staff & Roles screen for later changes.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user?->isAdmin() || ! $user->is_active) {
            $this->error('The email must belong to an existing active admin account.');

            return self::FAILURE;
        }

        if (! $this->confirm("Assign {$user->email} as the initial Owner?")) {
            $this->warn('No changes made.');

            return self::FAILURE;
        }

        $staffId = DB::transaction(function () use ($user, $identities): ?string {
            $admins = User::query()
                ->where('is_admin', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($admins->contains(fn (User $admin) => $admin->is_active && $admin->admin_role === AdminRole::OWNER)) {
                return null;
            }

            /** @var User $locked */
            $locked = $admins->firstWhere('id', $user->getKey());
            $locked->forceFill([
                'admin_role' => AdminRole::OWNER,
                'admin_session_version' => (int) $locked->admin_session_version + 1,
            ])->save();

            return $identities->ensure($locked);
        }, 3);

        if ($staffId === null) {
            $this->error('An active Owner was assigned concurrently. No changes were made to this account.');

            return self::FAILURE;
        }

        $this->info("Initial Owner assigned: {$staffId} ({$user->email}). Existing admin sessions were revoked.");

        return self::SUCCESS;
    }
}
