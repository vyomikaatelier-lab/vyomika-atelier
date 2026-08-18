<?php

namespace App\View\Composers;

use App\Support\Seo\PageSeo;
use App\Support\Seo\StaticPageSeo;
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

        if ($robots === null && request()->routeIs('shop.index') && (request()->filled('search') || request()->filled('sort'))) {
            $robots = 'noindex,follow';
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

        if ($slug === null) {
            $view->with('pageSeo', PageSeo::make(['robots' => $robots]));

            return;
        }

        $view->with('pageSeo', StaticPageSeo::forSlug($slug, $robots));
    }
}
