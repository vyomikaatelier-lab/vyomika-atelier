<?php

namespace App\Support;

use App\Models\SiteSetting;

class CollectionNav
{
    /** @return array<int, array<string, mixed>> */
    public static function nav(): array
    {
        $nav = SiteSetting::getValue('nav');

        if (! is_array($nav) || $nav === []) {
            return config('site.nav', []);
        }

        return $nav;
    }

    /** @param  array<int, array<string, mixed>>  $nav */
    public static function persistNav(array $nav): void
    {
        SiteSetting::setValue('nav', $nav);
    }

    public static function addShopCollectionLink(string $slug, string $label): void
    {
        self::persistNav(self::upsertShopChild(self::nav(), $slug, $label));
    }

    public static function removeShopCollectionLink(string $slug): void
    {
        $nav = self::nav();

        foreach ($nav as &$item) {
            if (($item['label'] ?? '') !== 'Shop' || empty($item['children']) || ! is_array($item['children'])) {
                continue;
            }

            $item['children'] = array_values(array_filter(
                $item['children'],
                fn (array $link) => ! self::linkMatchesShopSlug($link, $slug)
            ));
        }
        unset($item);

        self::persistNav($nav);
    }

    /** @param  array<string, mixed>  $link */
    public static function linkMatchesShopSlug(array $link, string $slug): bool
    {
        if ($slug === 'mirror-frames') {
            return ($link['route'] ?? '') === 'shop.mirror-frames.index';
        }

        return ($link['route'] ?? '') === 'shop.show'
            && (($link['params']['slug'] ?? null) === $slug);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nav
     * @return array<int, array<string, mixed>>
     */
    private static function upsertShopChild(array $nav, string $slug, string $label): array
    {
        $newLink = $slug === 'mirror-frames'
            ? ['label' => $label, 'route' => 'shop.mirror-frames.index']
            : ['label' => $label, 'route' => 'shop.show', 'params' => ['slug' => $slug]];

        $updated = false;

        foreach ($nav as &$item) {
            if (($item['label'] ?? '') !== 'Shop') {
                continue;
            }

            $children = is_array($item['children'] ?? null) ? $item['children'] : [];

            foreach ($children as $index => $link) {
                if (! is_array($link)) {
                    continue;
                }

                if (self::linkMatchesShopSlug($link, $slug)) {
                    $children[$index]['label'] = $label;
                    $updated = true;
                    break;
                }
            }

            if (! $updated) {
                $children[] = $newLink;
            }

            $item['children'] = $children;
            $updated = true;
        }
        unset($item);

        if (! $updated) {
            $nav[] = ['label' => 'Shop', 'children' => [$newLink]];
        }

        return $nav;
    }
}
