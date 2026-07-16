<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
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
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'gallery' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
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
    public static function calculatorCategorySlugs(): array
    {
        return ['partitions', 'fluted-panels', 'room-dividers', 'door-handles'];
    }

    public function isFurnitureProduct(): bool
    {
        $slug = strtolower($this->slug ?? '');

        if (str_contains($slug, 'door') || str_contains($slug, 'rack')) {
            return false;
        }

        if (in_array($this->category?->slug, ['coffee-tables', 'corner-tables', 'glass-tables'], true)) {
            return true;
        }

        foreach (['coffee', 'table', 'console'] as $keyword) {
            if (str_contains($slug, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function showsSqFtCalculator(): bool
    {
        if ($this->isFurnitureProduct()) {
            return false;
        }

        $categorySlug = $this->category?->slug;
        $slug = strtolower($this->slug ?? '');

        if ($categorySlug && in_array($categorySlug, self::calculatorCategorySlugs(), true)) {
            return true;
        }

        if ($categorySlug === 'metal-furniture') {
            return str_contains($slug, 'door') || str_contains($slug, 'rack');
        }

        if (str_contains($slug, 'door') || str_contains($slug, 'rack')) {
            return true;
        }

        if (str_contains($slug, 'handle') || str_contains($slug, 'pull')) {
            return true;
        }

        return false;
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
        return 1800;
    }

    public static function blackSqFtRate(): int
    {
        return (int) round(self::baseSqFtRate() * 1.3);
    }
}
