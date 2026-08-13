<?php

namespace App\Models;

use App\Support\FinishSwatches;
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
        'headline_text',
        'swatches_note',
        'tab_specifications',
        'tab_packaging',
        'tab_shipping',
        'dim_width_cm',
        'dim_height_cm',
        'price',
        'size_options',
        'compare_price',
        'sku',
        'stock',
        'image',
        'gallery',
        'is_featured',
        'is_active',
        'sort_order',
        'section',
        'purchase_mode',
        'pricing_type',
        'is_gallery_visible',
        'meta_title',
        'meta_description',
        'og_image',
        'image_alt',
        'material',
        'finish',
        'color',
        'weight_kg',
        'gtin',
        'mpn',
        'seo_keyword',
        'canonical_url',
        'robots_index',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'size_options' => 'array',
            'compare_price' => 'decimal:2',
            'dim_width_cm' => 'decimal:2',
            'dim_height_cm' => 'decimal:2',
            'gallery' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_gallery_visible' => 'boolean',
            'weight_kg' => 'decimal:3',
            'robots_index' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Higher sort_order appears first in admin and storefront listings. */
    public function scopeOrderedForDisplay($query)
    {
        return $query->orderByDesc('sort_order')->orderByDesc('id');
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    public function formattedPrice(): string
    {
        return '₹'.number_format($this->price, 0);
    }

    public function isDoorHandleProduct(): bool
    {
        return $this->category?->slug === 'door-handles';
    }

    public function hasSizeOptions(): bool
    {
        return $this->isDoorHandleProduct() && $this->normalizedSizeOptions() !== [];
    }

    /**
     * @return list<array{label: string, size_inches: ?float, price: float, compare_price: ?float, discount_percent: ?int, sku_suffix: ?string}>
     */
    public function normalizedSizeOptions(): array
    {
        $raw = $this->size_options;
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $options = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $price = (float) ($row['price'] ?? 0);

            if ($label === '' || $price < 0) {
                continue;
            }

            $sizeInches = filled($row['size_inches'] ?? null)
                ? round((float) $row['size_inches'], 2)
                : null;
            $skuSuffix = filled($row['sku_suffix'] ?? null)
                ? trim((string) $row['sku_suffix'])
                : null;
            $comparePrice = filled($row['compare_price'] ?? null)
                ? round((float) $row['compare_price'], 2)
                : null;
            $discountPercent = self::discountPercentFromPrices($price, $comparePrice);

            $options[] = [
                'label' => $label,
                'size_inches' => $sizeInches,
                'price' => round($price, 2),
                'compare_price' => $comparePrice,
                'discount_percent' => $discountPercent,
                'sku_suffix' => $skuSuffix,
            ];
        }

        usort($options, fn (array $a, array $b) => $a['price'] <=> $b['price']);

        return $options;
    }

    public function lowestSizePrice(): ?float
    {
        $options = $this->normalizedSizeOptions();

        return $options === [] ? null : (float) $options[0]['price'];
    }

    public function listingPrice(): float
    {
        return $this->lowestSizePrice() ?? (float) $this->price;
    }

    public function formattedListingPrice(): string
    {
        if ($this->hasSizeOptions()) {
            return 'From ₹'.number_format($this->listingPrice(), 0);
        }

        return $this->formattedPrice();
    }

    /** @return array{label: string, size_inches: ?float, price: float, compare_price: ?float, discount_percent: ?int, sku_suffix: ?string}|null */
    public function resolveSizeOption(?string $label): ?array
    {
        $options = $this->normalizedSizeOptions();

        if ($options === []) {
            return null;
        }

        if (filled($label)) {
            foreach ($options as $option) {
                if ($option['label'] === $label) {
                    return $option;
                }
            }
        }

        return $options[0];
    }

    public function unitPriceForSize(?string $label = null): float
    {
        $resolved = $this->resolveSizeOption($label);

        return $resolved !== null ? (float) $resolved['price'] : (float) $this->price;
    }

    public function resolvedHeadlineText(): string
    {
        if (filled($this->headline_text)) {
            return trim((string) $this->headline_text);
        }

        if (! filled($this->sku)) {
            return '';
        }

        return 'SKU: '.$this->sku.' · Pan-India shipping';
    }

    public function resolvedSwatchesNote(): string
    {
        if (filled($this->swatches_note)) {
            return trim((string) $this->swatches_note);
        }

        return 'Black Mirror & Black Brush: +30% on sq ft rate';
    }

    public function isMirrorFrameProduct(): bool
    {
        return $this->category?->slug === 'mirror-frames';
    }

    public function hasMirrorDimensions(): bool
    {
        return $this->isMirrorFrameProduct()
            && $this->dim_width_cm !== null
            && $this->dim_height_cm !== null
            && (float) $this->dim_width_cm > 0
            && (float) $this->dim_height_cm > 0;
    }

    /** @return array{feet: string, mm: string, cm: string}|null */
    public function mirrorDimensionDisplays(): ?array
    {
        if (! $this->hasMirrorDimensions()) {
            return null;
        }

        $widthCm = (float) $this->dim_width_cm;
        $heightCm = (float) $this->dim_height_cm;

        return [
            'feet' => self::formatMirrorDimensionPairInFeet($widthCm, $heightCm),
            'mm' => self::formatMirrorDimensionPair((int) round($widthCm * 10), (int) round($heightCm * 10), 'mm'),
            'cm' => self::formatMirrorDimensionPair(
                self::formatMirrorDimensionNumber($widthCm),
                self::formatMirrorDimensionNumber($heightCm),
                'cm'
            ),
        ];
    }

    /**
     * Convert feet + inches to centimetres (canonical storage).
     * 1 in = 2.54 cm; 1 ft = 12 in.
     */
    public static function cmFromFeetInches(float|int $feet, float|int $inches): float
    {
        $totalInches = ((float) $feet * 12.0) + (float) $inches;

        return round(max(0, $totalInches) * 2.54, 2);
    }

    /**
     * Convert centimetres to feet + inches for admin form / display.
     *
     * @return array{feet: int, inches: float}
     */
    public static function feetInchesFromCm(float $cm, int $inchPrecision = 1): array
    {
        if ($cm <= 0) {
            return ['feet' => 0, 'inches' => 0.0];
        }

        $totalInches = round($cm / 2.54, $inchPrecision);
        $feet = (int) floor($totalInches / 12);
        $inches = round($totalInches - ($feet * 12), $inchPrecision);

        if ($inches >= 12) {
            $feet++;
            $inches = 0.0;
        }

        return ['feet' => max(0, $feet), 'inches' => $inches];
    }

    private static function formatMirrorDimensionPair(int|float|string $width, int|float|string $height, string $unit): string
    {
        $w = is_string($width) ? $width : self::formatMirrorDimensionNumber($width);
        $h = is_string($height) ? $height : self::formatMirrorDimensionNumber($height);

        return $w.' × '.$h.' '.$unit;
    }

    private static function formatMirrorDimensionPairInFeet(float $widthCm, float $heightCm): string
    {
        $widthParts = self::feetInchesFromCm($widthCm, 1);
        $heightParts = self::feetInchesFromCm($heightCm, 1);
        $widthHasInches = $widthParts['inches'] > 0;
        $heightHasInches = $heightParts['inches'] > 0;

        // Compact whole-feet form when both axes have zero inches: "2 × 4 ft"
        if (! $widthHasInches && ! $heightHasInches) {
            return $widthParts['feet'].' × '.$heightParts['feet'].' ft';
        }

        return self::formatFeetInchesLabel($widthParts).' × '.self::formatFeetInchesLabel($heightParts);
    }

    /** @param array{feet: int, inches: float} $parts */
    private static function formatFeetInchesLabel(array $parts): string
    {
        $feet = $parts['feet'];
        $inches = $parts['inches'];

        if ($inches <= 0) {
            return $feet.' ft';
        }

        return $feet.' ft '.self::formatMirrorDimensionNumber($inches).' in';
    }

    private static function formatMirrorDimensionNumber(int|float $value): string
    {
        if (is_int($value) || fmod((float) $value, 1.0) === 0.0) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
    }

    /** Per-sq-ft rate for studio products; uses this product's price when set. */
    public function sqFtRate(): int
    {
        if ($this->resolvedPricingType() === self::PRICING_SQUARE_FOOT && (float) $this->price > 0) {
            return (int) round((float) $this->price);
        }

        return self::baseSqFtRate();
    }

    public function blackSqFtRateForProduct(): int
    {
        return (int) round($this->sqFtRate() * (float) config('pricing.black_finish_multiplier', 1.3));
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

    /** @return array<int, string> Main product image only (legacy gallery column is unused on PDP). */
    public function galleryUrls(): array
    {
        $main = $this->imageUrl();

        return $main ? [$main] : [];
    }

    public function discountPercent(): ?int
    {
        return self::discountPercentFromPrices((float) $this->price, $this->compare_price !== null ? (float) $this->compare_price : null);
    }

    public function hasDisplayComparePrice(): bool
    {
        return $this->discountPercent() !== null;
    }

    public static function discountPercentFromPrices(float $price, ?float $comparePrice): ?int
    {
        if ($comparePrice === null || $comparePrice <= $price || $comparePrice <= 0 || $price < 0) {
            return null;
        }

        return (int) round((1 - $price / $comparePrice) * 100);
    }

    /** True when a string looks like a size/dimension chip (e.g. "600 x 1200 mm standard"). */
    public static function isDimensionLikeSpecLine(string $line): bool
    {
        $normalized = trim(html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($normalized === '') {
            return false;
        }

        // "600 x 1200 mm", "900 × 1200 mm standard", "600 mm diameter"
        return (bool) preg_match(
            '/\d+(?:[.,]\d+)?\s*[x×]\s*\d+(?:[.,]\d+)?\s*(mm|cm|m|ft|in|inches?\b)|'
            .'^\d+(?:[.,]\d+)?\s*(mm|cm|m)\b.*\b(diameter|dia\.?|standard)\b|'
            .'^\d+(?:[.,]\d+)?\s*(mm|cm)\s*$/iu',
            $normalized
        );
    }

    /**
     * Spec / highlight lines with dimension chips removed when structured dims exist.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    public function linesWithoutDimensionChips(array $lines): array
    {
        if (! $this->hasMirrorDimensions()) {
            return array_values($lines);
        }

        return array_values(array_filter(
            $lines,
            fn (string $line): bool => ! self::isDimensionLikeSpecLine($line)
        ));
    }

    /**
     * Parse tab_specifications into clean display lines.
     * Accepts newline-separated text or legacy HTML (<li>, <br>, paragraphs).
     *
     * @return list<string>
     */
    public function specificationLines(): array
    {
        return self::linesFromTabText($this->tab_specifications);
    }

    /**
     * @return list<string>
     */
    public static function linesFromTabText(?string $text): array
    {
        if (! filled($text)) {
            return [];
        }

        if (preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $text, $matches) && $matches[1] !== []) {
            return array_values(array_filter(
                array_map(
                    fn (string $line): string => trim(html_entity_decode(strip_tags($line), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                    $matches[1]
                ),
                fn (string $line): bool => $line !== ''
            ));
        }

        $normalized = str_ireplace(
            ['<br>', '<br/>', '<br />', '</p>', '</div>', '</h1>', '</h2>', '</h3>', '</h4>', '</li>'],
            "\n",
            $text
        );
        $plain = html_entity_decode(strip_tags($normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\r\n|\r|\n/', $plain) ?: [];

        return array_values(array_filter(
            array_map(fn (string $line): string => trim($line), $lines),
            fn (string $line): bool => $line !== ''
        ));
    }

    public static function normalizeTabLines(?string $text): ?string
    {
        $lines = self::linesFromTabText($text);

        return $lines === [] ? null : implode("\n", $lines);
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
        return ['partitions'];
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

    public function isClassified(): bool
    {
        return in_array($this->section, self::SECTIONS, true)
            && in_array($this->purchase_mode, self::PURCHASE_MODES, true)
            && in_array($this->pricing_type, self::PRICING_TYPES, true)
            && $this->category_id !== null;
    }

    public function scopeUnclassified($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('section')
                ->orWhereNotIn('section', self::SECTIONS)
                ->orWhereNull('purchase_mode')
                ->orWhereNotIn('purchase_mode', self::PURCHASE_MODES)
                ->orWhereNull('pricing_type')
                ->orWhereNotIn('pricing_type', self::PRICING_TYPES)
                ->orWhereNull('category_id');
        });
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
        return FinishSwatches::all();
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
