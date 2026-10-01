<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountAuthController;
use App\Models\User;
use App\Services\WhatsappOtpService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
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

    public function test_mail_transport_failure_does_not_claim_delivery_or_reveal_the_account(): void
    {
        $logged = null;
        Log::listen(function ($event) use (&$logged): void {
            if ($event->message === 'account.password_reset_mail_failed') {
                $logged = $event;
            }
        });
        $user = User::factory()->create([
            'email' => 'reset-failure@example.com',
            'password' => Hash::make('existing-password'),
        ]);
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP refused the message for reset-failure@example.com'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $this->post(route('account.forgot.send'), [
            'email' => $user->email,
        ])->assertRedirect()
            ->assertSessionHas('status', AccountAuthController::RESET_LINK_STATUS);

        $this->assertStringNotContainsString('we have sent', strtolower(AccountAuthController::RESET_LINK_STATUS));
        $this->assertStringNotContainsString('has been sent', strtolower(AccountAuthController::RESET_LINK_STATUS));

        $this->assertNotNull($logged);
        $encoded = json_encode($logged->context);
        $this->assertSame('warning', $logged->level);
        $this->assertSame($user->getKey(), $logged->context['user_id'] ?? null);
        $this->assertSame(RuntimeException::class, $logged->context['exception'] ?? null);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('reset-failure@example.com', $encoded);
        $this->assertStringNotContainsString('SMTP refused', $encoded);
    }
}
