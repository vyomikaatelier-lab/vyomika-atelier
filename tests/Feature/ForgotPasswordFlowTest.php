<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountAuthController;
use App\Models\User;
use App\Services\WhatsappOtpService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class ForgotPasswordFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_page_uses_email_reset_without_otp(): void
    {
        $this->get(route('account.forgot'))
            ->assertOk()
            ->assertSee('Email')
            ->assertSee('Send reset link')
            ->assertDontSee('Send OTP');
    }

    public function test_existing_customer_can_set_password_through_email_reset(): void
    {
        Notification::fake();
        $otp = Mockery::mock(WhatsappOtpService::class);
        $otp->shouldReceive('send')->never();
        $this->app->instance(WhatsappOtpService::class, $otp);

        $user = User::factory()->unverified()->create([
            'email' => 'legacy-otp@example.com',
            'password' => Hash::make('unusable-otp-era-secret'),
        ]);

        $this->post(route('account.forgot.send'), [
            'email' => $user->email,
        ])->assertSessionHas('status', AccountAuthController::RESET_LINK_STATUS);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post(route('account.forgot.reset'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'fresh-password',
            'password_confirmation' => 'fresh-password',
        ])->assertRedirect(route('account'));

        $this->assertTrue(Hash::check('fresh-password', $user->fresh()->password));
        $this->assertDatabaseCount('whatsapp_otp_verifications', 0);
    }
}
