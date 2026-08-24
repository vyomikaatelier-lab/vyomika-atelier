<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->numberBetween(1000, 50000),
            'compare_price' => null,
            'sku' => strtoupper(Str::random(8)),
            'stock' => 25,
            'is_featured' => false,
            'is_active' => true,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_gallery_visible' => true,
        ];
    }

    public function shop(): static
    {
        return $this->state(fn () => [
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
        ]);
    }

    public function studio(): static
    {
        return $this->state(fn () => [
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
        ]);
    }

    public function railings(): static
    {
        return $this->state(fn () => [
            'section' => Product::SECTION_RAILINGS,
            'purchase_mode' => Product::PURCHASE_MODE_QUOTE,
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
