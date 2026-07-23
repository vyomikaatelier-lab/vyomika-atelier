<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

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

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_active_verified_customer_can_login_with_email_or_mobile(): void
    {
        $user = User::factory()->create([
            'email' => 'active@example.com',
            'mobile' => '9818891878',
            'mobile_country_code' => '+91',
            'password' => Hash::make('correct-password'),
        ]);

        $this->post(route('account.login.email'), [
            'login' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('account'));

        Auth::logout();

        $this->post(route('account.login.email'), [
            'login' => '9818891878',
            'password' => 'correct-password',
        ])->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_page_shows_unified_sign_in_options(): void
    {
        $response = $this->get(route('account.login'));

        $response->assertOk()
            ->assertSee('Email address or mobile number')
            ->assertSee('Sign in with OTP')
            ->assertSee('Sign in with Apple')
            ->assertSee('Sign in with Google')
            ->assertSee('Create an account')
            ->assertDontSee('Sign in with mobile &amp; password', false);
    }
}
