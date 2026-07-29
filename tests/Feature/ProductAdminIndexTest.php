<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductCatalog;
use Database\Seeders\CatalogSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAdminIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_products_by_parent_category(): void
    {
        $admin = User::factory()->admin()->create();

        $mirrors = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );
        $tables = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $mirrors->id,
            'name' => 'Round Mirror',
            'slug' => 'round-mirror',
            'price' => 5000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $tables->id,
            'name' => 'Oak Coffee Table',
            'slug' => 'oak-coffee-table',
            'price' => 8000,
            'stock' => 3,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->get(route('admin.products.index', ['category_id' => $mirrors->id]))
            ->assertOk()
            ->assertSee('Round Mirror', false)
            ->assertDontSee('Oak Coffee Table', false)
            ->assertSee('Mirror Frames', false);
    }

    public function test_admin_can_filter_products_by_section(): void
    {
        $admin = User::factory()->admin()->create();

        $mirrors = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );
        $partitions = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'Partitions', 'section' => 'studio', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $mirrors->id,
            'name' => 'Shop Product',
            'slug' => 'shop-product',
            'price' => 1000,
            'stock' => 1,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $partitions->id,
            'name' => 'Studio Product',
            'slug' => 'studio-product',
            'price' => 1800,
            'stock' => 1,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->get(route('admin.products.index', ['section' => Product::SECTION_STUDIO]))
            ->assertOk()
            ->assertSee('Studio Product', false)
            ->assertDontSee('Shop Product', false);
    }

    public function test_category_filter_preserves_through_pagination(): void
    {
        $admin = User::factory()->admin()->create();

        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        foreach (range(1, 16) as $i) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => "Mirror {$i}",
                'slug' => "mirror-{$i}",
                'price' => 1000 + $i,
                'stock' => 1,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAsAdmin($admin)
            ->get(route('admin.products.index', ['category_id' => $category->id]));

        $response->assertOk();
        $response->assertSee('category_id='.$category->id, false);
    }

    public function test_admin_products_index_uses_category_dropdown_not_chips(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seed(CatalogSyncSeeder::class);

        $response = $this->actingAsAdmin($admin)->get(route('admin.products.index'));

        $response->assertOk();
        $response->assertSee('name="category_id"', false);
        $response->assertSee('<optgroup label="Shop">', false);
        $response->assertSee('<optgroup label="Studio">', false);
    }

    public function test_obsolete_categories_hidden_from_admin_products_index_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seed(CatalogSyncSeeder::class);

        $response = $this->actingAsAdmin($admin)->get(route('admin.products.index'));

        $response->assertOk();

        foreach (ProductCatalog::obsoleteCategorySlugs() as $slug) {
            $response->assertDontSee('value="'.Category::query()->where('slug', $slug)->value('id').'"', false);
        }
    }

    public function test_obsolete_categories_hidden_from_product_form_dropdown(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seed(CatalogSyncSeeder::class);

        $response = $this->actingAsAdmin($admin)->get(route('admin.products.create'));

        $response->assertOk();

        foreach (ProductCatalog::obsoleteCategorySlugs() as $slug) {
            $response->assertDontSee('('.$slug.')', false);
        }
    }

    public function test_store_redirects_back_to_filtered_products_index(): void
    {
        $admin = User::factory()->admin()->create();

        $mirrors = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $this->actingAsAdmin($admin)
            ->from(route('admin.products.create', ['category_id' => $mirrors->id]))
            ->post(route('admin.products.store'), [
                '_page_save' => '1',
                '_return_category_id' => $mirrors->id,
                'category_id' => $mirrors->id,
                'name' => 'Filtered Mirror',
                'slug' => 'filtered-mirror',
                'price' => 5000,
                'stock' => 2,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.products.index', ['category_id' => $mirrors->id]))
            ->assertSessionHasNoErrors();
    }

    public function test_update_redirects_back_to_filtered_products_index(): void
    {
        $admin = User::factory()->admin()->create();

        $mirrors = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $mirrors->id,
            'name' => 'Round Mirror',
            'slug' => 'round-mirror',
            'price' => 5000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->from(route('admin.products.edit', ['product' => $product, 'category_id' => $mirrors->id]))
            ->put(route('admin.products.update', $product), [
                '_page_save' => '1',
                '_return_category_id' => $mirrors->id,
                'category_id' => $mirrors->id,
                'name' => 'Updated Mirror',
                'slug' => 'round-mirror',
                'price' => 5500,
                'stock' => 5,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.products.index', ['category_id' => $mirrors->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated Mirror', $product->fresh()->name);
    }

    public function test_destroy_redirects_back_to_filtered_products_index(): void
    {
        $admin = User::factory()->admin()->create();

        $mirrors = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $mirrors->id,
            'name' => 'Round Mirror',
            'slug' => 'round-mirror',
            'price' => 5000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->from(route('admin.products.index', ['category_id' => $mirrors->id]))
            ->delete(route('admin.products.destroy', $product), [
                '_return_category_id' => $mirrors->id,
            ])
            ->assertRedirect(route('admin.products.index', ['category_id' => $mirrors->id]));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
