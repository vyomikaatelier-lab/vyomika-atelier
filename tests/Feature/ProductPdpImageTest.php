<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPdpImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_shows_only_main_image_not_legacy_gallery(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Gallery Test Table',
            'slug' => 'gallery-test-table',
            'description' => 'Single-image PDP test product',
            'price' => 15000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'image' => 'products/main-image.jpg',
            'gallery' => ['products/legacy-gallery-1.jpg', 'products/legacy-gallery-2.jpg'],
        ]);

        $this->assertSame([$product->imageUrl()], $product->galleryUrls());

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('storage/products/main-image.jpg', false)
            ->assertDontSee('storage/products/legacy-gallery-1.jpg', false)
            ->assertDontSee('storage/products/legacy-gallery-2.jpg', false)
            ->assertDontSee('data-pdp-thumb', false)
            ->assertDontSee('am-pdp__thumbs', false);
    }
}
