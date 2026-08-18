<?php

namespace App\Support\Seo;

use App\Support\LandingPageContent;

class LandingPageSeo
{
    /** @return array<string, mixed> */
    public static function forSlug(string $slug): array
    {
        $page = LandingPageContent::page($slug);

        $canonical = filled($page['canonical'] ?? null)
            ? (string) $page['canonical']
            : LandingPageContent::publicRoute($slug);

        $robots = ($page['robots'] ?? null) === 'noindex' ? 'noindex,follow' : null;

        $ogImage = $page['og_image'] ?? data_get($page, 'hero.image');

        return PageSeo::make([
            'title' => $page['meta_title'] ?? null,
            'description' => $page['meta_description'] ?? null,
            'canonical' => $canonical,
            'robots' => $robots,
            'og_title' => $page['og_title'] ?? null,
            'og_description' => $page['og_description'] ?? null,
            'og_image' => $ogImage,
            'primary_keyword' => $page['primary_keyword'] ?? null,
        ]);
    }
}
