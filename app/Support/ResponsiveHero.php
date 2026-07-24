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
            $rules[$field] = 'nullable|string|max:500';
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
        if (! filled($path)) {
            return null;
        }

        return MediaUrl::resolve($path) ?? $path;
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

    public static function adminUploadIntro(string $context = 'cover'): string
    {
        return match ($context) {
            'homepage' => 'Upload separate images per slide for desktop (1024px+), tablet/iPad (768–1023px), and mobile (up to 767px). Recommended: desktop 1920×1080, tablet 1200×900, mobile 900×1200.',
            'service' => 'Upload desktop, tablet, and mobile cover images. Desktop is also used on the /services listing. Recommended: desktop 1920×1080, tablet 1200×800, mobile 800×1200.',
            default => 'Upload desktop, tablet, and mobile cover images. Empty tablet/mobile slots fall back to the next larger size. Recommended: desktop 1920×1080, tablet 1200×800, mobile 800×1200.',
        };
    }
}
