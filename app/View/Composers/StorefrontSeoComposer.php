<?php

namespace App\View\Composers;

use App\Support\Seo\PageSeo;
use App\Support\StaticPageContent;
use Illuminate\View\View;

class StorefrontSeoComposer
{
    public function compose(View $view): void
    {
        $existing = $view->offsetExists('pageSeo') ? $view->offsetGet('pageSeo') : null;
        if (is_array($existing) && $existing !== []) {
            return;
        }

        $name = request()->route()?->getName();
        $robots = null;

        if (is_string($name) && (
            str_starts_with($name, 'cart.')
            || str_starts_with($name, 'checkout.')
            || str_starts_with($name, 'account.')
            || $name === 'account'
            || str_starts_with($name, 'form-protection.')
            || $name === 'catalogue.download'
        )) {
            $robots = 'noindex,nofollow';
        }

        $slug = match ($name) {
            'home' => 'home',
            'shop.index' => 'shop',
            'studio.index' => 'studio',
            'about' => 'about',
            'professionals.index' => 'professionals',
            'projects.index' => 'projects',
            'blog.index' => 'blog',
            'contact.index' => 'contact',
            default => null,
        };

        if ($slug === null && $robots === null) {
            return;
        }

        $page = $slug ? StaticPageContent::page($slug) : [];

        $view->with('pageSeo', PageSeo::make([
            'title' => $page['meta_title'] ?? null,
            'description' => $page['meta_description'] ?? null,
            'canonical' => $page['canonical'] ?? url()->current(),
            'robots' => $robots ?? (($page['robots'] ?? null) === 'noindex' ? 'noindex,follow' : null),
            'og_title' => $page['og_title'] ?? null,
            'og_description' => $page['og_description'] ?? null,
            'og_image' => $page['og_image'] ?? null,
            'primary_keyword' => $page['primary_keyword'] ?? null,
        ]));
    }
}
