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
        return self::all()['meta_title'] ?? 'Blog — Vyomika Atelier';
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

    /** Categories that have at least one publicly visible post. */
    /** @return array<int, array{slug: string, label: string, count: int}> */
    public static function categoriesWithPublishedPosts(): array
    {
        $counts = self::allPosts()
            ->groupBy(fn (BlogPost $post) => self::categorySlug($post->category))
            ->map->count();

        return collect(self::categories())
            ->map(function (array $cat) use ($counts) {
                $count = (int) ($counts[$cat['slug']] ?? 0);

                return [...$cat, 'count' => $count];
            })
            ->filter(fn (array $cat) => $cat['count'] > 0)
            ->values()
            ->all();
    }

    public static function usesDatabase(): bool
    {
        if (! Schema::hasTable('blog_posts')) {
            return false;
        }

        return BlogPost::query()->exists();
    }

    public static function publicQuery(): Builder
    {
        return BlogPost::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('status', BlogPost::STATUS_PUBLISHED)
                    ->orWhere(function ($query) {
                        $query->where('status', BlogPost::STATUS_SCHEDULED)
                            ->whereNotNull('published_at')
                            ->where('published_at', '<=', now());
                    })
                    ->orWhere(function ($query) {
                        $query->whereNull('status')
                            ->whereNotNull('published_at')
                            ->where('published_at', '<=', now());
                    });
            })
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public static function query(): Builder
    {
        if (self::usesDatabase()) {
            return self::publicQuery()->latest('published_at');
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
            return self::publicQuery()->where('slug', $slug)->first();
        }

        $data = collect(self::all()['posts'] ?? [])
            ->firstWhere('slug', $slug);

        return $data ? self::hydrateFromConfig($data) : null;
    }

    public static function featuredPost(?string $categorySlug = null): ?BlogPost
    {
        $posts = self::allPosts();

        if ($categorySlug) {
            $label = self::categoryLabel($categorySlug);
            $posts = $posts->filter(function (BlogPost $post) use ($categorySlug, $label) {
                $slug = self::categorySlug($post->category);

                return $slug === $categorySlug || $post->category === $label;
            });
        }

        if (self::usesDatabase()) {
            $featured = $posts->firstWhere('is_featured', true);

            return $featured ?? $posts->sortByDesc(fn (BlogPost $p) => $p->published_at?->timestamp ?? 0)->first();
        }

        $data = collect(self::all()['posts'] ?? [])
            ->firstWhere('is_featured', true)
            ?? (self::all()['posts'][0] ?? null);

        return $data ? self::hydrateFromConfig($data) : null;
    }

    public static function paginate(
        ?string $categorySlug = null,
        ?string $search = null,
        int $perPage = 9,
        ?BlogPost $exclude = null
    ): LengthAwarePaginator {
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

        if ($search !== null && trim($search) !== '') {
            $needle = Str::lower(trim($search));
            $posts = $posts->filter(function (BlogPost $post) use ($needle) {
                $haystack = Str::lower(implode(' ', [
                    $post->title,
                    strip_tags((string) $post->excerpt),
                    strip_tags((string) $post->content),
                ]));

                return str_contains($haystack, $needle);
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
        $selected = collect($post->relatedArticleSlugs())
            ->map(fn (string $slug) => self::findBySlug($slug))
            ->filter()
            ->reject(fn (BlogPost $p) => $p->slug === $post->slug)
            ->take($limit);

        if ($selected->count() >= $limit) {
            return $selected->values();
        }

        $fallback = self::allPosts()
            ->reject(fn (BlogPost $p) => $p->slug === $post->slug)
            ->reject(fn (BlogPost $p) => $selected->contains(fn (BlogPost $s) => $s->slug === $p->slug))
            ->sortByDesc(function (BlogPost $p) use ($post) {
                $sameCategory = self::categorySlug($p->category) === self::categorySlug($post->category) ? 10 : 0;

                return $sameCategory + ($p->published_at?->timestamp ?? 0) / 1_000_000_000;
            })
            ->take($limit - $selected->count());

        return $selected->merge($fallback)->values();
    }

    /** @return array{prev: ?BlogPost, next: ?BlogPost} */
    public static function adjacentPosts(BlogPost $post): array
    {
        $ordered = self::allPosts()
            ->sortByDesc(fn (BlogPost $p) => $p->published_at?->timestamp ?? 0)
            ->values();

        $index = $ordered->search(fn (BlogPost $p) => $p->slug === $post->slug);

        if ($index === false) {
            return ['prev' => null, 'next' => null];
        }

        return [
            'prev' => $index > 0 ? $ordered[$index - 1] : null,
            'next' => $index < $ordered->count() - 1 ? $ordered[$index + 1] : null,
        ];
    }

    public static function readingTimeMinutes(?string $content, ?int $stored = null): int
    {
        unset($stored);

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
            'author' => $data['author'] ?? BlogPost::DEFAULT_AUTHOR,
            'gallery' => $data['gallery'] ?? null,
            'related_product_slugs' => $data['related_product_slugs'] ?? null,
            'related_project_slugs' => $data['related_project_slugs'] ?? null,
            'related_article_slugs' => $data['related_article_slugs'] ?? null,
            'faq' => $data['faq'] ?? null,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_active' => true,
            'status' => BlogPost::STATUS_PUBLISHED,
        ]);

        if (! empty($data['published_at'])) {
            $post->published_at = Carbon::parse($data['published_at']);
        }

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
                    'reading_time_minutes' => self::readingTimeMinutes($post['content'] ?? ''),
                    'category_slug' => self::categorySlug($post['category'] ?? ''),
                ];
            })->values()->all(),
        ];
    }
}
