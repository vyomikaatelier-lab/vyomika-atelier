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

    /** @var list<string> */
    public const PRESERVE_PUBLISHED_SLUGS = [
        'glass-partitions-open-plan-without-compromise',
        'pvd-coating-explained-durable-metal-finishes',
        'why-corten-steel-is-perfect-for-modern-facades',
    ];

    /** @var array<string, mixed> */
    private array $report = [
        'create' => [],
        'update' => [],
        'skip' => [],
        'flag' => [],
        'errors' => [],
    ];

    public function __construct(
        private readonly string $contentPath,
    ) {}

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
    private function hydrateArticles(array $articles): array
    {
        $hydrated = [];

        foreach ($articles as $article) {
            $slug = (string) ($article['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $contentFile = $this->contentPath.'/articles/'.$slug.'.php';
            if (File::exists($contentFile)) {
                $loaded = require $contentFile;
                if (is_array($loaded)) {
                    $article = array_merge($article, $loaded);
                } elseif (is_string($loaded)) {
                    $article['content'] = $loaded;
                }
            }

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
     * @return array{created: int, updated: int, skipped: int, flagged: int}
     */
    public function import(bool $dryRun = false): array
    {
        $articles = $this->loadManifest();
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

        return compact('created', 'updated', 'skipped', 'flagged');
    }

    /**
     * @param  array<string, mixed>  $article
     * @return array{action: string, slug: string, flagged: bool, messages: list<string>}
     */
    private function importOne(array $article, bool $dryRun): array
    {
        $slug = (string) $article['slug'];
        $messages = [];
        $flagged = false;

        $existing = BlogPost::query()->where('slug', $slug)->first();
        $payload = $this->buildPayload($article, $existing, $messages, $flagged);

        if ($existing) {
            if ($this->shouldSkip($existing, $article)) {
                $this->report['skip'][] = $slug;
                $this->report['flag'] = array_merge($this->report['flag'], array_map(fn ($m) => "{$slug}: {$m}", $messages));

                return ['action' => 'skip', 'slug' => $slug, 'flagged' => $flagged, 'messages' => $messages];
            }

            if (! $dryRun) {
                $existing->update($payload);
            }

            $this->report['update'][] = $slug;
            if ($messages !== []) {
                $this->report['flag'] = array_merge($this->report['flag'], array_map(fn ($m) => "{$slug}: {$m}", $messages));
            }

            return ['action' => 'update', 'slug' => $slug, 'flagged' => $flagged, 'messages' => $messages];
        }

        if (! $dryRun) {
            BlogPost::query()->create($payload);
        }

        $this->report['create'][] = $slug;
        if ($messages !== []) {
            $this->report['flag'] = array_merge($this->report['flag'], array_map(fn ($m) => "{$slug}: {$m}", $messages));
        }

        return ['action' => 'create', 'slug' => $slug, 'flagged' => $flagged, 'messages' => $messages];
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  list<string>  $messages
     * @return array<string, mixed>
     */
    private function buildPayload(array $article, ?BlogPost $existing, array &$messages, bool &$flagged): array
    {
        $slug = (string) $article['slug'];
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
        if ($excerptLen > 0 && ($excerptLen < 140 || $excerptLen > 165)) {
            $messages[] = "Excerpt length {$excerptLen} chars (target 140–165)";
        }

        $image = $article['image'] ?? null;
        if ($this->isPlaceholderImage($image)) {
            $messages[] = 'Hero image is placeholder or unsuitable — replace before publishing';
            $flagged = true;
        }

        $requestedProducts = $this->normalizeStringList($article['related_product_slugs'] ?? []) ?? [];
        $requestedServices = $this->normalizeStringList($article['related_service_slugs'] ?? []) ?? [];
        $requestedProjects = $this->normalizeStringList($article['related_project_slugs'] ?? []) ?? [];

        [$productSlugs, $invalidProducts] = $this->resolveProductSlugs($requestedProducts);
        [$serviceSlugs, $invalidServices] = $this->resolveServiceSlugs($requestedServices);
        [$projectSlugs, $projectIds, $invalidProjects] = $this->resolveProjects($requestedProjects);

        foreach ($invalidProducts as $invalid) {
            $messages[] = "Product slug not yet in database: {$invalid} (stored for when catalogue syncs)";
        }
        foreach ($invalidServices as $invalid) {
            $messages[] = "Service slug not yet in database: {$invalid} (stored for when catalogue syncs)";
        }
        foreach ($invalidProjects as $invalid) {
            $messages[] = "Project slug not yet in database: {$invalid} (stored for when projects seed)";
        }

        $productSlugs = $requestedProducts !== [] ? $requestedProducts : $productSlugs;
        $serviceSlugs = $requestedServices !== [] ? $requestedServices : $serviceSlugs;
        $projectSlugs = $requestedProjects !== [] ? $requestedProjects : $projectSlugs;

        $status = $this->resolveStatus($article, $existing, $flagged);
        $publishedAt = $this->resolvePublishedAt($article, $existing, $status);

        $faq = $this->normalizeFaq($article['faq'] ?? []);

        return [
            'title' => (string) $article['title'],
            'slug' => $slug,
            'excerpt' => (string) ($article['excerpt'] ?? ''),
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
            'related_article_slugs' => $this->normalizeStringList($article['related_article_slugs'] ?? []),
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
        if ($existing?->isPublished() && ! in_array($existing->slug, self::PRESERVE_PUBLISHED_SLUGS, true)) {
            // Preserve any other published posts not in our library
            return BlogPost::STATUS_PUBLISHED;
        }

        if ($existing && in_array($existing->slug, self::PRESERVE_PUBLISHED_SLUGS, true)) {
            return BlogPost::STATUS_PUBLISHED;
        }

        if (in_array($article['slug'], self::PRESERVE_PUBLISHED_SLUGS, true) && ! $flagged) {
            return BlogPost::STATUS_PUBLISHED;
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

        if ($existing?->published_at && in_array($existing->slug, self::PRESERVE_PUBLISHED_SLUGS, true)) {
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
            if (Service::query()->where('slug', $slug)->exists()) {
                $valid[] = $slug;
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
            $project = Project::query()->where('project_name', 'like', '%'.$slug.'%')->first();

            // Projects table no longer has slug column after simplification — match by related slugs from catalog if stored elsewhere
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
