<?php

namespace App\Support\Seo;

use App\Support\StaticPageContent;

class StaticPageSeo
{
    /** @return array<string, mixed> */
    public static function forSlug(string $slug, ?string $robots = null): array
    {
        $page = StaticPageContent::page($slug);

        return PageSeo::make([
            'title' => $page['meta_title'] ?? null,
            'description' => $page['meta_description'] ?? null,
            'canonical' => $page['canonical'] ?? url()->current(),
            'robots' => $robots ?? (($page['robots'] ?? null) === 'noindex' ? 'noindex,follow' : null),
            'og_title' => $page['og_title'] ?? null,
            'og_description' => $page['og_description'] ?? null,
            'og_image' => $page['og_image'] ?? null,
            'primary_keyword' => $page['primary_keyword'] ?? null,
        ]);
    }
}
