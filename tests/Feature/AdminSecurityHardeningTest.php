<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class AdminSecurityHardeningTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard_or_settings(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.settings.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.settings.update'), [])->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_access_admin_products(): void
    {
        $customer = User::factory()->create(['is_admin' => false, 'is_active' => true]);

        $this->actingAs($customer)
            ->get(route('admin.products.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_uses_enumeration_safe_failure_message(): void
    {
        $customer = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
            'password' => 'password',
        ]);

        $this->from(route('admin.login'))
            ->post(route('admin.login.submit'), [
                'email' => $customer->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Invalid email or password.']);

        $this->from(route('admin.login'))
            ->post(route('admin.login.submit'), [
                'email' => 'nobody@example.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Invalid email or password.']);
    }

    public function test_admin_login_page_sends_noindex_meta(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_robots_txt_disallows_admin(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /admin/', $robots);
    }

    public function test_settings_update_requires_current_password(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => 'password',
        ]);

        $this->actingAsAdmin($admin)
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                'brand_name' => 'Test Brand',
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors('current_password');
    }

    public function test_disabling_customer_requires_current_password(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => 'password',
        ]);
        $customer = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
            'mobile' => '9876543210',
        ]);

        $this->actingAsAdmin($admin)
            ->from(route('admin.customers.show', $customer))
            ->put(route('admin.customers.update', $customer), [
                'name' => $customer->name,
                'email' => $customer->email,
                'mobile' => $customer->mobile,
                'account_type' => $customer->account_type ?? 'customer',
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.customers.show', $customer))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_is_admin_is_not_mass_assignable(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $user->update(['is_admin' => true, 'name' => 'Changed']);

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertSame('Changed', $user->fresh()->name);
    }

    public function test_admin_login_success_regenerates_session_and_sets_access_flag(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => 'password',
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(session(AdminAccess::SESSION_KEY));
    }
}
