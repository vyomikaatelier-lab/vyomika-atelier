<?php

namespace Tests\Feature;

use App\Models\WhatsappOtpVerification;
use App\Services\TurnstileService;
use App\Services\WhatsappOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class RegisterOtpFormFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_register_otp_stays_on_create_account_form(): void
    {
        $otp = Mockery::mock(WhatsappOtpService::class);
        $otp->shouldReceive('providerConfigured')->andReturn(true);
        $otp->shouldReceive('send')->once()->andReturnUsing(function () {
            return WhatsappOtpVerification::create([
                'mobile_e164' => '919818891878',
                'purpose' => 'register',
                'otp_hash' => Hash::make('123456'),
                'payload' => [
                    'name' => 'Hitesh',
                    'email' => 'hitesh@example.com',
                    'country_code' => '+91',
                    'mobile' => '9818891878',
                    'whatsapp' => '9818891878',
                    'city' => 'Delhi',
                    'account_type' => 'customer',
                ],
                'attempts' => 0,
                'send_count' => 1,
                'ip_address' => '127.0.0.1',
                'expires_at' => now()->addMinutes(5),
            ]);
        });
        $otp->shouldReceive('canResend')->andReturn(false);
        $otp->shouldReceive('secondsUntilResend')->andReturn(30);
        $this->app->instance(WhatsappOtpService::class, $otp);

        $turnstile = app(TurnstileService::class);

        $response = $this->post(route('account.register.send'), [
            'name' => 'Hitesh',
            'email' => 'hitesh@example.com',
            'country_code' => '+91',
            'mobile' => '9818891878',
            'city' => 'Delhi',
            'account_type' => 'customer',
            'form_loaded_at' => Crypt::encryptString(json_encode([
                'form' => 'account_register',
                'loaded_at' => now()->subSeconds(10)->timestamp,
            ])),
            'turnstile_fallback_token' => $turnstile->fallbackToken('account_register'),
            'turnstile_unavailable' => '0',
            'cf-turnstile-response' => 'test-turnstile-pass',
            'human_confirmation' => '1',
        ]);

        $response->assertRedirect(route('account.register'));

        $page = $this->followRedirects($response);

        $page->assertOk()
            ->assertSeeInOrder([
                'Full name',
                'Email',
                'City',
                'Account type',
                'WhatsApp number',
                'Verification code',
                'Verify OTP',
            ])
            ->assertDontSee('Send verification code')
            ->assertDontSee('id="register-password"', false);
    }

    public function test_verified_otp_shows_password_and_create_account(): void
    {
        $record = WhatsappOtpVerification::create([
            'mobile_e164' => '919818891878',
            'purpose' => 'register',
            'otp_hash' => Hash::make('123456'),
            'payload' => [
                'name' => 'Hitesh',
                'email' => 'hitesh@example.com',
                'country_code' => '+91',
                'mobile' => '9818891878',
                'whatsapp' => '9818891878',
                'city' => 'Delhi',
                'account_type' => 'customer',
            ],
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
        ])->get(route('account.register'));

        $response->assertOk()
            ->assertSee('Password')
            ->assertSee('Create account')
            ->assertDontSee('Verify OTP');
    }
}
