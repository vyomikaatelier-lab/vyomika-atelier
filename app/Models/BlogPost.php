<?php

namespace App\Models;

use App\Support\BlogContent;
use App\Support\MediaUrl;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SCHEDULED = 'scheduled';

    public const DEFAULT_AUTHOR = 'Vyomika Atelier Editorial Team';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'image',
        'hero_image_alt',
        'hero_image_caption',
        'meta_title',
        'meta_description',
        'category',
        'author',
        'reading_time_minutes',
        'gallery',
        'gallery_meta',
        'related_product_slugs',
        'related_project_slugs',
        'related_project_ids',
        'related_service_slugs',
        'related_article_slugs',
        'canonical_url',
        'faq',
        'is_featured',
        'published_at',
        'content_updated_at',
        'is_active',
        'status',
        'seo_source',
        'og_image',
        'og_title',
        'og_description',
        'primary_keyword',
        'robots_index',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'content_updated_at' => 'datetime',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'robots_index' => 'boolean',
            'gallery' => 'array',
            'gallery_meta' => 'array',
            'related_product_slugs' => 'array',
            'related_project_slugs' => 'array',
            'related_project_ids' => 'array',
            'related_service_slugs' => 'array',
            'related_article_slugs' => 'array',
            'faq' => 'array',
        ];
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?? ($this->title.' | Vyomika Atelier');
    }

    public function seoDescription(): string
    {
        return $this->meta_description ?? Str::limit(strip_tags($this->excerpt ?? ''), 160, '');
    }

    public function categoryLabel(): ?string
    {
        return BlogContent::categoryLabel($this->category);
    }

    public function categorySlug(): ?string
    {
        return BlogContent::categorySlug($this->category);
    }

    public function readingTime(): int
    {
        return BlogContent::readingTimeMinutes($this->content);
    }

    public function heroAlt(): string
    {
        return $this->hero_image_alt ?: ($this->title.' — Vyomika Atelier');
    }

    public function canonicalUrl(): string
    {
        return $this->canonical_url ?: route('blog.show', $this->slug);
    }

    public function isPublished(): bool
    {
        if (! $this->is_active || $this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            || ($this->status === self::STATUS_PUBLISHED && $this->published_at?->isFuture());
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->isPublished();
    }

    /** @return array<int, array{question: string, answer: string}> */
    public function faqItems(): array
    {
        return is_array($this->faq) ? $this->faq : [];
    }

    /** @return array<int, array{question: string, answer: string}> */
    public function validFaqItems(): array
    {
        return array_values(array_filter($this->faqItems(), function (array $item) {
            return filled($item['question'] ?? null) && filled($item['answer'] ?? null);
        }));
    }

    public function hasGallery(): bool
    {
        return count($this->galleryItems()) > 0;
    }

    /** @return array<int, array{path: string, url: ?string, alt: string, caption: ?string}> */
    public function galleryItems(): array
    {
        $paths = is_array($this->gallery) ? array_values($this->gallery) : [];
        $meta = is_array($this->gallery_meta) ? $this->gallery_meta : [];
        $items = [];

        foreach ($paths as $index => $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $itemMeta = is_array($meta[$index] ?? null) ? $meta[$index] : [];
            $items[] = [
                'path' => $path,
                'url' => MediaUrl::resolve($path),
                'alt' => filled($itemMeta['alt'] ?? null)
                    ? (string) $itemMeta['alt']
                    : ($this->title.' — gallery image '.($index + 1)),
                'caption' => filled($itemMeta['caption'] ?? null) ? (string) $itemMeta['caption'] : null,
            ];
        }

        return $items;
    }

    /** @return array<int, string> */
    public function relatedProductSlugs(): array
    {
        return is_array($this->related_product_slugs) ? $this->related_product_slugs : [];
    }

    /** @return array<int, string> */
    public function relatedProjectSlugs(): array
    {
        return is_array($this->related_project_slugs) ? $this->related_project_slugs : [];
    }

    /** @return array<int, int> */
    public function relatedProjectIds(): array
    {
        return is_array($this->related_project_ids) ? $this->related_project_ids : [];
    }

    /** @return array<int, string> */
    public function relatedServiceSlugs(): array
    {
        return is_array($this->related_service_slugs) ? $this->related_service_slugs : [];
    }

    /** @return array<int, string> */
    public function relatedArticleSlugs(): array
    {
        return is_array($this->related_article_slugs) ? $this->related_article_slugs : [];
    }

    public function imageUrl(): ?string
    {
        return MediaUrl::resolve($this->image);
    }

    /** @return array<int, string> */
    public function galleryUrls(): array
    {
        return array_values(array_filter(array_map(
            fn (array $item) => $item['url'],
            $this->galleryItems()
        )));
    }

    public function lastUpdatedAt(): ?Carbon
    {
        if ($this->content_updated_at !== null
            && $this->content_updated_at->gt($this->published_at ?? $this->created_at ?? now())) {
            return $this->content_updated_at;
        }

        return null;
    }

    /** @return array<int, array{id: string, text: string}> */
    public function tableOfContents(): array
    {
        if (! filled($this->content)) {
            return [];
        }

        preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', (string) $this->content, $matches, PREG_SET_ORDER);
        $items = [];

        foreach ($matches as $index => $match) {
            $text = trim(strip_tags($match[1]));
            if ($text === '') {
                continue;
            }

            $items[] = [
                'id' => 'section-'.($index + 1),
                'text' => $text,
            ];
        }

        return $items;
    }

    public function showTableOfContents(): bool
    {
        return count($this->tableOfContents()) >= 3;
    }
}
