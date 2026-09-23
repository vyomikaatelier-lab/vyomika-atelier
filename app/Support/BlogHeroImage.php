<?php

namespace App\Support;

class BlogHeroImage
{
    /** @var array<string, array{width: int, height: int}> */
    private const KNOWN_DIMENSIONS = [
        'glass-partitions-open-plan-hero.jpg' => ['width' => 768, 'height' => 1024],
        'glass-partitions-open-plan-hero-card.jpg' => ['width' => 768, 'height' => 432],
        'pvd-coating-explained-hero.jpg' => ['width' => 1024, 'height' => 682],
        'pvd-coating-explained-hero-card.jpg' => ['width' => 1024, 'height' => 576],
        'corten-steel-modern-facades-hero.jpg' => ['width' => 1024, 'height' => 576],
    ];

    public static function resolve(?string $path): ?string
    {
        return MediaUrl::resolve($path);
    }

    public static function webpPath(?string $jpegPath): ?string
    {
        if (! filled($jpegPath)) {
            return null;
        }

        if (preg_match('#^https?://#i', $jpegPath)) {
            return null;
        }

        $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $jpegPath);
        if ($webp === null || $webp === $jpegPath) {
            return null;
        }

        $public = public_path(ltrim(str_replace('\\', '/', $webp), '/'));

        return is_file($public) ? $webp : null;
    }

    public static function cardPath(?string $heroPath): ?string
    {
        if (! filled($heroPath)) {
            return null;
        }

        if (preg_match('#^https?://#i', $heroPath)) {
            return null;
        }

        $card = preg_replace('/(-hero)?\.(jpe?g|png|webp)$/i', '-hero-card.jpg', $heroPath);
        if ($card === null) {
            return null;
        }

        $public = public_path(ltrim(str_replace('\\', '/', $card), '/'));

        return is_file($public) ? $card : null;
    }

    /** @return array{width: int, height: int} */
    public static function dimensions(?string $path): array
    {
        if (! filled($path)) {
            return ['width' => 640, 'height' => 400];
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $basename = basename($normalized);

        if (isset(self::KNOWN_DIMENSIONS[$basename])) {
            return self::KNOWN_DIMENSIONS[$basename];
        }

        $absolute = public_path($normalized);
        if (is_file($absolute)) {
            $size = @getimagesize($absolute);
            if ($size !== false) {
                return ['width' => (int) $size[0], 'height' => (int) $size[1]];
            }
        }

        return ['width' => 640, 'height' => 400];
    }

    /** @return array{jpeg: ?string, webp: ?string, width: int, height: int} */
    public static function variant(?string $jpegPath): array
    {
        $dims = self::dimensions($jpegPath);

        return [
            'jpeg' => self::resolve($jpegPath),
            'webp' => self::resolve(self::webpPath($jpegPath)),
            'width' => $dims['width'],
            'height' => $dims['height'],
        ];
    }

    /** @return array{jpeg: ?string, webp: ?string, width: int, height: int} */
    public static function heroVariant(?string $heroPath): array
    {
        return self::variant($heroPath);
    }

    /** @return array{jpeg: ?string, webp: ?string, width: int, height: int} */
    public static function cardVariant(?string $heroPath): array
    {
        $card = self::cardPath($heroPath) ?? $heroPath;

        return self::variant($card);
    }

    public static function ogPath(?string $heroPath, ?string $ogOverride = null): ?string
    {
        $card = self::cardPath($heroPath);

        if (filled($ogOverride) && $ogOverride !== $heroPath) {
            return $ogOverride;
        }

        return $card ?? $heroPath;
    }
}
