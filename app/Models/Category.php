<?php

namespace App\Models;

use App\Support\ProductCatalog;
use App\Support\StorefrontRoutes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'section',
        'description',
        'image',
        'meta_title',
        'meta_description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function resolvedSection(): ?string
    {
        if ($this->section !== null && in_array($this->section, Product::SECTIONS, true)) {
            return $this->section;
        }

        return ProductCatalog::sectionForCategorySlug($this->slug, $this);
    }

    public function storefrontUrl(): ?string
    {
        $section = $this->resolvedSection();

        if ($section === Product::SECTION_RAILINGS) {
            return route('railings.index');
        }

        if ($section === Product::SECTION_SHOP) {
            if (StorefrontRoutes::isShopCategory($this->slug)) {
                return StorefrontRoutes::shopCategoryUrl($this->slug);
            }

            return route('shop.index');
        }

        if ($section === Product::SECTION_STUDIO) {
            if (in_array($this->slug, ['partitions', 'fluted-panels', 'room-dividers'], true)) {
                return route('studio.show', 'pvd-partitions');
            }

            return route('studio.index');
        }

        return null;
    }

    public function storefrontLinkLabel(): string
    {
        $section = $this->resolvedSection();

        return match ($section) {
            Product::SECTION_SHOP => StorefrontRoutes::isShopCategory($this->slug)
                ? 'Shop › '.StorefrontRoutes::shopCategoryLabel($this->slug)
                : 'Shop',
            Product::SECTION_STUDIO => in_array($this->slug, ['partitions', 'fluted-panels', 'room-dividers'], true)
                ? 'Studio › PVD Partitions'
                : 'Studio',
            Product::SECTION_RAILINGS => 'Railings',
            default => 'Not linked',
        };
    }
}
