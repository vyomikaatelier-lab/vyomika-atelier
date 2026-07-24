<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateIndependentLandingPageRequest;
use App\Models\SiteSetting;
use App\Support\LandingPageContent;

class IndependentLandingAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index()
    {
        $pages = [];

        foreach (LandingPageContent::slugs() as $slug) {
            $page = LandingPageContent::page($slug);
            $pages[] = [
                'slug' => $slug,
                'label' => LandingPageContent::label($slug),
                'title' => data_get($page, 'hero.title', LandingPageContent::label($slug)),
                'url' => LandingPageContent::publicRoute($slug),
            ];
        }

        return view('admin.independent-pages.index', compact('pages'));
    }

    public function edit(string $slug)
    {
        abort_unless(in_array($slug, LandingPageContent::slugs(), true), 404);

        $page = LandingPageContent::page($slug);
        $label = LandingPageContent::label($slug);
        $previewUrl = LandingPageContent::publicRoute($slug);

        return view('admin.independent-pages.form', compact('slug', 'page', 'label', 'previewUrl'));
    }

    public function update(UpdateIndependentLandingPageRequest $request, string $slug)
    {
        abort_unless(in_array($slug, LandingPageContent::slugs(), true), 404);

        $current = LandingPageContent::page($slug);
        $pages = SiteSetting::getValue('landing_pages', []) ?? [];
        $pendingDeletes = [];

        $heroImage = $this->resolveImageField(
            $request,
            'hero_image_file',
            'hero_image',
            data_get($current, 'hero.image'),
            'landing-pages',
            false
        );
        $this->queueImageDelete($pendingDeletes, data_get($current, 'hero.image'), $heroImage);

        $whyImage = $this->resolveImageField(
            $request,
            'why_image_file',
            'why_image',
            data_get($current, 'why.image'),
            'landing-pages',
            false
        );
        $this->queueImageDelete($pendingDeletes, data_get($current, 'why.image'), $whyImage);

        $technicalImage = $this->resolveImageField(
            $request,
            'technical_image_file',
            'technical_image',
            data_get($current, 'technical.image'),
            'landing-pages',
            false
        );
        $this->queueImageDelete($pendingDeletes, data_get($current, 'technical.image'), $technicalImage);

        $override = [
            'meta_title' => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'hero' => [
                'label' => $request->input('hero_label'),
                'title' => $request->input('hero_title'),
                'subtitle' => $request->input('hero_subtitle'),
                'image' => $heroImage,
                'image_alt' => $request->input('hero_image_alt'),
                'highlights' => $this->linesToList($request->input('hero_highlights')),
                'cta_primary' => [
                    'label' => $request->input('hero_cta_primary_label'),
                    'href' => $request->input('hero_cta_primary_href'),
                ],
                'cta_secondary' => [
                    'label' => $request->input('hero_cta_secondary_label'),
                    'href' => $request->input('hero_cta_secondary_href'),
                ],
            ],
            'intro' => [
                'title' => $request->input('intro_title'),
                'body' => $request->input('intro_body'),
            ],
        ];

        if ($slug === 'railings') {
            $override['categories'] = [
                'title' => $request->input('section_title'),
                'subtitle' => $request->input('section_subtitle'),
                'items' => $this->buildCardItems(
                    $request,
                    'cards',
                    data_get($current, 'categories.items', []),
                    'title',
                    $pendingDeletes
                ),
            ];
            $override['layouts'] = [
                'title' => $request->input('layouts_title') ?: data_get($current, 'layouts.title'),
                'subtitle' => $request->input('layouts_subtitle'),
                'items' => $this->buildTextItems($request->input('layouts', []), ['title', 'text']),
            ];
            $override['why'] = [
                'title' => $request->input('why_title'),
                'items' => $this->linesToList($request->input('why_points')),
                'image' => $whyImage,
                'image_alt' => $request->input('why_image_alt'),
            ];
            $override['quote'] = [
                'title' => $request->input('quote_title'),
                'body' => $request->input('quote_body'),
                'bullets' => $this->linesToList($request->input('quote_bullets')),
            ];
        }

        if ($slug === 'corten-steel') {
            $override['applications'] = [
                'title' => $request->input('section_title'),
                'items' => $this->buildCardItems(
                    $request,
                    'apps',
                    data_get($current, 'applications.items', []),
                    'name',
                    $pendingDeletes
                ),
            ];
            $override['why'] = [
                'title' => $request->input('why_title'),
                'points' => $this->linesToList($request->input('why_points')),
                'image' => $whyImage,
                'image_alt' => $request->input('why_image_alt'),
            ];
            $override['finish_evolution'] = [
                'title' => $request->input('finish_title'),
                'note' => $request->input('finish_note'),
                'stages' => $this->buildCardItems(
                    $request,
                    'stages',
                    data_get($current, 'finish_evolution.stages', []),
                    'label',
                    $pendingDeletes
                ),
            ];
            $override['process'] = [
                'title' => $request->input('process_title'),
                'steps' => $this->linesToList($request->input('process_steps')),
            ];
            $override['featured_projects'] = [
                'title' => $request->input('projects_title'),
                'categories' => $this->linesToList($request->input('projects_categories')),
                'items' => $this->buildProjectItems(
                    $request,
                    data_get($current, 'featured_projects.items', []),
                    $pendingDeletes
                ),
            ];
            $override['technical'] = [
                'title' => $request->input('technical_title'),
                'options' => $this->linesToList($request->input('technical_options')),
                'image' => $technicalImage,
                'image_alt' => $request->input('technical_image_alt'),
            ];
            $override['considerations'] = [
                'title' => $request->input('considerations_title'),
                'points' => $this->linesToList($request->input('considerations_points')),
            ];
            $override['faq'] = [
                'title' => $request->input('faq_title'),
                'items' => $this->buildFaqItems($request->input('faqs', [])),
            ];
            $override['cta'] = [
                'title' => $request->input('cta_title'),
                'body' => $request->input('cta_body'),
                'form_label' => $request->input('cta_form_label'),
                'form_title' => $request->input('cta_form_title'),
                'secondary' => [
                    'label' => $request->input('cta_secondary_label'),
                    'href' => $request->input('cta_secondary_href'),
                ],
            ];
        }

        // Preserve quotation form option maps (not edited in this UI).
        $override['form'] = data_get($current, 'form', []);

        $pages[$slug] = $override;
        SiteSetting::setValue('landing_pages', $pages);

        foreach (array_unique(array_filter($pendingDeletes)) as $path) {
            $this->deleteStoredPath($path);
        }

        return redirect()
            ->route('admin.independent-pages.edit', $slug)
            ->with('success', LandingPageContent::label($slug).' updated.');
    }

    /** @return list<string> */
    private function linesToList(?string $text): array
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

    /**
     * @param  list<string>  $pendingDeletes
     * @param  list<array<string, mixed>>  $existing
     * @return list<array<string, mixed>>
     */
    private function buildCardItems(
        UpdateIndependentLandingPageRequest $request,
        string $key,
        array $existing,
        string $titleKey,
        array &$pendingDeletes
    ): array {
        $rows = $request->input($key, []);
        if (! is_array($rows)) {
            return [];
        }

        $items = [];
        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ($row[$titleKey] ?? $row['title'] ?? $row['name'] ?? $row['label'] ?? ''));
            $text = trim((string) ($row['text'] ?? $row['description'] ?? ''));
            $imageUrl = trim((string) ($row['image'] ?? ''));
            $imageAlt = trim((string) ($row['image_alt'] ?? $title));
            $active = ! empty($row['active']);

            $previous = data_get($existing, "{$index}.image");
            $uploaded = $this->storeUpload($request, "{$key}.{$index}.image_file", 'landing-pages');
            $image = $uploaded ?: ($imageUrl !== '' ? $imageUrl : $previous);
            $this->queueImageDelete($pendingDeletes, $previous, $image);

            if ($title === '' && $text === '' && ! filled($image)) {
                if (filled($previous)) {
                    $pendingDeletes[] = $previous;
                }

                continue;
            }

            $item = [
                $titleKey => $title,
                'image' => $image,
                'image_alt' => $imageAlt,
                'active' => $active,
            ];

            if ($text !== '') {
                $item['text'] = $text;
            }

            if ($titleKey === 'title') {
                $item['text'] = $text;
                $ctaLabel = trim((string) ($row['cta_label'] ?? ''));
                $ctaHref = trim((string) ($row['cta_href'] ?? ''));
                if ($ctaLabel !== '') {
                    $item['cta_label'] = $ctaLabel;
                }
                if ($ctaHref !== '') {
                    $item['cta_href'] = $ctaHref;
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    private function buildTextItems(array $rows, array $fields): array
    {
        $items = [];
        foreach (array_values($rows) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = [];
            $empty = true;
            foreach ($fields as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                $item[$field] = $value;
                if ($value !== '') {
                    $empty = false;
                }
            }
            $item['active'] = ! empty($row['active']);
            if (! $empty) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  list<string>  $pendingDeletes
     * @param  list<array<string, mixed>>  $existing
     * @return list<array<string, mixed>>
     */
    private function buildProjectItems(
        UpdateIndependentLandingPageRequest $request,
        array $existing,
        array &$pendingDeletes
    ): array {
        $rows = $request->input('projects', []);
        if (! is_array($rows)) {
            return [];
        }

        $items = [];
        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $imageUrl = trim((string) ($row['image'] ?? ''));
            $previous = data_get($existing, "{$index}.image");
            $uploaded = $this->storeUpload($request, "projects.{$index}.image_file", 'landing-pages');
            $image = $uploaded ?: ($imageUrl !== '' ? $imageUrl : $previous);
            $this->queueImageDelete($pendingDeletes, $previous, $image);

            if ($title === '' && ! filled($image)) {
                if (filled($previous)) {
                    $pendingDeletes[] = $previous;
                }

                continue;
            }

            $items[] = [
                'title' => $title,
                'category' => trim((string) ($row['category'] ?? '')),
                'location' => trim((string) ($row['location'] ?? '')),
                'slug' => filled($row['slug'] ?? null) ? trim((string) $row['slug']) : null,
                'image' => $image,
                'image_alt' => trim((string) ($row['image_alt'] ?? $title)),
                'active' => ! empty($row['active']),
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{q: string, a: string, active: bool}>
     */
    private function buildFaqItems(array $rows): array
    {
        $items = [];
        foreach (array_values($rows) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $q = trim((string) ($row['q'] ?? ''));
            $a = trim((string) ($row['a'] ?? ''));
            if ($q === '' && $a === '') {
                continue;
            }
            $items[] = [
                'q' => $q,
                'a' => $a,
                'active' => ! empty($row['active']),
            ];
        }

        return $items;
    }

    /** @param  list<string>  $pendingDeletes */
    private function queueImageDelete(array &$pendingDeletes, mixed $previous, mixed $next): void
    {
        if (! is_string($previous) || $previous === '' || str_starts_with($previous, 'http')) {
            return;
        }

        if ($previous !== $next) {
            $pendingDeletes[] = $previous;
        }
    }
}
