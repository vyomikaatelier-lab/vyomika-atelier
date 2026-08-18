<?php

namespace App\Support\Seo;

use App\Support\MediaUrl;
use Illuminate\Support\Str;

class GalleryCardSeo
{
    public static function title(array $card, string $fallback = ''): string
    {
        if (filled($card['meta_title'] ?? null)) {
            return trim((string) $card['meta_title']);
        }

        return trim((string) ($card['title'] ?? $card['name'] ?? $card['label'] ?? $fallback));
    }

    public static function imageAlt(array $card, string $fallback = ''): string
    {
        if (filled($card['image_alt'] ?? null)) {
            return trim((string) $card['image_alt']);
        }

        $seoTitle = trim((string) ($card['meta_title'] ?? ''));
        if ($seoTitle !== '') {
            return $seoTitle;
        }

        return trim((string) ($card['title'] ?? $card['name'] ?? $card['label'] ?? $fallback));
    }

    public static function description(array $card): ?string
    {
        if (filled($card['meta_description'] ?? null)) {
            return trim((string) $card['meta_description']);
        }

        $text = trim((string) ($card['text'] ?? $card['description'] ?? ''));

        return $text !== '' ? $text : null;
    }

    public static function ogImage(array $card): ?string
    {
        $image = $card['og_image'] ?? $card['image'] ?? null;
        if (! filled($image)) {
            return null;
        }

        return MediaUrl::resolve((string) $image) ?? (string) $image;
    }

    public static function isIndexable(array $card): bool
    {
        $robotsIndex = $card['robots_index'] ?? true;

        return $robotsIndex !== false && $robotsIndex !== '0' && $robotsIndex !== 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function structuredDataItem(array $card, string $titleKey = 'title', ?string $pageUrl = null): ?array
    {
        if (! self::isIndexable($card)) {
            return null;
        }

        $name = self::title($card, (string) ($card[$titleKey] ?? ''));
        if ($name === '') {
            return null;
        }

        $item = [
            '@type' => 'Product',
            'name' => $name,
        ];

        $description = self::description($card);
        if ($description !== null) {
            $item['description'] = strip_tags($description);
        }

        $image = self::ogImage($card);
        if ($image !== null) {
            $item['image'] = [$image];
        }

        if (filled($card['canonical_url'] ?? null)) {
            $item['url'] = (string) $card['canonical_url'];
        } elseif ($pageUrl !== null) {
            $anchor = $card['slug'] ?? Str::slug($name);
            if ($anchor !== '') {
                $item['url'] = rtrim($pageUrl, '/').'#'.Str::slug($anchor);
            }
        }

        return $item;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return array<string, mixed>|null
     */
    public static function itemList(array $cards, string $listName, string $titleKey = 'title', ?string $pageUrl = null): ?array
    {
        $elements = [];
        $position = 1;

        foreach ($cards as $card) {
            if (! is_array($card)) {
                continue;
            }

            $item = self::structuredDataItem($card, $titleKey, $pageUrl);
            if ($item === null) {
                continue;
            }

            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'item' => $item,
            ];
        }

        if ($elements === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $listName,
            'itemListElement' => $elements,
        ];
    }
}
