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

        if ($section === Product::SECTION_RAILINGS || $this->slug === 'railings') {
            return route('railings.index');
        }

        if ($section === Product::SECTION_SHOP) {
            if (StorefrontRoutes::isShopCategory($this->slug)) {
                return StorefrontRoutes::shopCategoryUrl($this->slug);
            }

            if (in_array($this->slug, ['home-decor', 'metal-furniture'], true)) {
                return StorefrontRoutes::shopCategoryUrl('bespoke-metal-furniture');
            }

            return route('shop.index');
        }

        if ($section === Product::SECTION_STUDIO) {
            $studioUrl = StorefrontRoutes::studioUrlForService($this->slug);
            if ($studioUrl) {
                return route('studio.show', $studioUrl);
            }

            // Legacy archived partition taxonomy.
            if (in_array($this->slug, ['fluted-panels', 'room-dividers'], true)) {
                return route('studio.show', 'pvd-partitions');
            }

            if ($this->slug === 'metal-furniture') {
                return StorefrontRoutes::shopCategoryUrl('bespoke-metal-furniture');
            }

            return route('studio.index');
        }

        return null;
    }

    public function storefrontLinkLabel(): string
    {
        $section = $this->resolvedSection();

        if ($section === Product::SECTION_SHOP) {
            if (StorefrontRoutes::isShopCategory($this->slug)) {
                return 'Shop › '.StorefrontRoutes::shopCategoryLabel($this->slug);
            }

            return 'Shop';
        }

        if ($section === Product::SECTION_STUDIO) {
            $serviceLabel = StorefrontRoutes::studioServiceLabels()[$this->slug] ?? null;
            if ($serviceLabel) {
                return 'Studio › '.$serviceLabel;
            }

            if (in_array($this->slug, ['partitions', 'fluted-panels', 'room-dividers'], true)) {
                return 'Studio › PVD Partitions';
            }

            return 'Studio';
        }

        if ($section === Product::SECTION_RAILINGS || $this->slug === 'railings') {
            return 'Railings';
        }

        return 'Not linked';
    }
}
