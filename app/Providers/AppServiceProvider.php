<?php

namespace App\Providers;

use App\Contracts\WhatsAppProvider;
use App\Services\WhatsApp\MetaWhatsAppProvider;
use App\Support\CmsSettings;
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
        try {
            CmsSettings::hydrate();
        } catch (\Throwable) {
            // Database may not be ready during install.
        }
    }
}
