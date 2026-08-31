<?php

namespace App\Support;

class ResponsiveHero
{
    /** @param  array<string, mixed>  $hero */
    public static function urls(array $hero, ?string $fallbackDesktop = null): array
    {
        $desktop = self::resolveUrl($hero['image'] ?? $fallbackDesktop);
        $tablet = self::resolveUrl($hero['image_tablet'] ?? null) ?? $desktop;
        $mobile = self::resolveUrl($hero['image_mobile'] ?? null) ?? $tablet;

        return [
            'desktop' => $desktop,
            'tablet' => $tablet,
            'mobile' => $mobile,
        ];
    }

    /** @return array<string, string> */
    public static function flatValidationRules(string $prefix = 'hero'): array
    {
        $rules = [];

        foreach (self::flatFieldKeys($prefix) as $field) {
            $rules[$field] = 'nullable|string|max:2048';
            $rules[$field.'_file'] = 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120';
            $rules[$field.'_remove'] = 'nullable|boolean';
        }

        return $rules;
    }

    /** @return list<string> */
    public static function storageKeys(): array
    {
        return ['image', 'image_tablet', 'image_mobile'];
    }

    /** @return list<string> */
    public static function flatFieldKeys(string $prefix = 'hero'): array
    {
        return array_map(
            fn (string $storageKey) => self::flatFieldForStorageKey($prefix, $storageKey),
            self::storageKeys()
        );
    }

    public static function flatFieldForStorageKey(string $prefix, string $storageKey): string
    {
        return $storageKey === 'image'
            ? "{$prefix}_image"
            : "{$prefix}_{$storageKey}";
    }

    public static function resolveUrl(?string $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return null;
        }

