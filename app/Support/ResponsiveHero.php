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
}
