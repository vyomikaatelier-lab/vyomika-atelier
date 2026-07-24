<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_requires_admin_login_session_flag(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_grants_panel_access(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'password' => 'password',
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_storefront_account_route_does_not_expose_admin_panel(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->withSession([AdminAccess::SESSION_KEY => true])
            ->get(route('account'))
            ->assertRedirect(route('home'));
    }

    public function test_admin_panel_access_is_cleared_when_admin_access_flag_removed(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
            'password' => 'password',
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk();

        $this->withSession([AdminAccess::SESSION_KEY => false])
            ->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }
}
