<?php

namespace App\Support;

/**
 * Detects Composer's `php artisan package:discover` bootstrap.
 *
 * During install/update Composer boots the app before a database is
 * guaranteed. CMS helpers must serve config/static defaults there.
 */
class PackageDiscovery
{
    private static ?bool $running = null;

    public static function running(): bool
    {
        if (self::$running !== null) {
            return self::$running;
        }

        if (! app()->runningInConsole()) {
            return self::$running = false;
        }

        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (is_string($arg) && str_contains($arg, 'package:discover')) {
                return self::$running = true;
            }
        }

        return self::$running = false;
    }

    /** @internal Tests only. */
    public static function reset(): void
    {
        self::$running = null;
    }
}
