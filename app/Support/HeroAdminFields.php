<?php

namespace App\Support;

use Illuminate\Http\Request;

class HeroAdminFields
{
    /**
     * Admin upload context for responsive hero fields (compact = single 600×480 slot).
     *
     * @param  array<string, mixed>  $hero
     */
    public static function uploadContext(array $hero, ?string $fallbackContext = null): string
    {
        $layoutFallback = ($fallbackContext ?? 'cover') === 'compact' ? 'compact' : 'default';
        $layout = (string) ($hero['hero_layout'] ?? $layoutFallback);

        return $layout === 'compact' ? 'compact' : ($fallbackContext ?? 'cover');
    }

    /** @return array<string, mixed> */
    public static function validationRules(string $prefix = 'hero'): array
    {
        return [
            "{$prefix}_eyebrow" => 'nullable|string|max:120',
            "{$prefix}_label" => 'nullable|string|max:120',
            "{$prefix}_title" => 'nullable|string|max:255',
            "{$prefix}_title_line1" => 'nullable|string|max:120',
            "{$prefix}_title_accent" => 'nullable|string|max:120',
            "{$prefix}_title_line2" => 'nullable|string|max:120',
            "{$prefix}_tagline" => 'nullable|string|max:255',
            "{$prefix}_tagline_accent" => 'nullable|string|max:255',
            "{$prefix}_subtitle" => 'nullable|string|max:2000',
            "{$prefix}_highlights" => 'nullable|string|max:4000',
            "{$prefix}_footer_tagline" => 'nullable|string|max:255',
            "{$prefix}_footer_tagline_accent" => 'nullable|string|max:255',
            "{$prefix}_cta_primary_label" => 'nullable|string|max:120',
            "{$prefix}_cta_primary_href" => 'nullable|string|max:255',
            "{$prefix}_cta_secondary_label" => 'nullable|string|max:120',
            "{$prefix}_cta_secondary_href" => 'nullable|string|max:255',
            "{$prefix}_image_alt" => 'nullable|string|max:255',
            "{$prefix}_layout" => 'nullable|string|in:default,compact',
            "{$prefix}_image_position" => 'nullable|string|in:left,right',
            ...ResponsiveHero::flatValidationRules($prefix),
        ];
    }

    /**
     * @param  array<string, mixed>  $existingHero
     * @param  array<string, string|null>  $heroImages
     * @return array<string, mixed>
     */
    public static function buildFromRequest(
        Request $request,
        string $prefix = 'hero',
        array $existingHero = [],
        array $heroImages = []
    ): array {
        $hero = array_merge($existingHero, array_filter([
            'eyebrow' => $request->input("{$prefix}_eyebrow") ?: $request->input("{$prefix}_label"),
            'label' => $request->input("{$prefix}_label") ?: $request->input("{$prefix}_eyebrow"),
            'title' => $request->input("{$prefix}_title"),
            'title_line1' => $request->input("{$prefix}_title_line1"),
            'title_accent' => $request->input("{$prefix}_title_accent"),
            'title_line2' => $request->input("{$prefix}_title_line2"),
            'tagline' => $request->input("{$prefix}_tagline"),
            'tagline_accent' => $request->input("{$prefix}_tagline_accent"),
            'subtitle' => $request->input("{$prefix}_subtitle"),
            'footer_tagline' => $request->input("{$prefix}_footer_tagline"),
            'footer_tagline_accent' => $request->input("{$prefix}_footer_tagline_accent"),
            'image_alt' => $request->input("{$prefix}_image_alt"),
            'hero_layout' => $request->input("{$prefix}_layout"),
            'image_position' => $request->input("{$prefix}_image_position"),
        ], fn ($value) => $value !== null && $value !== ''));

        $highlights = self::linesToList($request->input("{$prefix}_highlights"));
        if ($highlights !== []) {
            $hero['highlights'] = $highlights;
        } elseif ($request->has("{$prefix}_highlights")) {
            unset($hero['highlights']);
        }

        $ctaPrimary = array_filter([
            'label' => $request->input("{$prefix}_cta_primary_label"),
            'href' => $request->input("{$prefix}_cta_primary_href"),
        ], fn ($value) => filled($value));

        $ctaSecondary = array_filter([
            'label' => $request->input("{$prefix}_cta_secondary_label"),
            'href' => $request->input("{$prefix}_cta_secondary_href"),
        ], fn ($value) => filled($value));

        if ($ctaPrimary !== []) {
            $hero['cta_primary'] = $ctaPrimary;
        } elseif ($request->has("{$prefix}_cta_primary_label") || $request->has("{$prefix}_cta_primary_href")) {
            unset($hero['cta_primary']);
        }

        if ($ctaSecondary !== []) {
            $hero['cta_secondary'] = $ctaSecondary;
        } elseif ($request->has("{$prefix}_cta_secondary_label") || $request->has("{$prefix}_cta_secondary_href")) {
            unset($hero['cta_secondary']);
        }

        foreach ($heroImages as $key => $path) {
            if (filled($path)) {
                $hero[$key] = $path;
            }
        }

        foreach (ResponsiveHero::storageKeys() as $storageKey) {
            $flatField = ResponsiveHero::flatFieldForStorageKey($prefix, $storageKey);
            if ($request->boolean($flatField.'_remove')) {
                unset($hero[$storageKey]);
            }
        }

        if (($hero['hero_layout'] ?? '') === 'compact') {
            unset($hero['image_tablet'], $hero['image_mobile']);
        }

        return $hero;
    }

    /** @return list<string> */
    public static function linesToList(?string $text): array
    {
        if (! filled($text)) {
            return [];
        }

        return collect(preg_split("/\r\n|\n|\r/", $text) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $hero */
    public static function displayTitle(array $hero): string
    {
        if (filled($hero['title'] ?? null)) {
            return (string) $hero['title'];
        }

        $parts = array_filter([
            $hero['title_line1'] ?? null,
            $hero['title_accent'] ?? null,
            $hero['title_line2'] ?? null,
        ]);

        return $parts !== [] ? implode(' ', $parts) : '—';
    }
}
