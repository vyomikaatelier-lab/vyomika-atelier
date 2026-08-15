<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryHideTest extends TestCase
{
    use RefreshDatabase;

    private function shopCategory(string $name, string $slug): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'section' => Product::SECTION_SHOP,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    private function shopProduct(Category $category, string $name, string $slug, array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'price' => 12000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_gallery_visible' => true,
            'hide_when_out_of_stock' => false,
        ], $overrides));
    }

    public function test_product_hides_from_shop_when_out_of_stock_and_flag_enabled(): void
    {
        $category = $this->shopCategory('Coffee Tables', 'coffee-tables');

        $product = $this->shopProduct($category, 'Hidden Coffee Table', 'hidden-coffee-table', [
            'stock' => 0,
            'hide_when_out_of_stock' => true,
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertDontSee('Hidden Coffee Table', false);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Hidden Coffee Table', false);
    }

    public function test_product_stays_in_shop_when_out_of_stock_but_flag_disabled(): void
    {
        $category = $this->shopCategory('Coffee Tables', 'coffee-tables');

        $this->shopProduct($category, 'Visible Coffee Table', 'visible-coffee-table', [
            'stock' => 0,
            'hide_when_out_of_stock' => false,
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Visible Coffee Table', false);
    }

    public function test_category_hides_from_shop_nav_when_unavailable_and_flag_enabled(): void
    {
        $category = $this->shopCategory('Corner Tables', 'corner-tables');
        $category->update(['hide_when_unavailable' => true]);

        $this->shopProduct($category, 'Sold Out Corner Table', 'sold-out-corner-table', [
            'stock' => 0,
            'hide_when_out_of_stock' => true,
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertDontSee('Sold Out Corner Table', false)
            ->assertDontSee('category=corner-tables', false)
            ->assertDontSee('shop/corner-tables', false);
    }

    public function test_category_gallery_stays_visible_but_empty_when_everything_is_hidden(): void
    {
        $category = $this->shopCategory('Corner Tables', 'corner-tables');
        $category->update(['hide_when_unavailable' => true]);

        $this->shopProduct($category, 'Sold Out Corner Table', 'sold-out-corner-table', [
            'stock' => 0,
            'hide_when_out_of_stock' => true,
        ]);

        $this->get(route('shop.show', 'corner-tables'))
            ->assertOk()
            ->assertDontSee('Sold Out Corner Table', false);
    }

    public function test_admin_can_save_inventory_hide_flags(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->shopCategory('Door Handles', 'door-handles');

        $product = $this->shopProduct($category, 'Handle One', 'handle-one');

        $this->actingAsAdmin($admin)
            ->put(route('admin.products.update', $product), [
                '_page_save' => '1',
                'category_id' => $category->id,
                'name' => 'Handle One',
                'slug' => 'handle-one',
                'price' => 12000,
                'stock' => 0,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => '1',
                'is_gallery_visible' => '1',
                'hide_when_out_of_stock' => '1',
            ])
            ->assertRedirect(route('admin.products.index', ['category_id' => $category->id]))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertTrue($product->hide_when_out_of_stock);
        $this->assertSame(0, $product->stock);

        $this->actingAsAdmin($admin)
            ->put(route('admin.categories.update', $category), [
                '_page_save' => '1',
                'name' => 'Door Handles',
                'section' => Product::SECTION_SHOP,
                'hide_when_unavailable' => '1',
                'is_active' => '1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertTrue($category->fresh()->hide_when_unavailable);
    }
}
