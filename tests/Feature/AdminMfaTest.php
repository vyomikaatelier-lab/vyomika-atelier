<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminMfa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class AdminMfaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_password_login_with_mfa_redirects_to_challenge_not_dashboard(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $mfa = app(AdminMfa::class);
        $admin = User::factory()->admin()->create([
            'password' => 'password',
            'two_factor_secret' => $mfa->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $mfa->hashRecoveryCodes(['ABCD-EFGH']),
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.mfa.challenge'));

        $this->assertFalse((bool) session(AdminAccess::SESSION_KEY));
        $this->get(route('admin.dashboard'))->assertRedirect();
    }

    public function test_valid_totp_grants_admin_access(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $mfa = app(AdminMfa::class);
        $admin = User::factory()->admin()->create([
            'password' => 'password',
            'two_factor_secret' => $mfa->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $mfa->hashRecoveryCodes(['ABCD-EFGH']),
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $code = $google2fa->getCurrentOtp($secret);

        $this->post(route('admin.mfa.challenge.submit'), [
            'code' => $code,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue((bool) session(AdminAccess::SESSION_KEY));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_recovery_code_is_single_use(): void
    {
        $mfa = app(AdminMfa::class);
        $secret = (new Google2FA)->generateSecretKey();
        $admin = User::factory()->admin()->create([
            'password' => 'password',
            'two_factor_secret' => $mfa->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $mfa->hashRecoveryCodes(['AAAA-BBBB']),
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->post(route('admin.mfa.challenge.submit'), [
            'code' => 'AAAA-BBBB',
        ])->assertRedirect(route('admin.dashboard'));

        Auth::logout();
        session()->flush();

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->post(route('admin.mfa.challenge.submit'), [
            'code' => 'AAAA-BBBB',
        ])->assertSessionHasErrors('code');
    }

    public function test_expired_grace_requires_enrollment_before_dashboard(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => 'password',
            'two_factor_grace_ends_at' => now()->subMinute(),
            'two_factor_confirmed_at' => null,
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.mfa.enroll'));

        $this->assertFalse((bool) session(AdminAccess::SESSION_KEY));
    }

    public function test_within_grace_allows_dashboard_without_mfa(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => 'password',
            'two_factor_grace_ends_at' => now()->addDays(3),
            'two_factor_confirmed_at' => null,
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue((bool) session(AdminAccess::SESSION_KEY));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_enroll_requires_password_and_valid_code(): void
    {
        $google2fa = new Google2FA;
        $admin = User::factory()->admin()->create([
            'password' => 'password',
            'two_factor_grace_ends_at' => now()->subMinute(),
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->get(route('admin.mfa.enroll'))->assertOk();
        $secret = session(AdminMfa::SESSION_SETUP_SECRET);
        $this->assertNotEmpty($secret);

        $this->post(route('admin.mfa.enroll.submit'), [
            'current_password' => 'password',
            'code' => $google2fa->getCurrentOtp($secret),
        ])->assertRedirect(route('admin.mfa.recovery'));

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertTrue((bool) session(AdminAccess::SESSION_KEY));
    }

    public function test_mfa_reset_command_clears_secret_and_requires_force(): void
    {
        $mfa = app(AdminMfa::class);
        $admin = User::factory()->admin()->create([
            'email' => 'ops-admin@example.test',
            'two_factor_secret' => $mfa->encryptSecret('SECRETSECRETSECRETSE'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $mfa->hashRecoveryCodes(['ZZZZ-YYYY']),
        ]);

        $this->artisan('admin:mfa-reset', ['email' => $admin->email])
            ->assertFailed();

        $this->artisan('admin:mfa-reset', [
            'email' => $admin->email,
            '--force' => true,
        ])
            ->expectsConfirmation("Clear MFA for {$admin->email} (id {$admin->id}) and revoke all sessions?", 'yes')
            ->assertSuccessful();

        $admin->refresh();
        $this->assertNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);
    }
}
