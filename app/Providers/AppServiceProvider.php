<?php

namespace App\Providers;

use App\Contracts\WhatsAppProvider;
use App\Services\WhatsApp\MetaWhatsAppProvider;
use App\Services\WhatsApp\Msg91WhatsAppProvider;
use App\Support\AdminMfa;
use App\Support\CmsSettings;
use App\Support\PackageDiscovery;
use App\View\Composers\StorefrontSeoComposer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use PDOException;
use SocialiteProviders\Apple\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppProvider::class, function () {
            return match (config('whatsapp.driver')) {
                'msg91' => new Msg91WhatsAppProvider,
                default => new MetaWhatsAppProvider,
            };
        });
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureSocialiteProviders();

        // Composer `package:discover` boots the app before a database is
        // guaranteed. Keep config seed values as the safe fallback there.
        if (! PackageDiscovery::running()) {
            try {
                CmsSettings::hydrate();
            } catch (QueryException|PDOException $e) {
                // Database may not be ready during install/runtime outages.
                // Only connection/query failures are deferred; other boot bugs
                // must still surface.
                CmsSettings::recordHydrationFailure($e);

                try {
                    Log::warning('CmsSettings::hydrate() failed; storefront is serving config seed content.', [
                        'exception' => $e->getMessage(),
                    ]);
                } catch (Throwable) {
                    // Logging must never break booting.
                }
            }
        }

        View::composer('layouts.store', StorefrontSeoComposer::class);

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return url(route('account.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false));
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by($email !== '' ? 'auth-email:'.$email : 'auth-email:'.$request->ip()),
            ];
        });

        RateLimiter::for('admin-mfa', function (Request $request) {
            $userId = $request->user()?->id ?? $request->session()->get(AdminMfa::SESSION_PENDING, $request->ip());

            return [
                Limit::perMinute(8)->by($request->ip()),
                Limit::perMinute(5)->by('admin-mfa:'.$userId),
            ];
        });

        RateLimiter::for('admin-passkey', function (Request $request) {
            $sessionId = $request->session()->getId();

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(6)->by('admin-passkey-session:'.$sessionId),
            ];
        });

        RateLimiter::for('otp-send', fn (Request $request) => [
            Limit::perHour(3)->by($request->ip()),
            Limit::perHour(3)->by('otp-send-session:'.$request->session()->getId()),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perHour(5)->by(
            'otp-verify:'.$request->session()->get('account_pending_verification_id', $request->ip())
        ));

        RateLimiter::for('password-reset', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perHour(5)->by($request->ip()),
                Limit::perHour(3)->by($email !== '' ? 'password-reset:'.$email : 'password-reset:'.$request->ip()),
            ];
        });

        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('buy-now', fn (Request $request) => Limit::perMinute(8)->by($request->ip()));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('general-enquiry', fn (Request $request) => [
            Limit::perMinutes(15, 3)->by($request->ip()),
            Limit::perMinutes(15, 3)->by('general-enquiry-session:'.$request->session()->getId()),
        ]);

        RateLimiter::for('professional-application', fn (Request $request) => Limit::perHour(2)->by($request->ip()));

        RateLimiter::for('catalogue-request', fn (Request $request) => Limit::perHour(3)->by($request->ip()));

        RateLimiter::for('vendor-proposal', fn (Request $request) => Limit::perHour(2)->by($request->ip()));

        RateLimiter::for('file-upload-forms', fn (Request $request) => Limit::perMinutes(30, 2)->by($request->ip()));
    }

    private function configureSocialiteProviders(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('apple', Provider::class);
        });
    }
}
