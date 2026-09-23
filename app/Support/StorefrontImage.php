<?php

namespace App\Support;

class StorefrontImage
{
    /**
     * @return array{src: string, webp: ?string, width: ?int, height: ?int}|null
     */
    public static function variant(?string $path): ?array
    {
        $src = ResponsiveHero::resolveUrl($path);
        if ($src === null) {
            return null;
        }

        $dims = ResponsiveHero::dimensions($src);

        return [
            'src' => $src,
            'webp' => ResponsiveHero::webpTwinUrl($src),
            'width' => $dims['width'] ?? null,
            'height' => $dims['height'] ?? null,
        ];
    }
}
