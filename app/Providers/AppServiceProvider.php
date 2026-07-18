<?php

namespace App\Providers;

use App\Contracts\WhatsAppProvider;
use App\Services\WhatsApp\MetaWhatsAppProvider;
use App\Support\CmsSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppProvider::class, function () {
            return match (config('whatsapp.driver')) {
                default => new MetaWhatsAppProvider,
            };
        });
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        try {
            CmsSettings::hydrate();
        } catch (\Throwable) {
            // Database may not be ready during install.
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('otp-send', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('leads', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
