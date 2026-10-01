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
    public function configure(): static
    {
        return $this->afterMaking(function (Product $product) {
            if (! in_array($product->section, [Product::SECTION_STUDIO, Product::SECTION_RAILINGS], true)) {
                return;
            }

            $product->availability_mode = 'confirm_with_team';
            $product->shipping_india_mode = 'quoted';
            $product->packing_india_mode = 'quoted';
            $product->shipping_international_mode = 'quoted';
            $product->packing_international_mode = 'quoted';
            $product->shipping_india_amount = null;
            $product->packing_india_amount = null;
            $product->shipping_international_amount = null;
            $product->packing_international_amount = null;
            $product->shipping_india_basis = null;
            $product->packing_india_basis = null;
            $product->shipping_international_basis = null;
            $product->packing_international_basis = null;
        });
    }
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
            'availability_mode' => 'ready_stock',
            'shipping_india_mode' => 'included',
            'packing_india_mode' => 'included',
            'shipping_international_mode' => 'quoted',
            'packing_international_mode' => 'quoted',
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
            'availability_mode' => 'confirm_with_team',
            'shipping_india_mode' => 'quoted',
            'packing_india_mode' => 'quoted',
            'shipping_international_mode' => 'quoted',
            'packing_international_mode' => 'quoted',
        ]);
    }

    public function railings(): static
    {
        return $this->state(fn () => [
            'section' => Product::SECTION_RAILINGS,
            'purchase_mode' => Product::PURCHASE_MODE_QUOTE,
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
            'availability_mode' => 'confirm_with_team',
            'shipping_india_mode' => 'quoted',
            'packing_india_mode' => 'quoted',
            'shipping_international_mode' => 'quoted',
            'packing_international_mode' => 'quoted',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
