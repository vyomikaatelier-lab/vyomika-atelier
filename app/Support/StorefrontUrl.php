<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class StorefrontUrl
{
    /**
     * Generate a named route URL when the route exists (e.g. after route:cache).
     * Falls back to a path or "#" so storefront layout never 500s on stale caches.
     */
    public static function to(string $name, array $params = [], string $fallback = '#'): string
    {
        if (! Route::has($name)) {
            return $fallback === '#' ? '#' : url($fallback);
        }

        try {
            return route($name, $params);
        } catch (\Throwable) {
            return $fallback === '#' ? '#' : url($fallback);
        }
    }
}
