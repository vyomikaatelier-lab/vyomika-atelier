<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\SiteSetting;

trait SavesStaticPageSeoFields
{
    /** @return array<string, string> */
    protected function staticPageSeoValidationRules(): array
    {
        return [
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'canonical' => 'nullable|string|max:500',
            'primary_keyword' => 'nullable|string|max:120',
            'seo_keyword' => 'nullable|string|max:120',
            'robots' => 'nullable|in:index,noindex',
        ];
    }

    /** @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $extra
     */
    protected function persistStaticPageSeoFields(string $slug, array $validated, array $extra = []): void
    {
        $all = SiteSetting::getValue('static_pages', []) ?? [];
        $existing = is_array($all[$slug] ?? null) ? $all[$slug] : [];

        $all[$slug] = array_merge($existing, [
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'og_title' => $validated['og_title'] ?? null,
            'og_description' => $validated['og_description'] ?? null,
            'og_image' => $validated['og_image'] ?? null,
            'canonical' => $validated['canonical'] ?? null,
            'primary_keyword' => $validated['primary_keyword'] ?? null,
            'seo_keyword' => $validated['seo_keyword'] ?? null,
            'robots' => $validated['robots'] ?? 'index',
        ], $extra);

        SiteSetting::setValue('static_pages', $all);
    }
}
