<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;

/**
 * Content for the homepage rows that are not product listings: the collection
 * row, the how-it-works steps, the quote CTA, and the review cards.
 */
class HomeSections
{
    /**
     * Cards for "Shop by collection". The categories table owns the label,
     * order, and link; config supplies the artwork and caption it has no
     * column for. Falls back to the canonical shop slugs so the row still
     * renders on a database-less first deploy.
     *
     * @return list<array{slug: string, label: string, caption: string, image: ?string, url: string}>
     */
    public static function collectionCards(?int $limit = null): array
    {
        $config = SiteContent::get('collection_row', []);
        $cardConfig = is_array($config['cards'] ?? null) ? $config['cards'] : [];
        $limit ??= (int) ($config['limit'] ?? 4);

        $cards = [];

        foreach (self::collectionCategories() as $slug => $category) {
            $meta = is_array($cardConfig[$slug] ?? null) ? $cardConfig[$slug] : [];

            $cards[] = [
                'slug' => $slug,
                'label' => $category?->name ?: StorefrontRoutes::shopCategoryLabel($slug),
                'caption' => (string) ($meta['caption'] ?? ''),
                'image' => self::cardImage($category, $meta['image'] ?? null),
                'url' => $category?->storefrontUrl() ?? StorefrontRoutes::shopCategoryUrl($slug),
            ];

            if ($limit > 0 && count($cards) >= $limit) {
                break;
            }
        }

        return $cards;
    }

    /**
     * Shop categories in the order the row should display them, keyed by slug.
     * Only slugs with a real shop landing page are eligible, so a card can
     * never link to a 404.
     *
     * @return array<string, ?Category>
     */
    private static function collectionCategories(): array
    {
        $eligible = StorefrontRoutes::shopCategorySlugs();

        if (! Schema::hasTable('categories')) {
            return array_fill_keys($eligible, null);
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->whereIn('slug', $eligible)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('slug');

        if ($categories->isEmpty()) {
            // An empty categories table means nothing has been synced yet, so
            // the canonical slugs are still the best preview. A populated table
            // with everything deactivated is an editorial choice: respect it.
            return Category::query()->exists() ? [] : array_fill_keys($eligible, null);
        }

        return $categories->all();
    }

    /** Category artwork wins, then the config still-life, then any product photo. */
    private static function cardImage(?Category $category, ?string $configImage): ?string
    {
        if ($category && filled($category->image)) {
            $resolved = MediaUrl::resolve($category->image);

            if ($resolved) {
                return $resolved;
            }
        }

        if (filled($configImage)) {
            return MediaUrl::resolve($configImage) ?? $configImage;
        }

        if (! $category || ! Schema::hasTable('products')) {
            return null;
        }

        return Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->whereNotNull('image')
            ->orderBy('id')
            ->first()
            ?->imageUrl();
    }

    /** @return array<string, mixed> */
    public static function howItWorks(): array
    {
        $section = SiteContent::get('how_it_works', []);

        return [
            'title' => $section['title'] ?? 'How It Works',
            'subtitle' => $section['subtitle'] ?? '',
            'steps' => array_values(array_filter(
                is_array($section['steps'] ?? null) ? $section['steps'] : [],
                fn ($step) => is_array($step) && filled($step['title'] ?? null)
            )),
        ];
    }

    /** @return array<string, mixed> */
    public static function quoteCta(): array
    {
        $cta = SiteContent::get('quote_cta', []);

        if (! is_array($cta) || ! filled($cta['title'] ?? null)) {
            return [];
        }

        $cta['image'] = MediaUrl::resolve($cta['image'] ?? null) ?? ($cta['image'] ?? null);
        $cta['points'] = array_values(array_filter(
            is_array($cta['points'] ?? null) ? $cta['points'] : [],
            fn ($point) => filled($point)
        ));

        return $cta;
    }

    /**
     * Review cards. Ratings default to 5 so a testimonial saved without one
     * still renders a full star row instead of an empty gap.
     *
     * @return array{title: string, subtitle: string, rating_label: ?string, cards: list<array<string, mixed>>}
     */
    public static function reviews(int $limit = 3): array
    {
        $meta = SiteContent::get('reviews', []);

        $cards = collect(SiteContent::testimonials())
            ->filter(fn ($item) => is_array($item) && filled($item['quote'] ?? null))
            ->take($limit)
            ->map(fn (array $item) => [
                'quote' => $item['quote'],
                'client' => $item['client'] ?? '',
                'role' => $item['role'] ?? '',
                'location' => $item['location'] ?? '',
                'rating' => self::clampRating($item['rating'] ?? 5),
                'verified' => str_contains(strtolower((string) ($item['role'] ?? '')), 'verified'),
            ])
            ->values()
            ->all();

        return [
            'title' => $meta['title'] ?? 'What Our Customers Say',
            'subtitle' => $meta['subtitle'] ?? '',
            'rating_label' => $meta['rating_label'] ?? null,
            'cards' => $cards,
        ];
    }

    private static function clampRating(mixed $rating): int
    {
        return max(1, min(5, (int) $rating));
    }
}
