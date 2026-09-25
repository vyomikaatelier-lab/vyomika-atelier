<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Support\AdminAccess;

trait ActsAsAdmin
{
    protected function actingAsAdmin(?User $admin = null): static
    {
        $admin ??= User::factory()->admin()->create();

        return $this->actingAs($admin)
            ->withSession([
                AdminAccess::SESSION_KEY => true,
                AdminAccess::SESSION_VERSION_KEY => (int) ($admin->admin_session_version ?? 1),
                AdminAccess::SESSION_USER_KEY => $admin->getKey(),
            ]);
    }
}
