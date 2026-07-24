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
            ->withSession([AdminAccess::SESSION_KEY => true]);
    }
}
