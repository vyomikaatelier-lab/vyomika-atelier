<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappOtpVerification;
use App\Services\TurnstileService;
use App\Services\WhatsappOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class ForgotPasswordFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_page_shows_inline_otp_and_password_reset_steps(): void
    {
        $otp = Mockery::mock(WhatsappOtpService::class);
        $otp->shouldReceive('providerConfigured')->andReturn(true);
        $this->app->instance(WhatsappOtpService::class, $otp);

        $response = $this->get(route('account.forgot'));

        $response->assertOk()
            ->assertSee('Reset password')
            ->assertSee('Send OTP')
            ->assertSee('OTP')
            ->assertDontSee('Update password');
    }

    public function test_verified_forgot_otp_shows_new_password_form(): void
    {
        $record = WhatsappOtpVerification::create([
            'mobile_e164' => '919818891878',
            'purpose' => 'forgot',
            'otp_hash' => Hash::make('123456'),
            'payload' => null,
            'attempts' => 0,
            'send_count' => 1,
            'ip_address' => '127.0.0.1',
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $otp = Mockery::mock(WhatsappOtpService::class);
        $otp->shouldReceive('providerConfigured')->andReturn(true);
        $this->app->instance(WhatsappOtpService::class, $otp);

        $response = $this->withSession([
            'account_pending_verification_id' => $record->id,
            'account_pending_mobile_display' => '+91 98188 91878',
        ])->get(route('account.forgot'));

        $response->assertOk()
            ->assertSee('New password')
            ->assertSee('Update password')
            ->assertDontSee('Verify OTP');
    }

    public function test_reset_password_after_forgot_otp_logs_user_in(): void
    {
        $user = User::factory()->create([
            'mobile' => '9818891878',
            'mobile_country_code' => '+91',
            'password' => Hash::make('old-password'),
        ]);

        $record = WhatsappOtpVerification::create([
            'mobile_e164' => '919818891878',
            'purpose' => 'forgot',
            'otp_hash' => Hash::make('123456'),
            'payload' => null,
            'attempts' => 0,
            'send_count' => 1,
            'ip_address' => '127.0.0.1',
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $otp = Mockery::mock(WhatsappOtpService::class);
        $otp->shouldReceive('providerConfigured')->andReturn(true);
        $this->app->instance(WhatsappOtpService::class, $otp);

        $turnstile = app(TurnstileService::class);

        $response = $this->withSession([
            'account_pending_verification_id' => $record->id,
            'account_pending_mobile_display' => '+91 98188 91878',
        ])->post(route('account.forgot.reset'), [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'form_loaded_at' => Crypt::encryptString(json_encode([
                'form' => 'account_forgot_otp',
                'loaded_at' => now()->subSeconds(10)->timestamp,
            ])),
            'turnstile_fallback_token' => $turnstile->fallbackToken('account_forgot_otp'),
            'turnstile_unavailable' => '0',
            'cf-turnstile-response' => 'test-turnstile-pass',
            'human_confirmation' => '1',
        ]);

        $response->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }
}
