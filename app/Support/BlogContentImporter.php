<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BlogContentImporter
{
    public const SOURCE = 'blog-content-v1';

    /** Manifest/content keys that map to verified live published slugs. */
    public const LEGACY_SLUG_MAP = [
        'glass-partitions-open-plan-without-compromise' => 'glass-partitions-open-plan',
        'pvd-coating-explained-durable-metal-finishes' => 'pvd-coating-explained',
        'why-corten-steel-is-perfect-for-modern-facades' => 'corten-steel-modern-facades',
    ];

    /** @var list<string> Verified live published slugs — status and published_at must be preserved. */
    public const PRESERVE_PUBLISHED_SLUGS = [
        'glass-partitions-open-plan',
        'pvd-coating-explained',
        'corten-steel-modern-facades',
    ];

    /** Service slugs stored on blog posts that resolve to landing pages, not DB services. */
    public const LANDING_SERVICE_SLUGS = [
        'corten-steel',
        'railings',
    ];

    /** @var array<string, string> Normalize invalid historical service references. */
    public const SERVICE_SLUG_ALIASES = [
        'corten-steel-facade' => 'corten-steel',
    ];

    /** @var list<string>|null */
    private ?array $manifestFinalSlugs = null;

    /** @var array<string, mixed> */
    private array $report = [
        'rows' => [],
        'create' => [],
        'update' => [],
        'skip' => [],
        'flag' => [],
        'errors' => [],
    ];

    private bool $globalOnly = true;

    private bool $regionalOnly = false;

    private bool $publishedOnly = false;

    private bool $draftsOnly = false;

    public function __construct(
        private readonly string $contentPath,
    ) {}

    public function setGlobalOnly(bool $globalOnly): self
    {
        $this->globalOnly = $globalOnly;

        return $this;
    }

    public function setRegionalOnly(bool $regionalOnly): self
    {
        $this->regionalOnly = $regionalOnly;

        return $this;
    }

    public function setPublishedOnly(bool $publishedOnly): self
    {
        $this->publishedOnly = $publishedOnly;

        return $this;
    }

    public function setDraftsOnly(bool $draftsOnly): self
    {
        $this->draftsOnly = $draftsOnly;

        return $this;
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        return $this->report;
    }

    /** @return list<array<string, mixed>> */
    public function loadManifest(): array
    {
        $manifestPath = $this->contentPath.'/manifest.php';

        if (! File::exists($manifestPath)) {
            throw new \RuntimeException("Blog manifest not found: {$manifestPath}");
        }

        $articles = require $manifestPath;

        if (! is_array($articles)) {
            throw new \RuntimeException('Blog manifest must return an array.');
        }

        return $this->hydrateArticles($articles);
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @return list<array<string, mixed>>
     */
    public function filterArticles(array $articles): array
    {
        return array_values(array_filter($articles, function (array $article) {
            $isRegional = isset($article['locale']) && filled($article['locale']);
            $importEligible = (bool) ($article['import_eligible'] ?? ! $isRegional);

            if ($this->regionalOnly && ! $isRegional) {
                return false;
            }

            if ($this->globalOnly && $isRegional) {
                return false;
            }

            if (! $importEligible && ! $this->regionalOnly) {
                return false;
            }

            $status = (string) ($article['status'] ?? BlogPost::STATUS_DRAFT);

            if ($this->publishedOnly && $status !== BlogPost::STATUS_PUBLISHED) {
                return false;
            }

            if ($this->draftsOnly && $status !== BlogPost::STATUS_DRAFT) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @return list<array<string, mixed>>
     */
    private function hydrateArticles(array $articles): array
    {
        $hydrated = [];

        foreach ($articles as $article) {
            $manifestKey = (string) ($article['manifest_key'] ?? $article['slug'] ?? '');
            $finalSlug = $this->resolveFinalSlug($article);

            if ($manifestKey === '' && $finalSlug === '') {
                continue;
            }

            $contentFile = $this->contentPath.'/articles/'.$manifestKey.'.php';
            if (! File::exists($contentFile) && $manifestKey !== $finalSlug) {
                $contentFile = $this->contentPath.'/articles/'.$finalSlug.'.php';
            }

            if (File::exists($contentFile)) {
                $loaded = require $contentFile;
                if (is_array($loaded)) {
                    $article = array_merge($article, $loaded);
                } elseif (is_string($loaded)) {
                    $article['content'] = $loaded;
                }
            }

            $article['manifest_key'] = $manifestKey;
            $article['slug'] = $finalSlug;

            $hydrated[] = $article;
        }

        return $hydrated;
    }

    public function exportBackup(): string
    {
        $backupDir = storage_path('app/blog-backups');
        File::ensureDirectoryExists($backupDir);

        $timestamp = now()->format('Y-m-d_His');
        $path = "{$backupDir}/blog-posts-{$timestamp}.json";

        $posts = BlogPost::query()->orderBy('id')->get();
        File::put($path, json_encode($posts->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    /**
     * @return array{created: int, updated: int, skipped: int, flagged: int, processed: int}
     */
    public function import(bool $dryRun = false): array
    {
        $articles = $this->filterArticles($this->loadManifest());
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $flagged = 0;

        DB::beginTransaction();

        try {
            foreach ($articles as $article) {
                $result = $this->importOne($article, $dryRun);

                match ($result['action']) {
                    'create' => $created++,
                    'update' => $updated++,
                    'skip' => $skipped++,
                    default => null,
                };

                if ($result['flagged']) {
                    $flagged++;
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'flagged' => $flagged,
            'processed' => count($articles),
        ];
    }

    public function resolveFinalSlug(array $article): string
    {
        $slug = (string) ($article['slug'] ?? '');

        return self::LEGACY_SLUG_MAP[$slug] ?? $slug;
    }

    private function findExistingPost(string $finalSlug): ?BlogPost
    {
        $existing = BlogPost::query()->where('slug', $finalSlug)->first();

        if ($existing) {
            return $existing;
        }

        foreach (self::LEGACY_SLUG_MAP as $legacy => $live) {
            if ($live === $finalSlug) {
                $legacyPost = BlogPost::query()->where('slug', $legacy)->first();
                if ($legacyPost) {
                    return $legacyPost;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $article
     * @return array{action: string, slug: string, flagged: bool, messages: list<string>}
     */
    private function importOne(array $article, bool $dryRun): array
    {
        $manifestKey = (string) ($article['manifest_key'] ?? $article['slug']);
        $finalSlug = $this->resolveFinalSlug($article);
        $messages = [];
        $flagged = false;

        if (array_key_exists($finalSlug, self::LEGACY_SLUG_MAP)) {
            $messages[] = 'Refusing to create record with deprecated legacy slug';
            $this->report['errors'][] = "{$manifestKey}: deprecated legacy slug target";
            $this->reportRecord($article, null, $finalSlug, 'error', BlogPost::STATUS_DRAFT, BlogPost::STATUS_DRAFT, null, null, $messages);

            return ['action' => 'skip', 'slug' => $finalSlug, 'flagged' => true, 'messages' => $messages];
        }

        $existing = $this->findExistingPost($finalSlug);
        $statusBefore = $existing?->status;
        $publishedAtBefore = $existing?->published_at;

        $payload = $this->buildPayload($article, $existing, $messages, $flagged);
        $statusAfter = $payload['status'];
        $publishedAtAfter = $payload['published_at'];

        if ($existing && in_array($existing->slug, array_keys(self::LEGACY_SLUG_MAP), true)) {
            $payload['slug'] = $finalSlug;
        }

        if ($existing) {
            if ($this->shouldSkip($existing, $article)) {
                $this->report['skip'][] = $finalSlug;
                $this->reportRecord($article, $existing, $finalSlug, 'skip', $statusBefore, $statusAfter, $publishedAtBefore, $publishedAtAfter, $messages);
                $this->appendFlags($finalSlug, $messages);

                return ['action' => 'skip', 'slug' => $finalSlug, 'flagged' => $flagged, 'messages' => $messages];
            }

            if (! $dryRun) {
                $existing->update($payload);
            }

            $this->report['update'][] = $finalSlug;
            $this->reportRecord($article, $existing, $finalSlug, 'update', $statusBefore, $statusAfter, $publishedAtBefore, $publishedAtAfter, $messages);
            $this->appendFlags($finalSlug, $messages);

            return ['action' => 'update', 'slug' => $finalSlug, 'flagged' => $flagged, 'messages' => $messages];
        }

        if (! $dryRun) {
            BlogPost::query()->create($payload);
        }

        $this->report['create'][] = $finalSlug;
        $this->reportRecord($article, null, $finalSlug, 'create', null, $statusAfter, null, $publishedAtAfter, $messages);
        $this->appendFlags($finalSlug, $messages);

        return ['action' => 'create', 'slug' => $finalSlug, 'flagged' => $flagged, 'messages' => $messages];
    }

    /**
     * @param  list<string>  $messages
     */
    private function reportRecord(
        array $article,
        ?BlogPost $existing,
        string $finalSlug,
        string $action,
        ?string $statusBefore,
        string $statusAfter,
        mixed $publishedAtBefore,
        mixed $publishedAtAfter,
        array $messages,
    ): void {
        $this->report['rows'][] = [
            'db_id' => $existing?->id,
            'slug' => $existing?->slug ?? $finalSlug,
            'manifest_key' => (string) ($article['manifest_key'] ?? $article['slug'] ?? ''),
            'final_slug' => $finalSlug,
            'action' => $action,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'published_at_before' => $publishedAtBefore instanceof Carbon ? $publishedAtBefore->toIso8601String() : $publishedAtBefore,
            'published_at_after' => $publishedAtAfter instanceof Carbon ? $publishedAtAfter->toIso8601String() : $publishedAtAfter,
            'word_count' => str_word_count(strip_tags((string) ($article['content'] ?? ''))),
            'excerpt_length' => strlen((string) ($article['excerpt'] ?? '')),
            'messages' => $messages,
        ];
    }

    /** @param  list<string>  $messages */
    private function appendFlags(string $slug, array $messages): void
    {
        if ($messages !== []) {
            $this->report['flag'] = array_merge(
                $this->report['flag'],
                array_map(fn ($m) => "{$slug}: {$m}", $messages)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  list<string>  $messages
     * @return array<string, mixed>
     */
    private function buildPayload(array $article, ?BlogPost $existing, array &$messages, bool &$flagged): array
    {
        $finalSlug = $this->resolveFinalSlug($article);
        $content = (string) ($article['content'] ?? '');

        if ($content === '') {
            $messages[] = 'Missing article body content';
            $flagged = true;
        }

        $wordCount = str_word_count(strip_tags($content));
        if ($wordCount > 0 && $wordCount < 900) {
            $messages[] = "Body below 900-word target ({$wordCount} words) — consider expanding before publish";
            $flagged = true;
        }

        $excerpt = (string) ($article['excerpt'] ?? '');
        $excerptLen = strlen($excerpt);
        $excerptInvalid = $excerptLen > 0 && ($excerptLen < 140 || $excerptLen > 165);
        if ($excerptInvalid) {
            $messages[] = "Excerpt length {$excerptLen} chars (target 140–165)";
            $flagged = true;
        }

        $image = $article['image'] ?? null;
        $imageUnsuitable = $this->isPlaceholderImage($image);
        if ($imageUnsuitable) {
            $messages[] = 'Hero image is placeholder or unsuitable — article stays draft until replaced';
            $flagged = true;
        }

        $requestedProducts = $this->normalizeStringList($article['related_product_slugs'] ?? []) ?? [];
        $requestedServices = $this->normalizeServiceSlugs($article['related_service_slugs'] ?? []);
        $requestedProjects = $this->normalizeStringList($article['related_project_slugs'] ?? []) ?? [];
        $requestedArticles = $this->normalizeRelatedArticleSlugs($article['related_article_slugs'] ?? []);

        [$productSlugs, $invalidProducts] = $this->resolveProductSlugs($requestedProducts);
        [$serviceSlugs, $invalidServices] = $this->resolveServiceSlugs($requestedServices);
        [$projectSlugs, $projectIds, $invalidProjects] = $this->resolveProjects($requestedProjects);
        [$articleSlugs, $invalidArticles] = $this->resolveArticleSlugs($requestedArticles);

        foreach ($invalidProducts as $invalid) {
            $messages[] = "Invalid product relationship: {$invalid}";
            $flagged = true;
        }
        foreach ($invalidServices as $invalid) {
            $messages[] = "Invalid service relationship: {$invalid}";
            $flagged = true;
        }
        foreach ($invalidProjects as $invalid) {
            $messages[] = "Invalid project relationship: {$invalid}";
            $flagged = true;
        }
        foreach ($invalidArticles as $invalid) {
            $messages[] = "Invalid related article: {$invalid}";
            $flagged = true;
        }

        $status = $this->resolveStatus($article, $existing, $flagged);
        $publishedAt = $this->resolvePublishedAt($article, $existing, $status);

        $faq = $this->normalizeFaq($article['faq'] ?? []);

        return [
            'title' => (string) $article['title'],
            'slug' => $finalSlug,
            'excerpt' => $excerpt,
            'content' => $content,
            'image' => $image,
            'hero_image_alt' => (string) ($article['hero_image_alt'] ?? $article['title'].' — Vyomika Atelier'),
            'hero_image_caption' => $article['hero_image_caption'] ?? null,
            'meta_title' => $article['meta_title'] ?? null,
            'meta_description' => $article['meta_description'] ?? null,
            'primary_keyword' => $article['primary_keyword'] ?? null,
            'og_title' => $article['og_title'] ?? ($article['meta_title'] ?? null),
            'og_description' => $article['og_description'] ?? ($article['meta_description'] ?? null),
            'og_image' => $article['og_image'] ?? $image,
            'canonical_url' => $article['canonical_url'] ?? null,
            'robots_index' => (bool) ($article['robots_index'] ?? true),
            'category' => (string) ($article['category'] ?? ''),
            'author' => (string) ($article['author'] ?? BlogPost::DEFAULT_AUTHOR),
            'gallery' => $article['gallery'] ?? null,
            'gallery_meta' => $article['gallery_meta'] ?? null,
            'related_product_slugs' => $productSlugs !== [] ? $productSlugs : null,
            'related_project_slugs' => $projectSlugs !== [] ? $projectSlugs : null,
            'related_project_ids' => $projectIds !== [] ? $projectIds : null,
            'related_service_slugs' => $serviceSlugs !== [] ? $serviceSlugs : null,
            'related_article_slugs' => $articleSlugs !== [] ? $articleSlugs : null,
            'faq' => $faq !== [] ? $faq : null,
            'is_featured' => (bool) ($article['is_featured'] ?? false),
            'is_active' => (bool) ($article['is_active'] ?? in_array($status, [
                BlogPost::STATUS_PUBLISHED,
                BlogPost::STATUS_SCHEDULED,
            ], true)),
            'status' => $status,
            'published_at' => $publishedAt,
            'content_updated_at' => now(),
            'reading_time_minutes' => BlogContent::readingTimeMinutes($content),
            'seo_source' => self::SOURCE,
        ];
    }

    private function shouldSkip(BlogPost $existing, array $article): bool
    {
        return (bool) ($article['skip_if_exists'] ?? false);
    }

    private function resolveStatus(array $article, ?BlogPost $existing, bool $flagged): string
    {
        $finalSlug = $this->resolveFinalSlug($article);

        if ($existing && (
            in_array($existing->slug, self::PRESERVE_PUBLISHED_SLUGS, true)
            || in_array($finalSlug, self::PRESERVE_PUBLISHED_SLUGS, true)
            || array_key_exists($existing->slug, self::LEGACY_SLUG_MAP)
        )) {
            if ($existing->status === BlogPost::STATUS_PUBLISHED || $existing->isPublished()) {
                return BlogPost::STATUS_PUBLISHED;
            }
        }

        if ($flagged) {
            return BlogPost::STATUS_DRAFT;
        }

        if (! empty($article['status'])) {
            return (string) $article['status'];
        }

        return BlogPost::STATUS_DRAFT;
    }

    private function resolvePublishedAt(array $article, ?BlogPost $existing, string $status): ?Carbon
    {
        if ($status === BlogPost::STATUS_DRAFT) {
            return null;
        }

        $finalSlug = $this->resolveFinalSlug($article);

        if ($existing?->published_at && (
            in_array($existing->slug, self::PRESERVE_PUBLISHED_SLUGS, true)
            || in_array($finalSlug, self::PRESERVE_PUBLISHED_SLUGS, true)
        )) {
            return $existing->published_at;
        }

        if (! empty($article['published_at'])) {
            return Carbon::parse($article['published_at']);
        }

        if ($status === BlogPost::STATUS_PUBLISHED) {
            return now()->subDays(7);
        }

        if ($status === BlogPost::STATUS_SCHEDULED) {
            return now()->addWeek();
        }

        return null;
    }

    /** @param  array<int, mixed>  $items */
    private function normalizeFaq(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = $item['question'] ?? $item['q'] ?? null;
            $answer = $item['answer'] ?? $item['a'] ?? null;

            if (filled($question) && filled($answer)) {
                $normalized[] = [
                    'question' => (string) $question,
                    'answer' => (string) $answer,
                ];
            }
        }

        return $normalized;
    }

    /** @param  array<int, mixed>  $items */
    private function normalizeStringList(array $items): ?array
    {
        $values = array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? $item : null,
            $items
        )));

        return $values !== [] ? $values : null;
    }

    /** @param  array<int, mixed>  $items */
    private function normalizeServiceSlugs(array $items): array
    {
        return array_values(array_filter(array_map(function ($item) {
            if (! is_string($item)) {
                return null;
            }

            return self::SERVICE_SLUG_ALIASES[$item] ?? $item;
        }, $items)));
    }

    /** @param  array<int, mixed>  $items */
    private function normalizeRelatedArticleSlugs(array $items): array
    {
        return array_values(array_filter(array_map(function ($item) {
            if (! is_string($item)) {
                return null;
            }

            return self::LEGACY_SLUG_MAP[$item] ?? $item;
        }, $items)));
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function resolveProductSlugs(array $slugs): array
    {
        $valid = [];
        $invalid = [];

        foreach ($this->normalizeStringList($slugs) ?? [] as $slug) {
            if (Product::query()->where('slug', $slug)->exists()) {
                $valid[] = $slug;
            } else {
                $invalid[] = $slug;
            }
        }

        return [$valid, $invalid];
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function resolveServiceSlugs(array $slugs): array
    {
        $valid = [];
        $invalid = [];

        foreach ($this->normalizeStringList($slugs) ?? [] as $slug) {
            $normalized = self::SERVICE_SLUG_ALIASES[$slug] ?? $slug;

            if (Service::query()->where('slug', $normalized)->exists()
                || in_array($normalized, self::LANDING_SERVICE_SLUGS, true)) {
                $valid[] = $normalized;
            } else {
                $invalid[] = $slug;
            }
        }

        return [$valid, $invalid];
    }

    /** @return array{0: list<string>, 1: list<int>, 2: list<string>} */
    private function resolveProjects(array $slugs): array
    {
        $validSlugs = [];
        $ids = [];
        $invalid = [];

        foreach ($this->normalizeStringList($slugs) ?? [] as $slug) {
            $catalog = collect(require database_path('data/projects-catalog.php'));
            $catalogMatch = $catalog->firstWhere('slug', $slug);

            if ($catalogMatch) {
                $validSlugs[] = $slug;
                $dbProject = Project::query()
                    ->where('project_name', $catalogMatch['title'])
                    ->first();
                if ($dbProject) {
                    $ids[] = $dbProject->id;
                }
            } else {
                $invalid[] = $slug;
            }
        }

        return [$validSlugs, $ids, $invalid];
    }

    /** @return list<string> */
    private function manifestFinalSlugs(): array
    {
        if ($this->manifestFinalSlugs === null) {
            $this->manifestFinalSlugs = collect(require $this->contentPath.'/manifest.php')
                ->map(fn (array $a) => $this->resolveFinalSlug($a))
                ->all();
        }

        return $this->manifestFinalSlugs;
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function resolveArticleSlugs(array $slugs): array
    {
        $valid = [];
        $invalid = [];
        $manifestSlugs = $this->manifestFinalSlugs();

        foreach ($this->normalizeStringList($slugs) ?? [] as $slug) {
            $normalized = self::LEGACY_SLUG_MAP[$slug] ?? $slug;

            if (BlogPost::query()->where('slug', $normalized)->exists()
                || in_array($normalized, $manifestSlugs, true)) {
                $valid[] = $normalized;
            } else {
                $invalid[] = $slug;
            }
        }

        return [$valid, $invalid];
    }

    private function isPlaceholderImage(?string $image): bool
    {
        if ($image === null || $image === '') {
            return true;
        }

        return Str::contains($image, [
            'unsplash.com',
            'placeholder',
            '/images/exhibitions/',
            '.svg',
        ]);
    }
}
