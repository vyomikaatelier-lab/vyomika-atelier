<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_does_not_show_social_signup_buttons(): void
    {
        $response = $this->get(route('account.register'));

        $response->assertOk()
            ->assertDontSee('Sign up with Apple')
            ->assertDontSee('Sign up with Google')
            ->assertDontSee('or continue with email')
            ->assertSee('Confirm password')
            ->assertDontSee('OTP');
    }

    public function test_login_page_hides_social_buttons_when_not_configured(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.apple.client_id' => null,
            'services.apple.client_secret' => null,
            'services.apple.key_id' => null,
            'services.apple.team_id' => null,
        ]);

        $response = $this->get(route('account.login'));

        $response->assertOk()
            ->assertDontSee('Sign in with Apple')
            ->assertDontSee('Sign in with Google');
    }

    public function test_login_page_shows_social_signin_buttons_when_configured(): void
    {
        config([
            'services.google.client_id' => 'test-google-id',
            'services.google.client_secret' => 'test-google-secret',
        ]);

        $response = $this->get(route('account.login'));

        $response->assertOk()
            ->assertSee('Sign in with Google')
            ->assertDontSee('Sign in with Apple');
    }

    public function test_google_redirect_without_credentials_returns_info_message(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $response = $this->get(route('account.social.redirect', 'google'));

        $response->assertRedirect(route('account.login'))
            ->assertSessionHas('info');
    }

    public function test_apple_redirect_without_credentials_returns_info_message(): void
    {
        config([
            'services.apple.client_id' => null,
            'services.apple.client_secret' => null,
            'services.apple.key_id' => null,
            'services.apple.team_id' => null,
        ]);

        $response = $this->get(route('account.social.redirect', 'apple'));

        $response->assertRedirect(route('account.login'))
            ->assertSessionHas('info');
    }

    public function test_unknown_social_provider_redirects_with_info(): void
    {
        $response = $this->get(route('account.social.redirect', 'facebook'));

        $response->assertRedirect(route('account.login'))
            ->assertSessionHas('info');
    }

    public function test_social_login_does_not_mark_phone_verified(): void
    {
        config([
            'services.google.client_id' => 'test-google-id',
            'services.google.client_secret' => 'test-google-secret',
        ]);

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google-user-1');
        $socialUser->shouldReceive('getEmail')->andReturn('social@example.com');
        $socialUser->shouldReceive('getName')->andReturn('Social User');

        $provider = Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('account.social.callback', 'google'))
            ->assertRedirect(route('account'));

        $user = User::query()->where('email', 'social@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->phone_verified_at);
        $this->assertFalse($user->is_admin);
        $this->assertTrue($user->is_active);
    }
}
