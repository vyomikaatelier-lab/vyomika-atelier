<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAdminPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_shop_product_price(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Test Mirror',
            'slug' => 'test-mirror',
            'price' => 5000,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Test Mirror',
            'slug' => 'test-mirror',
            'price' => 7500,
            'compare_price' => '',
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '1',
            'is_gallery_visible' => '1',
        ])->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $this->assertSame('7500.00', (string) $product->fresh()->price);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('₹7,500', false);
    }

    public function test_studio_product_price_is_used_as_sq_ft_rate_on_storefront(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'Partitions', 'section' => 'studio', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Wave Partition',
            'slug' => 'wave-partition',
            'price' => 1800,
            'stock' => 5,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Wave Partition',
            'slug' => 'wave-partition',
            'price' => 2200,
            'stock' => 5,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
            'is_active' => '1',
            'is_gallery_visible' => '1',
        ]);

        $product->refresh();
        $this->assertSame(2200, $product->sqFtRate());

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('₹2,200', false)
            ->assertSee('data-finish-rate="2200"', false);
    }
}
