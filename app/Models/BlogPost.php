<?php

namespace App\Models;

use App\Support\BlogContent;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'image',
        'hero_image_alt',
        'meta_title',
        'meta_description',
        'category',
        'author',
        'reading_time_minutes',
        'gallery',
        'related_product_slugs',
        'related_project_slugs',
        'related_service_slugs',
        'canonical_url',
        'faq',
        'is_featured',
        'published_at',
        'is_active',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'gallery' => 'array',
            'related_product_slugs' => 'array',
            'related_project_slugs' => 'array',
            'related_service_slugs' => 'array',
            'faq' => 'array',
        ];
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?? ($this->title . ' | Vyomika Atelier LLP');
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
        return BlogContent::readingTimeMinutes($this->content, $this->reading_time_minutes);
    }

    public function heroAlt(): string
    {
        return $this->hero_image_alt ?: ($this->title . ' — Vyomika Atelier LLP');
    }

    public function canonicalUrl(): string
    {
        return $this->canonical_url ?: route('blog.show', $this->slug);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->is_active;
    }

    /** @return array<int, array{question: string, answer: string}> */
    public function faqItems(): array
    {
        return is_array($this->faq) ? $this->faq : [];
    }

    public function hasGallery(): bool
    {
        return is_array($this->gallery) && count($this->gallery) > 0;
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

    public function imageUrl(): ?string
    {
        return MediaUrl::resolve($this->image);
    }

    /** @return array<int, string> */
    public function galleryUrls(): array
    {
        return MediaUrl::resolveMany($this->gallery);
    }
}
