<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'apple'];

    public function redirect(string $provider)
    {
        if (! $this->providerConfigured($provider)) {
            return redirect()->route('account.register')
                ->with('info', $this->providerLabel($provider) . ' sign-up is not configured yet. Use email registration or contact the studio.');
        }

        return Socialite::driver($provider)
            ->scopes($provider === 'google' ? ['openid', 'profile', 'email'] : [])
            ->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        if (! $this->providerConfigured($provider)) {
            return redirect()->route('account.register')
                ->with('info', $this->providerLabel($provider) . ' sign-up is not configured yet.');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            return redirect()->route('account.register')
                ->withErrors(['form' => 'Unable to sign in with ' . $this->providerLabel($provider) . '. Please try again or use email registration.']);
        }

        $providerIdColumn = $provider . '_id';
        $email = $socialUser->getEmail();

        if (! $email) {
            return redirect()->route('account.register')
                ->withErrors(['form' => 'We could not read an email address from your ' . $this->providerLabel($provider) . ' account.']);
        }

        $user = User::query()
            ->where($providerIdColumn, $socialUser->getId())
            ->where('is_admin', false)
            ->first();

        if (! $user) {
            $user = User::query()
                ->where('email', $email)
                ->where('is_admin', false)
                ->first();
        }

        if ($user) {
            if (! $user->is_active) {
                return redirect()->route('account.login')
                    ->withErrors(['email' => 'This account has been disabled. Contact the studio for assistance.']);
            }

            $user->update([
                $providerIdColumn => $socialUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            if (User::where('email', $email)->exists()) {
                return redirect()->route('account.login')
                    ->with('info', 'An account with this email already exists. Sign in with your password or link ' . $this->providerLabel($provider) . ' from your profile.');
            }

            $user = User::create([
                'name' => $socialUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                $providerIdColumn => $socialUser->getId(),
                'password' => Str::password(32),
                'is_admin' => false,
                'is_active' => true,
                'account_type' => 'customer',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account'))
            ->with('success', 'Welcome to Vyomika Atelier.');
    }

    private function providerConfigured(string $provider): bool
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return false;
        }

        return match ($provider) {
            'google' => filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
            'apple' => filled(config('services.apple.client_id'))
                && filled(config('services.apple.client_secret'))
                && filled(config('services.apple.key_id'))
                && filled(config('services.apple.team_id')),
            default => false,
        };
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'google' => 'Google',
            'apple' => 'Apple',
            default => ucfirst($provider),
        };
    }
}
