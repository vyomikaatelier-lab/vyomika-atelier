<?php

namespace App\Providers;

use App\Contracts\WhatsAppProvider;
use App\Services\WhatsApp\MetaWhatsAppProvider;
use App\Services\WhatsApp\Msg91WhatsAppProvider;
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
                'msg91' => new Msg91WhatsAppProvider,
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

        RateLimiter::for('otp-send', fn (Request $request) => [
            Limit::perHour(3)->by($request->ip()),
            Limit::perHour(3)->by('otp-send-session:' . $request->session()->getId()),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perHour(5)->by(
            'otp-verify:' . $request->session()->get('account_pending_verification_id', $request->ip())
        ));

        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('general-enquiry', fn (Request $request) => [
            Limit::perMinutes(15, 3)->by($request->ip()),
            Limit::perMinutes(15, 3)->by('general-enquiry-session:' . $request->session()->getId()),
        ]);

        RateLimiter::for('professional-application', fn (Request $request) => Limit::perHour(2)->by($request->ip()));

        RateLimiter::for('catalogue-request', fn (Request $request) => Limit::perHour(3)->by($request->ip()));

        RateLimiter::for('vendor-proposal', fn (Request $request) => Limit::perHour(2)->by($request->ip()));

        RateLimiter::for('file-upload-forms', fn (Request $request) => Limit::perMinutes(30, 2)->by($request->ip()));
    }
}
