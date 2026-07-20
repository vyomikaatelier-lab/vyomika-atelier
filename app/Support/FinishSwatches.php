<?php

namespace App\Support;

use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

class FinishSwatches
{
    /** @return list<array{slug: string, name: string, image: string, hex: string, rate: int, is_black: bool}> */
    public static function all(): array
    {
        $base = Product::baseSqFtRate();
        $blackRate = Product::blackSqFtRate();
        $overrides = self::imageOverrides();

        return array_map(function (array $swatch) use ($base, $blackRate, $overrides) {
            $slug = $swatch['slug'];

            return [
                'slug' => $slug,
                'name' => $swatch['name'],
                'hex' => $swatch['hex'],
                'is_black' => (bool) $swatch['is_black'],
                'rate' => $swatch['is_black'] ? $blackRate : $base,
                'image' => $overrides[$slug] ?? 'images/finishes/'.$slug.'.jpg',
            ];
        }, config('finishes.swatches', []));
    }

    /** @return array<string, string> slug => image path or URL */
    public static function imageOverrides(): array
    {
        if (! Schema::hasTable('site_settings')) {
            return [];
        }

        $stored = SiteSetting::getValue('finish_swatches', []);

        return is_array($stored) ? $stored : [];
    }

    public static function imageUrl(string $image): string
    {
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        if (str_starts_with($image, 'storage/')) {
            return asset($image);
        }

        return asset($image);
    }

    public static function fallbackSvg(string $image): string
    {
        if (str_ends_with($image, '.jpg')) {
            return self::imageUrl(str_replace('.jpg', '.svg', $image));
        }

        $slug = basename($image, '.'.pathinfo($image, PATHINFO_EXTENSION));

        return asset('images/finishes/'.$slug.'.svg');
    }

    public static function defaultSlug(): string
    {
        return config('finishes.swatches.0.slug', 'champagne-mirror');
    }

    /** @return array{slug: string, name: string, image: string, hex: string, rate: int, is_black: bool}|null */
    public static function findBySlug(?string $slug): ?array
    {
        if (! filled($slug)) {
            return null;
        }

        foreach (self::all() as $swatch) {
            if ($swatch['slug'] === $slug) {
                return $swatch;
            }
        }

        return null;
    }

    /** @return array{slug: string, name: string, image: string, hex: string, rate: int, is_black: bool} */
    public static function resolve(?string $slug): array
    {
        return self::findBySlug($slug) ?? self::findBySlug(self::defaultSlug()) ?? self::all()[0];
    }

    /** @return list<array{slug: string, name: string, image: string, hex: string, rate: int, is_black: bool, image_url: string, fallback_svg: string}> */
    public static function forJsonExport(): array
    {
        return array_map(function (array $swatch) {
            return [
                ...$swatch,
                'image_url' => self::imageUrl($swatch['image']),
                'fallback_svg' => self::fallbackSvg($swatch['image']),
            ];
        }, self::all());
    }
}
