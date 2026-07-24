<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappOtpVerification;
use App\Services\WhatsappOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class LoginOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_otp_stays_on_sign_in_page(): void
    {
        $record = WhatsappOtpVerification::create([
            'mobile_e164' => '919818891878',
            'purpose' => 'login',
            'otp_hash' => Hash::make('123456'),
            'payload' => null,
            'attempts' => 0,
            'send_count' => 1,
            'ip_address' => '127.0.0.1',
            'expires_at' => now()->addMinutes(5),
        ]);

        $otp = Mockery::mock(WhatsappOtpService::class);
        $otp->shouldReceive('providerConfigured')->andReturn(true);
        $otp->shouldReceive('canResend')->andReturn(false);
        $otp->shouldReceive('secondsUntilResend')->andReturn(30);
        $this->app->instance(WhatsappOtpService::class, $otp);

        $response = $this->withSession([
            'account_pending_verification_id' => $record->id,
            'account_pending_mobile_display' => '+91 98188 91878',
        ])->get(route('account.login', ['otp' => 1]));

        $response->assertOk()
            ->assertSee('Verify & sign in')
            ->assertSee('OTP')
            ->assertDontSee('Send OTP');
    }

    public function test_verify_otp_route_redirects_login_flow_back_to_sign_in_page(): void
    {
        $record = WhatsappOtpVerification::create([
            'mobile_e164' => '919818891878',
            'purpose' => 'login',
            'otp_hash' => Hash::make('123456'),
            'payload' => null,
            'attempts' => 0,
            'send_count' => 1,
            'ip_address' => '127.0.0.1',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->withSession([
            'account_pending_verification_id' => $record->id,
        ])->get(route('account.verify'));

        $response->assertRedirect(route('account.login', ['otp' => 1]));
    }

    public function test_successful_login_otp_signs_user_in(): void
    {
        $user = User::factory()->create([
            'mobile' => '9818891878',
            'mobile_country_code' => '+91',
        ]);

        $record = WhatsappOtpVerification::create([
            'mobile_e164' => '919818891878',
            'purpose' => 'login',
            'otp_hash' => Hash::make('123456'),
            'payload' => null,
            'attempts' => 0,
            'send_count' => 1,
            'ip_address' => '127.0.0.1',
            'expires_at' => now()->addMinutes(5),
        ]);

        $otp = Mockery::mock(WhatsappOtpService::class);
        $otp->shouldReceive('providerConfigured')->andReturn(true);
        $otp->shouldReceive('verify')->once()->andReturnUsing(function (WhatsappOtpVerification $verification) {
            $verification->update(['verified_at' => now()]);

            return true;
        });
        $this->app->instance(WhatsappOtpService::class, $otp);

        $response = $this->withSession([
            'account_pending_verification_id' => $record->id,
        ])->post(route('account.verify.submit'), [
            'otp' => '123456',
            'form_loaded_at' => Crypt::encryptString(json_encode([
                'form' => 'account_verify_otp',
                'loaded_at' => now()->subSeconds(10)->timestamp,
            ])),
            'turnstile_fallback_token' => app(\App\Services\TurnstileService::class)->fallbackToken('account_verify_otp'),
            'turnstile_unavailable' => '0',
            'cf-turnstile-response' => 'test-turnstile-pass',
            'human_confirmation' => '1',
        ]);

        $response->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user);
    }
}
