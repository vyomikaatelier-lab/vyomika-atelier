<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WhatsappOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RegisterOtpFormFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_shows_password_fields_without_otp(): void
    {
        $response = $this->get(route('account.register'));

        $response->assertOk()
            ->assertSeeInOrder([
                'Full name',
                'Email',
                'Password',
                'Confirm password',
                'Create Account',
            ])
            ->assertSee('Sign in')
            ->assertDontSee('Send OTP')
            ->assertDontSee('OTP')
            ->assertDontSee('Sign up with Apple')
            ->assertDontSee('Sign up with Google');
    }

    public function test_successful_registration_creates_customer_without_otp(): void
    {
        $otp = Mockery::mock(WhatsappOtpService::class);
        $otp->shouldReceive('send')->never();
        $this->app->instance(WhatsappOtpService::class, $otp);

        $this->post(route('account.register.send'), [
            'name' => 'Hitesh',
            'email' => 'hitesh@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('account'));

        $user = User::query()->where('email', 'hitesh@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('customer', $user->account_type);
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->phone_verified_at);
        $this->assertDatabaseCount('whatsapp_otp_verifications', 0);
    }
}
