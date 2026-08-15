<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_hide_and_show_products(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        $first = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Bulk One',
            'slug' => 'bulk-one',
            'price' => 10000,
            'stock' => 2,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $second = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Bulk Two',
            'slug' => 'bulk-two',
            'price' => 12000,
            'stock' => 2,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->post(route('admin.products.bulk'), [
                'action' => 'deactivate',
                'ids' => [$first->id, $second->id],
                '_return_category_id' => $category->id,
            ])
            ->assertRedirect(route('admin.products.index', ['category_id' => $category->id]))
            ->assertSessionHas('success');

        $this->assertFalse($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);

        $this->actingAsAdmin($admin)
            ->post(route('admin.products.bulk'), [
                'action' => 'activate',
                'ids' => [$first->id],
            ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $this->assertTrue($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);
    }

    public function test_admin_can_bulk_update_categories_and_skip_nonempty_on_delete(): void
    {
        $admin = User::factory()->admin()->create();

        $empty = Category::query()->create([
            'name' => 'Empty Bulk Category',
            'slug' => 'empty-bulk-category',
            'section' => Product::SECTION_SHOP,
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $withProducts = Category::query()->create([
            'name' => 'Filled Bulk Category',
            'slug' => 'filled-bulk-category',
            'section' => Product::SECTION_SHOP,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        Product::query()->create([
            'category_id' => $withProducts->id,
            'name' => 'Linked Product',
            'slug' => 'linked-product',
            'price' => 5000,
            'stock' => 1,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->post(route('admin.categories.bulk'), [
                'action' => 'hide_when_unavailable',
                'ids' => [$empty->id, $withProducts->id],
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertTrue($empty->fresh()->hide_when_unavailable);
        $this->assertTrue($withProducts->fresh()->hide_when_unavailable);

        $this->actingAsAdmin($admin)
            ->post(route('admin.categories.bulk'), [
                'action' => 'delete',
                'ids' => [$empty->id, $withProducts->id],
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertNull(Category::query()->find($empty->id));
        $this->assertNotNull(Category::query()->find($withProducts->id));
    }

    public function test_products_index_shows_bulk_controls(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Handle Bulk UI',
            'slug' => 'handle-bulk-ui',
            'price' => 800,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('product-select-all', false)
            ->assertSee('Bulk actions', false)
            ->assertSee('product-bulk-check', false);
    }
}
