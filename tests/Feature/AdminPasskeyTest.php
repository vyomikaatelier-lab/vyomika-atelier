<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminAuthFlow;
use App\Support\AdminMfa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkeys;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class AdminPasskeyTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'passkeys.relying_party_id' => 'vyomikaatelier.com',
            'passkeys.relying_party_name' => 'Vyomika Atelier',
            'passkeys.allowed_origins' => [
                'https://vyomikaatelier.com',
                'https://www.vyomikaatelier.com',
            ],
        ]);
    }

    public function test_passkey_config_uses_vyomika_rp_id_and_origins(): void
    {
        $this->assertSame('vyomikaatelier.com', Passkeys::relyingPartyId());
        $this->assertSame([
            'https://vyomikaatelier.com',
            'https://www.vyomikaatelier.com',
        ], Passkeys::allowedOrigins());
    }

    public function test_login_options_returns_discoverable_credential_challenge(): void
    {
        $response = $this->getJson(route('admin.passkeys.login.options'));

        $response->assertOk()
            ->assertJsonStructure(['options' => ['challenge', 'rpId', 'timeout']]);

        $this->assertSame('vyomikaatelier.com', $response->json('options.rpId'));
        $this->assertSame([], $response->json('options.allowCredentials'));
    }

    public function test_login_without_stored_challenge_is_rejected(): void
    {
        $this->postJson(route('admin.passkeys.login'), [
            'credential' => [
                'id' => 'test',
                'rawId' => 'test',
                'type' => 'public-key',
                'response' => [],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('credential.response');
    }

    public function test_expired_login_challenge_cannot_be_replayed(): void
    {
        $this->getJson(route('admin.passkeys.login.options'))->assertOk();

        $this->postJson(route('admin.passkeys.login'), [
            'credential' => [
                'id' => 'dGVzdA',
                'rawId' => 'dGVzdA',
                'type' => 'public-key',
                'response' => [
                    'authenticatorData' => 'dGVzdA',
                    'clientDataJSON' => 'dGVzdA',
                    'signature' => 'dGVzdA',
                ],
            ],
        ])->assertStatus(422);

        $this->postJson(route('admin.passkeys.login'), [
            'credential' => [
                'id' => 'dGVzdA',
                'rawId' => 'dGVzdA',
                'type' => 'public-key',
                'response' => [
                    'authenticatorData' => 'dGVzdA',
                    'clientDataJSON' => 'dGVzdA',
                    'signature' => 'dGVzdA',
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('credential');
    }

    public function test_non_admin_passkey_login_is_rejected_without_account_enumeration(): void
    {
        $customer = User::factory()->create();
        $passkey = $customer->passkeys()->create([
            'name' => 'Phone',
            'credential_id' => 'customer-credential',
            'credential' => ['type' => 'public-key'],
        ]);

        $this->expectException(ValidationException::class);

        Passkeys::allowsLogin(request(), $passkey);
    }

    public function test_disabled_admin_passkey_login_is_rejected(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => false]);
        $passkey = $admin->passkeys()->create([
            'name' => 'Laptop',
            'credential_id' => 'disabled-admin',
            'credential' => ['type' => 'public-key'],
        ]);

        try {
            Passkeys::allowsLogin(request(), $passkey);
            $this->fail('Expected passkey authorization to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Unable to sign in with this passkey.',
                $exception->errors()['credential'][0]
            );
        }
    }

    public function test_passkey_manage_page_requires_verified_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.passkeys.manage'))
            ->assertRedirect();

        $this->actingAsAdmin($admin)
            ->get(route('admin.passkeys.manage'))
            ->assertOk()
            ->assertSee('Passkeys');
    }

    public function test_registration_requires_password_and_totp(): void
    {
        $admin = $this->adminWithMfa();

        $this->actingAsAdmin($admin)
            ->postJson(route('admin.passkeys.register.options'), [
                'name' => 'MacBook',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['current_password', 'totp_code']);
    }

    public function test_registration_rejects_invalid_totp(): void
    {
        $admin = $this->adminWithMfa();

        $this->actingAsAdmin($admin)
            ->postJson(route('admin.passkeys.register.options'), [
                'name' => 'MacBook',
                'current_password' => 'password',
                'totp_code' => '000000',
            ])->assertStatus(422)
            ->assertJsonValidationErrors('totp_code');
    }

    public function test_registration_requires_mfa_before_enrollment(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);

        $this->actingAsAdmin($admin)
            ->postJson(route('admin.passkeys.register.options'), [
                'name' => 'MacBook',
                'current_password' => 'password',
                'totp_code' => '123456',
            ])->assertStatus(422)
            ->assertJsonValidationErrors('totp_code');
    }

    public function test_cannot_remove_last_passkey_without_mfa_fallback(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);
        $passkey = $admin->passkeys()->create([
            'name' => 'Only key',
            'credential_id' => 'only-key',
            'credential' => ['type' => 'public-key'],
        ]);

        $this->actingAsAdmin($admin)
            ->deleteJson(route('admin.passkeys.destroy', $passkey), [
                'current_password' => 'password',
                'totp_code' => '123456',
            ])->assertStatus(422);
    }

    public function test_can_remove_last_passkey_when_mfa_is_enabled(): void
    {
        $admin = $this->adminWithMfa();
        $passkey = $admin->passkeys()->create([
            'name' => 'Only key',
            'credential_id' => 'only-key-mfa',
            'credential' => ['type' => 'public-key'],
        ]);

        $code = (new Google2FA)->getCurrentOtp($this->plainSecret($admin));

        $this->actingAsAdmin($admin)
            ->deleteJson(route('admin.passkeys.destroy', $passkey), [
                'current_password' => 'password',
                'totp_code' => $code,
            ])->assertOk();

        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    public function test_rename_updates_passkey_name(): void
    {
        $admin = $this->adminWithMfa();
        $passkey = $admin->passkeys()->create([
            'name' => 'Phone',
            'credential_id' => 'rename-key',
            'credential' => ['type' => 'public-key'],
        ]);

        $code = (new Google2FA)->getCurrentOtp($this->plainSecret($admin));

        $this->actingAsAdmin($admin)
            ->patchJson(route('admin.passkeys.update', $passkey), [
                'name' => 'Work iPhone',
                'current_password' => 'password',
                'totp_code' => $code,
            ])->assertOk();

        $this->assertDatabaseHas('passkeys', [
            'id' => $passkey->id,
            'name' => 'Work iPhone',
        ]);
    }

    public function test_login_options_are_rate_limited(): void
    {
        RateLimiter::clear('admin-passkey');

        for ($i = 0; $i < 10; $i++) {
            $this->getJson(route('admin.passkeys.login.options'))->assertOk();
        }

        $this->getJson(route('admin.passkeys.login.options'))->assertStatus(429);
    }

    public function test_admin_login_page_shows_passkey_button(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Sign in with a passkey')
            ->assertSee('admin-passkeys.js');
    }

    public function test_package_discover_works_without_database(): void
    {
        $this->artisan('package:discover', ['--ansi' => true])->assertSuccessful();
    }

    public function test_wrong_rp_id_in_options_is_not_used_when_configured(): void
    {
        config(['passkeys.relying_party_id' => 'wrong.example']);

        $response = $this->getJson(route('admin.passkeys.login.options'));

        $response->assertOk();
        $this->assertSame('wrong.example', $response->json('options.rpId'));
    }

    public function test_passkey_login_grants_admin_access_without_totp_challenge_when_mfa_enabled(): void
    {
        $admin = $this->adminWithMfa();
        $request = Request::create('/admin/passkeys/login', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $response = app(AdminAuthFlow::class)->completeAdminLogin($request, $admin, 'passkey');

        $this->assertSame(route('admin.dashboard'), $response->getTargetUrl());
        $this->assertTrue(AdminAccess::verified($request));
        $this->assertNull($request->session()->get(AdminMfa::SESSION_PENDING));
    }

    public function test_passkey_login_still_requires_mfa_enrollment_when_grace_expired(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => 'password',
            'two_factor_grace_ends_at' => now()->subDay(),
        ]);
        $request = Request::create('/admin/passkeys/login', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $response = app(AdminAuthFlow::class)->completeAdminLogin($request, $admin, 'passkey');

        $this->assertSame(route('admin.mfa.enroll'), $response->getTargetUrl());
        $this->assertFalse(AdminAccess::verified($request));
        $this->assertSame($admin->id, $request->session()->get(AdminMfa::SESSION_PENDING));
    }

    private function adminWithMfa(): User
    {
        $secret = (new Google2FA)->generateSecretKey();
        $mfa = app(AdminMfa::class);

        return User::factory()->admin()->create([
            'password' => 'password',
            'two_factor_secret' => $mfa->encryptSecret($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $mfa->hashRecoveryCodes(['ABCD-EFGH']),
        ]);
    }

    private function plainSecret(User $admin): string
    {
        $secret = app(AdminMfa::class)->decryptSecret($admin->two_factor_secret);
        $this->assertNotNull($secret);

        return $secret;
    }
}
