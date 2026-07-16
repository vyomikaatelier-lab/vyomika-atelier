<?php

namespace App\Models;

use App\Support\ServiceGallery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name', 'slug', 'summary', 'content', 'image',
        'has_calculator', 'has_designs', 'lead_form', 'rate_per_sqft',
        'is_active', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'has_calculator' => 'boolean',
            'has_designs' => 'boolean',
            'is_active' => 'boolean',
            'rate_per_sqft' => 'decimal:2',
        ];
    }

    public function designs(): HasMany
    {
        return $this->hasMany(ServiceDesign::class);
    }

    public function usesPopupForm(): bool
    {
        return $this->lead_form === 'popup';
    }

    /** @return list<string> */
    public static function calculatorPageSlugs(): array
    {
        return [
            'partitions',
            'slim-profile-door-system',
            'main-entrance-pvd-doors',
            'rack-systems-metal-pvd',
        ];
    }

    /** @return list<string> */
    public static function noDesignPageSlugs(): array
    {
        return ['rack-systems-metal-pvd'];
    }

    public function linksDesignsToProducts(): bool
    {
        return $this->slug === 'partitions';
    }

    public function usesGalleryOnlyLayout(): bool
    {
        return ServiceGallery::usesGalleryOnlyLayout($this);
    }

    public function usesCalculatorPageLayout(): bool
    {
        if (in_array($this->slug, ['corten-steel-facade', 'bespoke-metal-furniture'], true)) {
            return false;
        }

        return $this->has_calculator
            && in_array($this->slug, self::calculatorPageSlugs(), true);
    }

    public function calculatorEstimateLabel(): string
    {
        return match ($this->slug) {
            'partitions' => 'partition',
            'rack-systems-metal-pvd' => 'display rack',
            default => 'door',
        };
    }

    /** @return list<string> */
    public function relatedCategorySlugs(): array
    {
        return match ($this->slug) {
            'partitions' => ['partitions', 'fluted-panels', 'room-dividers'],
            'slim-profile-door-system', 'main-entrance-pvd-doors' => ['metal-furniture', 'door-handles'],
            'rack-systems-metal-pvd' => ['metal-furniture'],
            'bespoke-metal-furniture' => ['coffee-tables', 'corner-tables', 'glass-tables', 'metal-furniture'],
            default => [],
        };
    }

    /** @return list<string> */
    public function careGuidelines(): array
    {
        return match ($this->slug) {
            'partitions' => [
                'Material: Grade 304/316 stainless with PVD coating (champagne, rose gold, matte black)',
                'Designed in: Mumbai, India',
                'Fabrication: Vyomika Atelier LLP studio — custom dimensions',
                'Care: Wipe with soft microfibre; avoid abrasives and harsh chemicals',
                'Installation: Pan-India delivery; on-site installation available on request',
            ],
            'rack-systems-metal-pvd' => [
                'Material: Stainless steel with PVD finish — wall or freestanding configurations',
                'Designed in: Mumbai, India',
                'Load rating: Specified per module during quotation',
                'Care: Dust regularly; use non-abrasive cleaner on PVD surfaces',
                'Mounting: Wall fixings or freestanding bases per design approval',
            ],
            default => [
                'Material: Slim-profile stainless frame with PVD finish and premium glass',
                'Designed in: Mumbai, India',
                'Hardware: Concealed hinges, pivots, or slim-track sliding systems',
                'Care: Clean glass with standard glass cleaner; wipe frames with soft cloth',
                'Sealing: Weather and acoustic sealing per project specification',
            ],
        };
    }

    /** @return list<string> */
    public static function calculatorCategorySlugs(): array
    {
        return Product::calculatorCategorySlugs();
    }

    public static function serviceSlugForProduct(?string $productSlug, ?string $categorySlug): string
    {
        if ($productSlug && (str_contains($productSlug, 'door') || str_contains($productSlug, 'handle'))) {
            return 'main-entrance-pvd-doors';
        }

        if ($productSlug && str_contains($productSlug, 'rack')) {
            return 'rack-systems-metal-pvd';
        }

        return self::serviceSlugForCategory($categorySlug);
    }

    public static function serviceSlugForCategory(?string $categorySlug): string
    {
        return match ($categorySlug) {
            'partitions', 'fluted-panels', 'room-dividers' => 'partitions',
            'door-handles' => 'main-entrance-pvd-doors',
            'metal-furniture' => 'rack-systems-metal-pvd',
            default => 'partitions',
        };
    }

    /** @return list<string> */
    public static function careGuidelinesForCategory(?string $categorySlug): array
    {
        return (new self(['slug' => self::serviceSlugForCategory($categorySlug)]))->careGuidelines();
    }

    public static function estimateLabelForProduct(?string $productSlug, ?string $categorySlug): string
    {
        if ($productSlug && (str_contains($productSlug, 'door') || str_contains($productSlug, 'handle'))) {
            return 'door';
        }
        if ($productSlug && str_contains($productSlug, 'rack')) {
            return 'display rack';
        }

        return (new self(['slug' => self::serviceSlugForCategory($categorySlug)]))->calculatorEstimateLabel();
    }
}
