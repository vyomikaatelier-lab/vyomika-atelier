<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminAuthFlow;
use App\Support\AdminMfa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * S1 — admin authentication must only honour safe internal intended URLs.
 * S2 — passkey authentication must rotate the session id.
 * S3 — disabling MFA requires the current password and a live TOTP code.
 * Plus regression cover for admin login throttling and logout teardown.
 */
class AdminAuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    /** Destinations that must never be used as a post-login redirect. */
    public static function hostileIntendedUrls(): array
    {
        return [
            'absolute external https' => ['https://evil.test/admin'],
            'absolute external http' => ['http://evil.test/admin'],
            'protocol relative' => ['//evil.test/admin'],
            'backslash authority' => ['/\\evil.test/admin'],
            'unc path' => ['\\\\evil.test\\share'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'traversal' => ['/admin/../../etc/passwd'],
            'null byte' => ["/admin/products\0/x"],
            'empty' => [''],
        ];
    }

    private function graceAdmin(array $overrides = []): User
    {
        return User::factory()->admin()->create(array_merge([
            'password' => 'password',
            'two_factor_grace_ends_at' => now()->addDays(3),
            'two_factor_confirmed_at' => null,
        ], $overrides));
    }

    private function mfaAdmin(?string &$secret = null): User
    {
        $secret = (new Google2FA)->generateSecretKey();
        $mfa = app(AdminMfa::class);

        return User::factory()->admin()->create([
            'password' => 'password',
            'two_factor_secret' => $mfa->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $mfa->hashRecoveryCodes(['AAAA-BBBB']),
        ]);
    }

    // ------------------------------------------------------------------
    // S1 — safe intended redirects
    // ------------------------------------------------------------------

    public function test_password_login_honours_safe_internal_intended_url(): void
    {
        $admin = $this->graceAdmin();

        $this->withSession(['url.intended' => route('admin.products.index')])
            ->post(route('admin.login.submit'), [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.products.index'));
    }

    #[DataProvider('hostileIntendedUrls')]
    public function test_password_login_rejects_hostile_intended_url(string $intended): void
    {
        $admin = $this->graceAdmin();

        $this->withSession(['url.intended' => $intended])
            ->post(route('admin.login.submit'), [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_mfa_completion_honours_safe_internal_intended_url(): void
    {
        $admin = $this->mfaAdmin($secret);

        $this->withSession(['url.intended' => route('admin.products.index')])
            ->post(route('admin.login.submit'), [
                'email' => $admin->email,
                'password' => 'password',
            ])->assertRedirect(route('admin.mfa.challenge'));

        $this->post(route('admin.mfa.challenge.submit'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect(route('admin.products.index'));

        $this->assertTrue((bool) session(AdminAccess::SESSION_KEY));
    }

    #[DataProvider('hostileIntendedUrls')]
    public function test_mfa_completion_rejects_hostile_intended_url(string $intended): void
    {
        $admin = $this->mfaAdmin($secret);

        $this->withSession(['url.intended' => $intended])
            ->post(route('admin.login.submit'), [
                'email' => $admin->email,
                'password' => 'password',
            ])->assertRedirect(route('admin.mfa.challenge'));

        $this->post(route('admin.mfa.challenge.submit'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue((bool) session(AdminAccess::SESSION_KEY));
    }

    public function test_passkey_completion_honours_safe_internal_intended_url(): void
    {
        $admin = $this->mfaAdmin($secret);
        $request = $this->passkeyRequest(['url.intended' => route('admin.products.index')]);

        $response = app(AdminAuthFlow::class)->completeAdminLogin($request, $admin, 'passkey');

        $this->assertSame(route('admin.products.index'), $response->getTargetUrl());
    }

    #[DataProvider('hostileIntendedUrls')]
    public function test_passkey_completion_rejects_hostile_intended_url(string $intended): void
    {
        $admin = $this->mfaAdmin($secret);
        $request = $this->passkeyRequest(['url.intended' => $intended]);

        $response = app(AdminAuthFlow::class)->completeAdminLogin($request, $admin, 'passkey');

        $this->assertSame(route('admin.dashboard'), $response->getTargetUrl());
    }

    public function test_mfa_enrollment_routing_is_not_broken_by_intended_url_filtering(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => 'password',
            'two_factor_grace_ends_at' => now()->subDay(),
            'two_factor_confirmed_at' => null,
        ]);

        // An intended URL must not skip mandatory enrollment.
        $this->withSession(['url.intended' => route('admin.products.index')])
            ->post(route('admin.login.submit'), [
                'email' => $admin->email,
                'password' => 'password',
            ])->assertRedirect(route('admin.mfa.enroll'));

        $this->assertFalse((bool) session(AdminAccess::SESSION_KEY));
    }

    // ------------------------------------------------------------------
    // S2 — passkey session rotation
    // ------------------------------------------------------------------

    public function test_passkey_login_regenerates_the_session_id_and_still_grants_access(): void
    {
        $admin = $this->mfaAdmin($secret);
        $request = $this->passkeyRequest();
        $before = $request->session()->getId();

        $response = app(AdminAuthFlow::class)->completeAdminLogin($request, $admin, 'passkey');

        $after = $request->session()->getId();

        $this->assertNotSame($before, $after, 'Passkey login must rotate the session id.');
        $this->assertTrue(AdminAccess::verified($request));
        $this->assertSame(route('admin.dashboard'), $response->getTargetUrl());
        $this->assertNull($request->session()->get(AdminMfa::SESSION_PENDING));
    }

    public function test_passkey_session_rotation_preserves_validated_intended_destination(): void
    {
        $admin = $this->mfaAdmin($secret);
        $request = $this->passkeyRequest(['url.intended' => route('admin.products.index')]);
        $before = $request->session()->getId();

        $response = app(AdminAuthFlow::class)->completeAdminLogin($request, $admin, 'passkey');

        $this->assertNotSame($before, $request->session()->getId());
        $this->assertSame(route('admin.products.index'), $response->getTargetUrl());
        $this->assertTrue(AdminAccess::verified($request));
    }

    public function test_passkey_rotation_destroys_the_previous_session_record(): void
    {
        $admin = $this->mfaAdmin($secret);
        $request = $this->passkeyRequest(['probe' => 'pre-login']);
        $session = $request->session();
        $before = $session->getId();

        // Persist the pre-login session so its destruction is observable.
        $session->save();
        $this->assertNotSame('', (string) $session->getHandler()->read($before));

        app(AdminAuthFlow::class)->completeAdminLogin($request, $admin, 'passkey');

        $this->assertNotSame($before, $session->getId());
        $this->assertSame(
            '',
            (string) $session->getHandler()->read($before),
            'The pre-login session record must be destroyed, not left resumable.'
        );
    }

    public function test_passkey_login_for_non_admin_does_not_grant_access(): void
    {
        $customer = User::factory()->create(['password' => 'password']);
        $request = $this->passkeyRequest();

        $response = app(AdminAuthFlow::class)->completeAdminLogin($request, $customer, 'passkey');

        $this->assertSame(route('admin.login'), $response->getTargetUrl());
        $this->assertFalse(AdminAccess::verified($request));
    }

    // ------------------------------------------------------------------
    // S3 — MFA disable requires password + live TOTP
    // ------------------------------------------------------------------

    public function test_disable_requires_a_totp_code(): void
    {
        $admin = $this->mfaAdmin($secret);

        $this->actingAsAdmin($admin)
            ->from(route('admin.mfa.manage'))
            ->post(route('admin.mfa.disable'), [
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('code');

        $this->assertMfaStillEnabled($admin);
    }

    public function test_disable_rejects_an_invalid_totp_code(): void
    {
        $admin = $this->mfaAdmin($secret);

        $this->actingAsAdmin($admin)
            ->from(route('admin.mfa.manage'))
            ->post(route('admin.mfa.disable'), [
                'current_password' => 'password',
                'code' => '000000',
            ])
            ->assertSessionHasErrors('code');

        $this->assertMfaStillEnabled($admin);
    }

    public function test_disable_rejects_a_replayed_totp_code(): void
    {
        $admin = $this->mfaAdmin($secret);
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);

        // The same OTP window was already consumed earlier in this session.
        $this->actingAsAdmin($admin)
            ->withSession([AdminMfa::SESSION_LAST_TOTP => $google2fa->getTimestamp()])
            ->from(route('admin.mfa.manage'))
            ->post(route('admin.mfa.disable'), [
                'current_password' => 'password',
                'code' => $code,
            ])
            ->assertSessionHasErrors('code');

        $this->assertMfaStillEnabled($admin);
    }

    public function test_disable_rejects_an_incorrect_password(): void
    {
        $admin = $this->mfaAdmin($secret);

        $this->actingAsAdmin($admin)
            ->from(route('admin.mfa.manage'))
            ->post(route('admin.mfa.disable'), [
                'current_password' => 'wrong-password',
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertMfaStillEnabled($admin);
    }

    public function test_disable_does_not_accept_a_recovery_code(): void
    {
        $admin = $this->mfaAdmin($secret);

        $this->actingAsAdmin($admin)
            ->from(route('admin.mfa.manage'))
            ->post(route('admin.mfa.disable'), [
                'current_password' => 'password',
                'code' => 'AAAA-BBBB',
            ])
            ->assertSessionHasErrors('code');

        $this->assertMfaStillEnabled($admin);
    }

    public function test_disable_succeeds_with_password_and_live_totp(): void
    {
        $admin = $this->mfaAdmin($secret);

        $this->actingAsAdmin($admin)
            ->post(route('admin.mfa.disable'), [
                'current_password' => 'password',
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertRedirect(route('admin.mfa.enroll'))
            ->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);
        $this->assertNull($admin->two_factor_recovery_codes);
        $this->assertFalse(app(AdminMfa::class)->hasMfaEnabled($admin));
    }

    public function test_disable_keeps_the_owner_signed_in_and_able_to_reenroll(): void
    {
        $admin = $this->mfaAdmin($secret);

        $this->actingAsAdmin($admin)
            ->post(route('admin.mfa.disable'), [
                'current_password' => 'password',
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])->assertRedirect(route('admin.mfa.enroll'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.mfa.enroll'))->assertOk();
    }

    public function test_disable_validation_errors_do_not_leak_the_totp_secret(): void
    {
        $admin = $this->mfaAdmin($secret);

        $response = $this->actingAsAdmin($admin)
            ->from(route('admin.mfa.manage'))
            ->post(route('admin.mfa.disable'), [
                'current_password' => 'password',
                'code' => '000000',
            ]);

        $errors = session('errors')->getBag('default')->all();

        foreach ($errors as $message) {
            $this->assertStringNotContainsString($secret, $message);
        }

        $response->assertRedirect(route('admin.mfa.manage'));
        $this->get(route('admin.mfa.manage'))->assertOk()->assertDontSee($secret);
    }

    public function test_mfa_manage_form_collects_the_authenticator_code(): void
    {
        $admin = $this->mfaAdmin($secret);

        $this->actingAsAdmin($admin)
            ->get(route('admin.mfa.manage'))
            ->assertOk()
            ->assertSee('name="code"', false)
            ->assertSee('Current authenticator code');
    }

    // ------------------------------------------------------------------
    // Login throttling and logout teardown
    // ------------------------------------------------------------------

    public function test_admin_login_page_remains_accessible(): void
    {
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_failed_admin_logins_are_throttled_after_the_configured_attempts(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);

        // throttle:auth allows 5 attempts per minute per email address.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('admin.login.submit'), [
                'email' => $admin->email,
                'password' => 'wrong-password',
            ])->assertStatus(302);
        }

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    public function test_login_get_stays_available_while_post_is_throttled(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('admin.login.submit'), [
                'email' => $admin->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->get(route('admin.login'))->assertOk();
    }

    public function test_throttling_is_scoped_per_email_address(): void
    {
        $first = User::factory()->admin()->create(['password' => 'password']);
        $second = User::factory()->admin()->create(['password' => 'password']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('admin.login.submit'), [
                'email' => $first->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('admin.login.submit'), [
            'email' => $first->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        // A different admin must not be locked out by someone else's failures.
        $this->post(route('admin.login.submit'), [
            'email' => $second->email,
            'password' => 'wrong-password',
        ])->assertStatus(302);
    }

    public function test_logout_invalidates_session_and_clears_admin_and_mfa_flags(): void
    {
        $admin = $this->mfaAdmin($secret);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.mfa.challenge'));

        $this->post(route('admin.mfa.challenge.submit'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk();

        $tokenBeforeLogout = session()->token();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertGuest();
        $this->assertNotSame($tokenBeforeLogout, session()->token(), 'Logout must regenerate the CSRF token.');
        $this->assertFalse((bool) session(AdminAccess::SESSION_KEY));
        $this->assertNull(session(AdminMfa::SESSION_PENDING));
        $this->assertNull(session(AdminMfa::SESSION_SETUP_SECRET));
        $this->assertNull(session(AdminMfa::SESSION_LAST_TOTP));

        $this->get(route('admin.dashboard'))->assertRedirect();
    }

    private function passkeyRequest(array $session = []): Request
    {
        $request = Request::create('/admin/passkeys/login', 'POST');
        $store = $this->app['session.store'];
        $store->start();
        $request->setLaravelSession($store);

        foreach ($session as $key => $value) {
            $store->put($key, $value);
        }

        return $request;
    }

    private function assertMfaStillEnabled(User $admin): void
    {
        $admin->refresh();

        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertTrue(app(AdminMfa::class)->hasMfaEnabled($admin));
    }
}
