<?php

namespace Tests\Feature;

use App\Models\WhatsappOtpVerification;
use App\Services\WhatsappOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class LoginOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_otp_get_pages_redirect_to_login(): void
    {
        $this->get(route('account.login', ['otp' => 1]))
            ->assertOk()
            ->assertDontSee('Send OTP')
            ->assertDontSee('Sign in with OTP');

        $this->get(route('account.verify'))
            ->assertRedirect(route('account.login'));
    }

    public function test_otp_send_endpoints_cannot_send_new_otp_messages(): void
    {
        $otp = Mockery::mock(WhatsappOtpService::class);
        $otp->shouldReceive('send')->never();
        $otp->shouldReceive('resend')->never();
        $otp->shouldReceive('verify')->never();
        $this->app->instance(WhatsappOtpService::class, $otp);

        WhatsappOtpVerification::create([
            'mobile_e164' => '919818891878',
            'purpose' => 'login',
            'otp_hash' => Hash::make('123456'),
            'payload' => null,
            'attempts' => 0,
            'send_count' => 1,
            'ip_address' => '127.0.0.1',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withSession(['account_pending_verification_id' => 1])
            ->post(route('account.login.send'), [
                'country_code' => '+91',
                'mobile' => '9818891878',
            ])
            ->assertRedirect(route('account.login'));

        $this->post(route('account.verify.submit'), [
            'otp' => '123456',
        ])->assertRedirect(route('account.login'));

        $this->post(route('account.resend'))
            ->assertRedirect(route('account.login'));

        $this->post(route('account.login.mobile'), [
            'country_code' => '+91',
            'mobile' => '9818891878',
            'password' => 'secret-password',
        ])->assertRedirect(route('account.login'));

        $this->assertGuest();
        $this->assertSame(1, WhatsappOtpVerification::query()->count());
        $this->assertNull(WhatsappOtpVerification::query()->first()->verified_at);
    }
}
