<?php

namespace App\Support;

/**
 * Single load point for PHP catalog data files that declare helpers.
 * Prevents "Cannot redeclare function" when multiple callers require the same file.
 */
class CatalogData
{
    /** @var array<string, list<array<string, mixed>>>|null */
    private static ?array $serviceGallery = null;

    /** @return array<string, list<array<string, mixed>>> */
    public static function serviceGallery(): array
    {
        if (self::$serviceGallery !== null) {
            return self::$serviceGallery;
        }

        $path = database_path('data/service-gallery-catalog.php');
        $loaded = require_once $path;

        if (is_array($loaded)) {
            return self::$serviceGallery = $loaded;
        }

        // Included earlier via plain require (e.g. CatalogSyncSeeder); function is
        // guarded so a second include only rebuilds the returned array.
        return self::$serviceGallery = require $path;
    }
}
