<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountAuthController;
use App\Models\User;
use App\Services\WhatsappOtpService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_with_email_and_password(): void
    {
        $otp = $this->refuseOtpSends();

        $response = $this->post(route('account.register.send'), [
            'name' => 'Hitesh Kumar',
            'email' => 'hitesh@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertRedirect(route('account'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'hitesh@example.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->phone_verified_at);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
    }

    public function test_registration_does_not_send_otp(): void
    {
        $this->refuseOtpSends();

        $this->post(route('account.register.send'), [
            'name' => 'Hitesh Kumar',
            'email' => 'hitesh@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'country_code' => '+91',
            'mobile' => '9818891878',
        ])->assertRedirect(route('account'));

        $this->assertDatabaseCount('whatsapp_otp_verifications', 0);
        $this->assertNull(User::query()->where('email', 'hitesh@example.com')->value('phone_verified_at'));
    }

    public function test_active_customer_can_login_with_email_and_password(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'active@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->post(route('account.login.email'), [
            'email' => 'active@example.com',
            'password' => 'correct-password',
        ])->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_does_not_require_phone_verified_at(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->post(route('account.login.email'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('account'));

        $this->get(route('account'))->assertOk();
    }

    public function test_disabled_customer_cannot_login_with_correct_credentials(): void
    {
        $user = User::factory()->disabled()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post(route('account.login.email'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unknown_email_returns_generic_invalid_credentials(): void
    {
        $this->post(route('account.login.email'), [
            'email' => 'missing@example.com',
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_page_shows_email_password_and_create_account(): void
    {
        $response = $this->get(route('account.login'));

        $response->assertOk()
            ->assertSee('Email')
            ->assertSee('Password')
            ->assertSee('Remember me')
            ->assertSee('Create an account')
            ->assertDontSee('Sign in with OTP')
            ->assertDontSee('Send OTP');
    }

    public function test_password_reset_uses_email_not_whatsapp_otp(): void
    {
        Notification::fake();
        $this->refuseOtpSends();

        $user = User::factory()->unverified()->create([
            'email' => 'reset@example.com',
            'password' => Hash::make('old-unknown-password'),
        ]);

        $this->post(route('account.forgot.send'), [
            'email' => 'reset@example.com',
        ])->assertRedirect()
            ->assertSessionHas('status', AccountAuthController::RESET_LINK_STATUS);

        $this->assertDatabaseCount('whatsapp_otp_verifications', 0);
        Notification::assertSentTo($user, ResetPassword::class);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post(route('account.password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('new-secret-pass', $user->fresh()->password));
    }

    public function test_password_reset_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();

        $this->post(route('account.forgot.send'), [
            'email' => 'nobody@example.com',
        ])->assertSessionHas('status', AccountAuthController::RESET_LINK_STATUS);

        Notification::assertNothingSent();
    }

    public function test_external_return_urls_are_rejected_after_login(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->withSession(['url.intended' => 'https://evil.example/phish'])
            ->post(route('account.login.email'), [
                'email' => $user->email,
                'password' => 'secret-password',
            ]);

        $response->assertRedirect(route('account'));
        $this->assertStringNotContainsString('evil.example', $response->headers->get('Location') ?? '');
    }

    public function test_external_return_urls_are_rejected_after_registration(): void
    {
        $response = $this->withSession(['url.intended' => 'https://evil.example/phish'])
            ->post(route('account.register.send'), [
                'name' => 'New Buyer',
                'email' => 'newbuyer@example.com',
                'password' => 'secret-pass',
                'password_confirmation' => 'secret-pass',
            ]);

        $response->assertRedirect(route('account'));
        $this->assertStringNotContainsString('evil.example', $response->headers->get('Location') ?? '');
    }

    private function refuseOtpSends(): WhatsappOtpService
    {
        $otp = Mockery::mock(WhatsappOtpService::class);
        $otp->shouldReceive('send')->never();
        $otp->shouldReceive('resend')->never();
        $otp->shouldReceive('verify')->never();
        $otp->shouldReceive('providerConfigured')->andReturn(true);
        $this->app->instance(WhatsappOtpService::class, $otp);

        return $otp;
    }
}