        return MediaUrl::resolve($path) ?? $path;
    }

    public static function localPath(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parsedPath = parse_url(trim($url), PHP_URL_PATH);
        $path = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : trim($url);
        $path = str_replace('\\', '/', $path);
        $relative = ltrim($path, '/');

        $candidates = [];
        if (str_starts_with($relative, 'storage/')) {
            $stored = substr($relative, strlen('storage/'));
            $candidates[] = storage_path('app/public/'.$stored);
            $candidates[] = public_path('storage/'.$stored);
        }

        $candidates[] = public_path($relative);
        $candidates[] = storage_path('app/public/'.$relative);

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array{width: int, height: int, mime: ?string}|null */
    public static function dimensions(?string $url): ?array
    {
        $local = self::localPath($url);
        if ($local === null) {
            return null;
        }

        $size = @getimagesize($local);
        if ($size === false) {
            return null;
        }

        return [
            'width' => (int) $size[0],
            'height' => (int) $size[1],
            'mime' => isset($size['mime']) ? (string) $size['mime'] : null,
        ];
    }

    public static function mimeType(?string $url): ?string
    {
        $dims = self::dimensions($url);
        if (is_string($dims['mime'] ?? null) && $dims['mime'] !== '') {
            return $dims['mime'];
        }

        if (! is_string($url) || $url === '') {
            return null;
        }

        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: $url));

        return match (true) {
            str_ends_with($path, '.webp') => 'image/webp',
            str_ends_with($path, '.jpg'), str_ends_with($path, '.jpeg') => 'image/jpeg',
            str_ends_with($path, '.png') => 'image/png',
            default => null,
        };
    }

    public static function webpTwinUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $twin = preg_replace('/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', $url);
        if (! is_string($twin) || $twin === $url) {
            return null;
        }

        return self::localPath($twin) !== null ? $twin : null;
    }

    /** @return array{width: int, height: int}|null */
    private static function sourceDimensions(?string $url): ?array
    {
        $dims = self::dimensions($url);
        if ($dims === null) {
            return null;
        }

        $width = (int) ($dims['width'] ?? 0);
        $height = (int) ($dims['height'] ?? 0);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @param  array<string, mixed>  $hero
     * @return list<array{media: string, srcset: string, type: ?string, width: ?int, height: ?int}>
     */
    public static function pictureSources(array $hero, ?string $fallbackDesktop = null): array
    {
        $urls = self::urls($hero, $fallbackDesktop);
        $sources = [];

        foreach ([
            'mobile' => '(max-width: 767px)',
            'tablet' => '(max-width: 1023px)',
        ] as $key => $media) {
            $url = $urls[$key] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }

            $sourceDims = self::sourceDimensions($url);

            $webp = self::webpTwinUrl($url);
            if ($webp !== null) {
                $source = [
                    'media' => $media,
                    'srcset' => $webp,
                    'type' => 'image/webp',
                ];
                if ($sourceDims !== null) {
                    $source['width'] = $sourceDims['width'];
                    $source['height'] = $sourceDims['height'];
                }
                $sources[] = $source;
            }

            $type = self::mimeType($url);
            $source = [
                'media' => $media,
                'srcset' => $url,
                'type' => $type === 'image/webp' ? 'image/webp' : null,
            ];
            if ($sourceDims !== null) {
                $source['width'] = $sourceDims['width'];
                $source['height'] = $sourceDims['height'];
            }
            $sources[] = $source;
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $hero
     * @return array{src: string, srcset: string, sizes: string, width: ?int, height: ?int, sources: list<array{media: string, srcset: string, type: ?string, width: ?int, height: ?int}>}|null
     */
    public static function picture(array $hero, ?string $fallbackDesktop = null, string $sizes = '100vw'): ?array
    {
        $urls = self::urls($hero, $fallbackDesktop);
        $src = $urls['desktop'] ?? $urls['tablet'] ?? $urls['mobile'] ?? null;
        if (! is_string($src) || $src === '') {
            return null;
        }

        $desktopDims = self::sourceDimensions($src);
        $srcset = $src;
        if ($desktopDims !== null) {
            $srcset = $src.' '.$desktopDims['width'].'w';
        }

        return [
            'src' => $src,
            'srcset' => $srcset,
            'sizes' => $sizes,
            'width' => $desktopDims['width'] ?? null,
            'height' => $desktopDims['height'] ?? null,
            'sources' => self::pictureSources($hero, $fallbackDesktop),
        ];
    }

    /**
     * @param  array<string, mixed>  $hero
     * @return list<array{href: string, media: ?string, type: ?string}>
     */
    public static function preloadLinks(array $hero, ?string $fallbackDesktop = null): array
    {
        $urls = self::urls($hero, $fallbackDesktop);

        $href = static function (?string $url): ?string {
            if (! is_string($url) || $url === '') {
                return null;
            }

            return self::webpTwinUrl($url) ?? $url;
        };

        $mobileHref = $href($urls['mobile'] ?? null);
        $tabletHref = $href($urls['tablet'] ?? null);
        $desktopHref = $href($urls['desktop'] ?? null) ?? $tabletHref ?? $mobileHref;

        if ($tabletHref === null) {
            $tabletHref = $desktopHref;
        }
        if ($mobileHref === null) {
            $mobileHref = $tabletHref;
        }

        if ($mobileHref === null) {
            return [];
        }

        $ranges = [
            ['min' => 0, 'max' => 767, 'href' => $mobileHref],
            ['min' => 768, 'max' => 1023, 'href' => $tabletHref],
            ['min' => 1024, 'max' => null, 'href' => $desktopHref],
        ];

        $collapsed = [];
        foreach ($ranges as $range) {
            $last = $collapsed === [] ? null : array_key_last($collapsed);
            if ($last !== null && $collapsed[$last]['href'] === $range['href']) {
                $collapsed[$last]['max'] = $range['max'];
                continue;
            }

            $collapsed[] = $range;
        }

        $links = [];
        foreach ($collapsed as $range) {
            $type = self::mimeType($range['href']);
            $links[] = [
                'href' => $range['href'],
                'media' => self::preloadMediaQuery($range['min'], $range['max']),
                'type' => $type === 'image/webp' ? 'image/webp' : null,
            ];
        }

        return $links;
    }

    private static function preloadMediaQuery(int $min, ?int $max): ?string
    {
        if ($min <= 0 && $max === null) {
            return null;
        }

        if ($min <= 0) {
            return '(max-width: '.$max.'px)';
        }

        if ($max === null) {
            return '(min-width: '.$min.'px)';
        }

        return '(min-width: '.$min.'px) and (max-width: '.$max.'px)';
    }

    /**
     * Admin upload guidance for responsive hero / cover images.
     *
     * @return array<string, array{label: string, hint: string, size: string, key: string}>
     */
    public static function adminVariants(string $context = 'cover'): array
    {
        $specs = match ($context) {
            'homepage' => [
                'desktop' => ['size' => '1920 × 1080 px', 'ratio' => '16:9 landscape', 'min' => '1600 × 900 px', 'crop' => 'Keep subject centered; image sits beside text on large screens.'],
                'tablet' => ['size' => '1200 × 900 px', 'ratio' => '4:3 landscape', 'min' => '1024 × 768 px', 'crop' => 'Landscape iPad crop. Falls back to desktop if empty.'],
                'mobile' => ['size' => '900 × 1200 px', 'ratio' => '3:4 portrait', 'min' => '800 × 1200 px', 'crop' => 'Portrait or square; image stacks above text. Falls back to tablet/desktop if empty.'],
            ],
            'compact' => [
                'desktop' => ['size' => '600 × 480 px', 'ratio' => '5:4 landscape', 'min' => '600 × 480 px', 'crop' => 'One image for all devices — scales automatically on tablet and mobile. Upload 1200×960 for retina (2×).'],
                'tablet' => ['size' => '600 × 480 px', 'ratio' => '5:4 landscape', 'min' => '600 × 480 px', 'crop' => 'Falls back to desktop image if empty.'],
                'mobile' => ['size' => '600 × 480 px', 'ratio' => '5:4 landscape', 'min' => '600 × 480 px', 'crop' => 'Falls back to desktop image if empty.'],
            ],
            'service' => [
                'desktop' => ['size' => '1920 × 1080 px', 'ratio' => '16:9 landscape', 'min' => '1600 × 900 px', 'crop' => 'Also used as the /services list thumbnail. Keep the subject centered.'],
                'tablet' => ['size' => '1200 × 800 px', 'ratio' => '3:2 landscape', 'min' => '1024 × 768 px', 'crop' => 'Landscape iPad crop. Falls back to desktop if empty.'],
                'mobile' => ['size' => '800 × 1200 px', 'ratio' => '2:3 portrait', 'min' => '800 × 1200 px', 'crop' => 'Portrait crop for phones. Falls back to tablet/desktop if empty.'],
            ],
            default => [
                'desktop' => ['size' => '1920 × 1080 px', 'ratio' => '16:9 landscape', 'min' => '1600 × 900 px', 'crop' => 'Full-width hero background. Keep important detail away from edges.'],
                'tablet' => ['size' => '1200 × 800 px', 'ratio' => '3:2 landscape', 'min' => '1024 × 768 px', 'crop' => 'Landscape iPad crop. Falls back to desktop if empty.'],
                'mobile' => ['size' => '800 × 1200 px', 'ratio' => '2:3 portrait', 'min' => '800 × 1200 px', 'crop' => 'Portrait crop for phones. Falls back to tablet/desktop if empty.'],
            ],
        };

        $labels = [
            'desktop' => 'Desktop image (1024px and wider)',
            'tablet' => 'Tablet / iPad image (768px–1023px)',
            'mobile' => 'Mobile image (phones, up to 767px)',
        ];

        $keys = [
            'desktop' => 'image',
            'tablet' => 'image_tablet',
            'mobile' => 'image_mobile',
        ];

        $variants = [];

        foreach ($labels as $variant => $label) {
            $meta = $specs[$variant];
            $variants[$variant] = [
                'label' => $label,
                'size' => $meta['size'],
                'hint' => sprintf(
                    'Recommended %s (%s). Min %s. %s JPG, PNG, or WebP · max 5 MB.',
                    $meta['size'],
                    $meta['ratio'],
                    $meta['min'],
                    $meta['crop']
                ),
                'key' => $keys[$variant],
            ];
        }

        return $variants;
    }

    /**
     * Variants shown in admin upload forms (compact uses a single desktop slot).
     *
     * @return array<string, array{label: string, hint: string, size: string, key: string}>
     */
    public static function adminFormVariants(string $context = 'cover'): array
    {
        $variants = self::adminVariants($context);

        if ($context !== 'compact') {
            return $variants;
        }

        $desktop = $variants['desktop'];
        $desktop['label'] = 'Hero image (all devices)';

        return ['desktop' => $desktop];
    }

    public static function adminUploadIntro(string $context = 'cover'): string
    {
        return match ($context) {
            'homepage' => 'Upload separate images per slide for desktop (1024px+), tablet/iPad (768–1023px), and mobile (up to 767px). Recommended: desktop 1920×1080, tablet 1200×900, mobile 900×1200.',
            'compact' => 'One image 600×480 (or 1200×960 retina) — used on all devices, scales automatically.',
            'service' => 'Upload desktop, tablet, and mobile cover images. Desktop is also used on the /services listing. Recommended: desktop 1920×1080, tablet 1200×800, mobile 800×1200.',
            default => 'Upload desktop, tablet, and mobile cover images. Empty tablet/mobile slots fall back to the next larger size. Recommended: desktop 1920×1080, tablet 1200×800, mobile 800×1200.',
        };
    }
}
