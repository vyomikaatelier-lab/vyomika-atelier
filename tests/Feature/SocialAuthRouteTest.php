<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAuthRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_shows_social_signup_buttons(): void
    {
        $response = $this->get(route('account.register'));

        $response->assertOk()
            ->assertSee('Sign up with Apple')
            ->assertSee('Sign up with Google')
            ->assertSee('or continue with email')
            ->assertSee('Confirm password')
            ->assertSee('Mobile verification');
    }

    public function test_google_redirect_without_credentials_returns_info_message(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $response = $this->get(route('account.social.redirect', 'google'));

        $response->assertRedirect(route('account.register'))
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

        $response->assertRedirect(route('account.register'))
            ->assertSessionHas('info');
    }

    public function test_unknown_social_provider_redirects_with_info(): void
    {
        $response = $this->get(route('account.social.redirect', 'facebook'));

        $response->assertRedirect(route('account.register'))
            ->assertSessionHas('info');
    }
}
