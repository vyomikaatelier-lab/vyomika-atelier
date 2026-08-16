<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionGalleryRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_category_gallery_reflects_admin_product_updates(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Original Coffee Table',
            'slug' => 'brushed-brass-coffee-table',
            'description' => 'Original gallery description',
            'price' => 18900,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Updated Coffee Table Name',
            'slug' => 'brushed-brass-coffee-table',
            'description' => 'Updated gallery description from admin',
            'price' => 22500,
            'compare_price' => '',
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '1',
            'is_gallery_visible' => '1',
        ])->assertRedirect();

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('Updated Coffee Table Name', false)
            ->assertSee('Updated gallery description from admin', false);
    }

    public function test_mirror_frames_gallery_uses_database_product_data(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Admin Arched Mirror',
            'slug' => 'arched-wall-mirror',
            'description' => 'Admin mirror gallery description',
            'price' => 12000,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Admin Arched Mirror',
            'slug' => 'arched-wall-mirror',
            'description' => 'Admin mirror gallery description',
            'price' => 12000,
            'compare_price' => '',
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '1',
            'is_gallery_visible' => '1',
        ]);

        $this->get(route('shop.mirror-frames.index'))
            ->assertOk()
            ->assertSee('Admin Arched Mirror', false)
            ->assertSee('Admin mirror gallery description', false)
            ->assertDontSee('Soft-arch profile wall mirror', false);
    }

    public function test_mirror_frames_gallery_includes_new_admin_products(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Bespoke Oval Mirror',
            'slug' => 'bespoke-oval-mirror',
            'description' => 'Custom oval mirror added from admin',
            'price' => 15000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_gallery_visible' => true,
            'sort_order' => 100,
        ]);

        $this->get(route('shop.mirror-frames.index'))
            ->assertOk()
            ->assertSee('Bespoke Oval Mirror', false)
            ->assertSee('Custom oval mirror added from admin', false);
    }
}
