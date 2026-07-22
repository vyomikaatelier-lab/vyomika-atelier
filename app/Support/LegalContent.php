<?php

namespace App\Support;

class LegalContent
{
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
        $vars['pan'] = $vars['pan'] ?? '';
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
