<?php

namespace App\Models;

use App\Support\ProductCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    public const SECTION_SHOP = 'shop';

    public const SECTION_STUDIO = 'studio';

    public const SECTION_RAILINGS = 'railings';

    /** @var list<string> */
    public const SECTIONS = [self::SECTION_SHOP, self::SECTION_STUDIO, self::SECTION_RAILINGS];

    public const PURCHASE_MODE_CHECKOUT = 'checkout';

    public const PURCHASE_MODE_ENQUIRY = 'enquiry';

    public const PURCHASE_MODE_QUOTE = 'quote';

    /** @var list<string> */
    public const PURCHASE_MODES = [self::PURCHASE_MODE_CHECKOUT, self::PURCHASE_MODE_ENQUIRY, self::PURCHASE_MODE_QUOTE];

    public const PRICING_FIXED = 'fixed';

    public const PRICING_SQUARE_FOOT = 'square_foot';

    public const PRICING_QUOTATION_ONLY = 'quotation_only';

    /** @var list<string> */
    public const PRICING_TYPES = [self::PRICING_FIXED, self::PRICING_SQUARE_FOOT, self::PRICING_QUOTATION_ONLY];

    /** Section → default purchase mode. Shop=checkout, Studio=enquiry, Railings=quote. */
    public const SECTION_PURCHASE_MODE_MAP = [
        self::SECTION_SHOP => self::PURCHASE_MODE_CHECKOUT,
        self::SECTION_STUDIO => self::PURCHASE_MODE_ENQUIRY,
        self::SECTION_RAILINGS => self::PURCHASE_MODE_QUOTE,
    ];

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'compare_price',
        'sku',
        'stock',
        'image',
        'gallery',
        'is_featured',
        'is_active',
        'section',
        'purchase_mode',
        'pricing_type',
        'is_gallery_visible',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'gallery' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_gallery_visible' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    public function formattedPrice(): string
    {
        return '₹' . number_format($this->price, 0);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        return asset('storage/'.$this->image);
    }

    /** @return array<int, string> */
    public function galleryUrls(): array
    {
        $urls = [];
        if ($main = $this->imageUrl()) {
            $urls[] = $main;
        }
        foreach ($this->gallery ?? [] as $item) {
            if (is_string($item) && $item !== '') {
                $urls[] = str_starts_with($item, 'http') ? $item : asset('storage/'.$item);
            }
        }

        return array_values(array_unique($urls));
    }

    public function discountPercent(): ?int
    {
        if (! $this->compare_price || $this->compare_price <= $this->price) {
            return null;
        }

        return (int) round((1 - $this->price / $this->compare_price) * 100);
    }

    /** @return list<string> */
    public static function checkoutCategorySlugs(): array
    {
        return [
            'mirror-frames',
            'coffee-tables',
            'corner-tables',
            'glass-tables',
            'door-handles',
        ];
    }

    /** @return list<string> */
    public static function calculatorCategorySlugs(): array
    {
        return ['partitions', 'fluted-panels', 'room-dividers'];
    }

    /**
     * Database-first section resolution. Falls back to the slug/category-based
     * ProductCatalog map only while legacy rows have not been classified yet
     * (see Database\Seeders\CorrectCatalogClassificationSeeder).
     */
    public function resolvedSection(): ?string
    {
        if (in_array($this->section, self::SECTIONS, true)) {
            return $this->section;
        }

        $fallback = ProductCatalog::sectionFor($this->slug, $this->category?->slug);

        return $fallback === 'unknown' ? null : $fallback;
    }

    public function resolvedPurchaseMode(): string
    {
        if (in_array($this->purchase_mode, self::PURCHASE_MODES, true)) {
            return $this->purchase_mode;
        }

        // Fail closed: unknown/unclassified products never default to checkout.
        return self::SECTION_PURCHASE_MODE_MAP[$this->resolvedSection()] ?? self::PURCHASE_MODE_ENQUIRY;
    }

    public function resolvedPricingType(): string
    {
        if (in_array($this->pricing_type, self::PRICING_TYPES, true)) {
            return $this->pricing_type;
        }

        return match ($this->resolvedSection()) {
            self::SECTION_SHOP => self::PRICING_FIXED,
            self::SECTION_STUDIO => self::PRICING_SQUARE_FOOT,
            self::SECTION_RAILINGS => self::PRICING_QUOTATION_ONLY,
            default => self::PRICING_FIXED,
        };
    }

    public function isShopProduct(): bool
    {
        return $this->resolvedSection() === self::SECTION_SHOP;
    }

    public function isStudioItem(): bool
    {
        return $this->resolvedSection() === self::SECTION_STUDIO;
    }

    public function isRailingItem(): bool
    {
        return $this->resolvedSection() === self::SECTION_RAILINGS;
    }

    public function usesCheckoutFlow(): bool
    {
        return $this->isShopProduct() && $this->resolvedPurchaseMode() === self::PURCHASE_MODE_CHECKOUT;
    }

    public function usesEnquiryFlow(): bool
    {
        return $this->resolvedPurchaseMode() === self::PURCHASE_MODE_ENQUIRY;
    }

    public function usesQuoteFlow(): bool
    {
        return $this->resolvedPurchaseMode() === self::PURCHASE_MODE_QUOTE;
    }

    /** Whether this product may ever legally enter the cart/checkout flow. */
    public function canEnterCart(): bool
    {
        return $this->is_active && $this->usesCheckoutFlow();
    }

    /** @deprecated Use isStudioItem(). Kept for backward compatibility. */
    public function isStudioProduct(): bool
    {
        return $this->isStudioItem();
    }

    public function showsSqFtCalculator(): bool
    {
        return $this->isStudioItem();
    }

    public function scopeSection($query, string $section)
    {
        return $query->where('section', $section);
    }

    public function scopeShopSection($query)
    {
        return $query->where('section', self::SECTION_SHOP);
    }

    public function scopeStudioSection($query)
    {
        return $query->where('section', self::SECTION_STUDIO);
    }

    public function scopeRailingsSection($query)
    {
        return $query->where('section', self::SECTION_RAILINGS);
    }

    public function scopeCheckoutEligible($query)
    {
        return $query->where('is_active', true)
            ->where('section', self::SECTION_SHOP)
            ->where('purchase_mode', self::PURCHASE_MODE_CHECKOUT);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** @return list<array{slug: string, name: string, image: string, hex: string, rate: int, is_black: bool}> */
    public static function finishSwatches(): array
    {
        $base = self::baseSqFtRate();
        $blackRate = self::blackSqFtRate();

        $swatches = [
            ['slug' => 'gold-mirror', 'name' => 'Gold Mirror', 'hex' => '#D4AF37', 'is_black' => false],
            ['slug' => 'gold-brush', 'name' => 'Gold Brush', 'hex' => '#C5A028', 'is_black' => false],
            ['slug' => 'rose-gold-mirror', 'name' => 'Rose Gold Mirror', 'hex' => '#B76E79', 'is_black' => false],
            ['slug' => 'rose-gold-brush', 'name' => 'Rose Gold Brush', 'hex' => '#A85A65', 'is_black' => false],
            ['slug' => 'champagne-mirror', 'name' => 'Champagne Mirror', 'hex' => '#C9A86C', 'is_black' => false],
            ['slug' => 'champagne-brush', 'name' => 'Champagne Brush', 'hex' => '#B8956A', 'is_black' => false],
            ['slug' => 'black-mirror', 'name' => 'Black Mirror', 'hex' => '#1A1A1A', 'is_black' => true],
            ['slug' => 'black-brush', 'name' => 'Black Brush', 'hex' => '#2C2C2C', 'is_black' => true],
        ];

        return array_map(function (array $s) use ($base, $blackRate) {
            $s['rate'] = $s['is_black'] ? $blackRate : $base;
            $s['image'] = 'images/finishes/'.$s['slug'].'.jpg';

            return $s;
        }, $swatches);
    }

    public static function baseSqFtRate(): int
    {
        return (int) config('pricing.base_sqft_rate', 1800);
    }

    public static function blackSqFtRate(): int
    {
        return (int) round(self::baseSqFtRate() * (float) config('pricing.black_finish_multiplier', 1.3));
    }
}
