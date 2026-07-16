<?php

namespace App\Support;

use App\Models\BlogPost;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BlogContent
{
    public static function all(): array
    {
        return config('blog', []);
    }

    public static function indexMeta(): array
    {
        return self::all()['index'] ?? [];
    }

    public static function metaTitle(): string
    {
        return self::all()['meta_title'] ?? 'Blog — Vyomika Atelier LLP';
    }

    public static function metaDescription(): string
    {
        return self::all()['meta_description'] ?? '';
    }

    /** @return array<int, array{slug: string, label: string}> */
    public static function categories(): array
    {
        return self::all()['categories'] ?? [];
    }

    public static function categoryLabel(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return null;
        }

        foreach (self::categories() as $cat) {
            if (($cat['slug'] ?? '') === $category || ($cat['label'] ?? '') === $category) {
                return $cat['label'];
            }
        }

        return $category;
    }

    public static function categorySlug(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return null;
        }

        foreach (self::categories() as $cat) {
            if (($cat['slug'] ?? '') === $category || ($cat['label'] ?? '') === $category) {
                return $cat['slug'];
            }
        }

        return Str::slug($category);
    }

    public static function usesDatabase(): bool
    {
        if (! Schema::hasTable('blog_posts')) {
            return false;
        }

        return BlogPost::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->exists();
    }

    public static function query(): Builder
    {
        if (self::usesDatabase()) {
            return BlogPost::query()
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhere('status', 'published');
                })
                ->latest('published_at');
        }

        return BlogPost::query()->whereRaw('0 = 1');
    }

    /** @return Collection<int, BlogPost> */
    public static function allPosts(): Collection
    {
        if (self::usesDatabase()) {
            return self::query()->get();
        }

        return collect(self::all()['posts'] ?? [])
            ->map(fn (array $data) => self::hydrateFromConfig($data));
    }

    public static function findBySlug(string $slug): ?BlogPost
    {
        if (self::usesDatabase()) {
            return BlogPost::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhere('status', 'published');
                })
                ->first();
        }

        $data = collect(self::all()['posts'] ?? [])
            ->firstWhere('slug', $slug);

        return $data ? self::hydrateFromConfig($data) : null;
    }

    public static function featuredPost(): ?BlogPost
    {
        if (self::usesDatabase()) {
            return self::query()->where('is_featured', true)->first()
                ?? self::query()->first();
        }

        $data = collect(self::all()['posts'] ?? [])
            ->firstWhere('is_featured', true)
            ?? (self::all()['posts'][0] ?? null);

        return $data ? self::hydrateFromConfig($data) : null;
    }

    public static function paginate(?string $categorySlug = null, int $perPage = 9, ?BlogPost $exclude = null): LengthAwarePaginator
    {
        $posts = self::allPosts();

        if ($exclude) {
            $posts = $posts->reject(fn (BlogPost $p) => $p->slug === $exclude->slug);
        }

        if ($categorySlug) {
            $label = self::categoryLabel($categorySlug);
            $posts = $posts->filter(function (BlogPost $post) use ($categorySlug, $label) {
                $slug = self::categorySlug($post->category);

                return $slug === $categorySlug || $post->category === $label;
            });
        }

        $posts = $posts->sortByDesc(fn (BlogPost $p) => $p->published_at?->timestamp ?? 0)->values();

        $page = max(1, (int) request()->query('page', 1));
        $total = $posts->count();
        $items = $posts->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /** @return Collection<int, BlogPost> */
    public static function relatedPosts(BlogPost $post, int $limit = 3): Collection
    {
        return self::allPosts()
            ->reject(fn (BlogPost $p) => $p->slug === $post->slug)
            ->sortByDesc(function (BlogPost $p) use ($post) {
                $sameCategory = self::categorySlug($p->category) === self::categorySlug($post->category) ? 10 : 0;

                return $sameCategory + ($p->published_at?->timestamp ?? 0) / 1_000_000_000;
            })
            ->take($limit)
            ->values();
    }

    public static function readingTimeMinutes(?string $content, ?int $stored = null): int
    {
        if ($stored !== null && $stored > 0) {
            return $stored;
        }

        $text = trim(strip_tags($content ?? ''));
        if ($text === '') {
            return 1;
        }

        $words = str_word_count($text);

        return max(1, (int) ceil($words / 200));
    }

    public static function hydrateFromConfig(array $data): BlogPost
    {
        $post = new BlogPost([
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? '',
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'] ?? null,
            'image' => $data['image'] ?? null,
            'hero_image_alt' => $data['hero_image_alt'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'category' => $data['category'] ?? null,
            'author' => $data['author'] ?? 'Vyomika Atelier LLP',
            'reading_time_minutes' => $data['reading_time_minutes'] ?? null,
            'gallery' => $data['gallery'] ?? null,
            'related_product_slugs' => $data['related_product_slugs'] ?? null,
            'related_project_slugs' => $data['related_project_slugs'] ?? null,
            'faq' => $data['faq'] ?? null,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_active' => true,
        ]);

        if (! empty($data['published_at'])) {
            $post->published_at = Carbon::parse($data['published_at']);
        }

        $post->reading_time_minutes = self::readingTimeMinutes(
            $post->content,
            $post->reading_time_minutes
        );

        return $post;
    }

    /** @return array<string, mixed> */
    public static function exportForPreview(): array
    {
        $config = self::all();

        return [
            'meta_title' => $config['meta_title'] ?? '',
            'meta_description' => $config['meta_description'] ?? '',
            'index' => $config['index'] ?? [],
            'categories' => $config['categories'] ?? [],
            'posts' => collect($config['posts'] ?? [])->map(function (array $post) {
                $published = ! empty($post['published_at'])
                    ? Carbon::parse($post['published_at'])->format('j F Y')
                    : '';

                return [
                    ...$post,
                    'date' => $published,
                    'reading_time_minutes' => self::readingTimeMinutes(
                        $post['content'] ?? '',
                        $post['reading_time_minutes'] ?? null
                    ),
                    'category_slug' => self::categorySlug($post['category'] ?? ''),
                ];
            })->values()->all(),
        ];
    }
}
