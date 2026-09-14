<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignInitialAdminOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_explicitly_assigns_initial_owner_and_staff_id(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'owner@example.com',
            'admin_role' => null,
            'admin_session_version' => 1,
        ]);

        $this->artisan('admin:assign-initial-owner', ['email' => $admin->email])
            ->expectsConfirmation("Assign {$admin->email} as the initial Owner?", 'yes')
            ->assertSuccessful();

        $admin->refresh();
        $this->assertSame(AdminRole::OWNER, $admin->admin_role);
        $this->assertSame(2, $admin->admin_session_version);
        $this->assertSame(sprintf('VA-STF-%06d', $admin->id), $admin->staff_id);
    }

    public function test_command_refuses_customer_inactive_admin_and_second_owner(): void
    {
        $customer = User::factory()->create(['email' => 'customer@example.com']);
        $this->artisan('admin:assign-initial-owner', ['email' => $customer->email])->assertFailed();

        $inactive = User::factory()->admin()->disabled()->create(['email' => 'inactive@example.com']);
        $this->artisan('admin:assign-initial-owner', ['email' => $inactive->email])->assertFailed();

        User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $candidate = User::factory()->admin()->create(['email' => 'candidate@example.com']);
        $this->artisan('admin:assign-initial-owner', ['email' => $candidate->email])->assertFailed();
    }
}
