<?php

namespace App\Support;

use App\Support\Seo\PageSeo;

class LegalContent
{
    /** @return array<string, string> page key => route name */
    public static function pageRoutes(): array
    {
        return [
            'privacy' => 'legal.privacy',
            'terms' => 'legal.terms',
            'shipping' => 'legal.shipping',
            'cancellation' => 'legal.cancellation',
            'warranty' => 'legal.warranty',
            'grievance' => 'legal.grievance',
        ];
    }

    public static function routeNameForPage(string $key): ?string
    {
        return self::pageRoutes()[$key] ?? null;
    }

    public static function pageKeyForRoute(?string $routeName): ?string
    {
        if (! is_string($routeName) || $routeName === '') {
            return null;
        }

        $key = array_search($routeName, self::pageRoutes(), true);

        return $key === false ? null : $key;
    }

    /**
     * Storefront SEO for a legal page using Admin/config metadata only.
     *
     * @return array<string, mixed>
     */
    public static function pageSeo(string $key): array
    {
        $page = static::page($key) ?? [];
        $routeName = self::routeNameForPage($key);
        $title = filled($page['meta_title'] ?? null)
            ? (string) $page['meta_title']
            : trim((string) ($page['title'] ?? 'Legal')).' — '.(config('legal.business.brand_name') ?? 'Vyomika Atelier');
        $description = filled($page['meta_description'] ?? null)
            ? (string) $page['meta_description']
            : '';

        return PageSeo::make([
            'title' => $title,
            'description' => $description,
            'canonical' => $routeName ? route($routeName) : url()->current(),
            'robots' => 'index,follow',
            'og_title' => $title,
            'og_description' => $description,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function seoForRoute(?string $routeName): array
    {
        $key = self::pageKeyForRoute($routeName);

        return $key ? self::pageSeo($key) : PageSeo::make(['robots' => 'index,follow']);
    }

    public static function business(): array
    {
        return config('legal.business', []);
    }

    public static function lastUpdated(): string
    {
        return config('legal.last_updated', '');
    }

    /** @return list<array{label: string, route: string}> */
    public static function footerLinks(): array
    {
        return config('legal.footer_links', []);
    }

    public static function page(string $key): ?array
    {
        return CmsSettings::legalPage($key);
    }

    public static function interpolate(string $text): string
    {
        $vars = static::business();
        $vars['legal_name'] = $vars['legal_name'] ?? '';
        $vars['brand_name'] = $vars['brand_name'] ?? '';
        $vars['country'] = $vars['country'] ?? 'India';
        $vars['business_type'] = $vars['business_type'] ?? '';
        $vars['email'] = $vars['email'] ?? '';
        $vars['phone'] = $vars['phone'] ?? '';
        $vars['address'] = $vars['address'] ?? '';
        $vars['gstin'] = $vars['gstin'] ?? '';
        $vars['grievance_officer_name'] = $vars['grievance_officer_name'] ?? '';
        $vars['grievance_officer_email'] = $vars['grievance_officer_email'] ?? '';
        $vars['grievance_officer_phone'] = $vars['grievance_officer_phone'] ?? '';
        $vars['registration_note'] = $vars['registration_note'] ?? '';

        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($vars) {
            return $vars[$m[1]] ?? $m[0];
        }, $text);
    }

    public static function interpolateHtml(string $text): string
    {
        $text = static::interpolate($text);
        $contactUrl = route('contact.index');

        return str_replace('href="/contact"', 'href="' . e($contactUrl) . '"', $text);
    }

    /** @return list<array{heading: string, paragraphs: list<string>}> */
    public static function resolvedSections(string $key): array
    {
        $page = static::page($key);
        if (! $page) {
            return [];
        }

        return array_map(function (array $section) {
            return [
                'heading' => $section['heading'],
                'paragraphs' => array_map(
                    fn (string $p) => static::interpolateHtml($p),
                    $section['paragraphs'] ?? []
                ),
            ];
        }, $page['sections'] ?? []);
    }
}
