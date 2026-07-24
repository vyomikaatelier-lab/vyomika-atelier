<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductCatalog;
use Database\Seeders\CatalogSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_categories_match_approved_shop_and_studio_list(): void
    {
        $slugs = collect(ProductCatalog::canonicalCategories())->pluck('slug')->all();

        $this->assertSame([
            'mirror-frames',
            'corner-tables',
            'coffee-tables',
            'glass-tables',
            'door-handles',
            'bespoke-metal-furniture',
            'partitions',
            'slim-profile-door-system',
            'main-entrance-pvd-doors',
            'rack-systems-metal-pvd',
        ], $slugs);

        foreach (ProductCatalog::obsoleteCategorySlugs() as $obsolete) {
            $this->assertNotContains($obsolete, $slugs);
        }
    }

    public function test_catalog_sync_seeder_sets_section_on_categories(): void
    {
        $this->seed(CatalogSyncSeeder::class);

        $partitions = Category::query()->where('slug', 'partitions')->first();
        $railings = Category::query()->where('slug', 'railings')->first();
        $fluted = Category::query()->where('slug', 'fluted-panels')->first();
        $metal = Category::query()->where('slug', 'metal-furniture')->first();
        $slim = Category::query()->where('slug', 'slim-profile-door-system')->first();

        $this->assertNotNull($partitions);
        $this->assertTrue($partitions->is_active);
        $this->assertSame('studio', $partitions->section);

        $this->assertNotNull($slim);
        $this->assertTrue($slim->is_active);
        $this->assertSame('studio', $slim->section);

        $this->assertNotNull($railings);
        $this->assertFalse($railings->is_active);
        $this->assertSame('railings', $railings->section);
        $this->assertFalse($fluted->is_active);
        $this->assertSame('studio', $fluted->section);
        $this->assertFalse($metal->is_active);
        $this->assertSame('studio', $metal->section);
    }

    public function test_category_resolved_section_prefers_db_section_column(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'is_active' => true]
        );
        $category->update(['section' => 'studio']);

        $this->assertSame('studio', $category->fresh()->resolvedSection());
    }

    public function test_sync_categories_command_creates_missing_categories(): void
    {
        Product::query()->delete();
        Category::query()->delete();
        $this->assertSame(0, Category::query()->count());

        $this->artisan('catalog:sync-categories')
            ->assertSuccessful();

        $expectedActive = count(ProductCatalog::canonicalCategories());
        $expectedTotal = $expectedActive + count(ProductCatalog::obsoleteCategorySlugs());

        $this->assertSame($expectedTotal, Category::query()->count());
        $this->assertSame($expectedActive, Category::query()->where('is_active', true)->count());
        $this->assertNotNull(Category::query()->where('slug', 'partitions')->where('is_active', true)->first());
        $this->assertNotNull(Category::query()->where('slug', 'home-decor')->where('is_active', false)->first());
        $this->assertNull(Category::query()->where('slug', 'home-decor')->where('is_active', true)->first());
    }

    public function test_product_form_validation_accepts_category_matching_section(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $response = $this->actingAsAdmin($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Test Mirror Frame',
            'slug' => 'test-mirror-frame',
            'price' => 1000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', [
            'slug' => 'test-mirror-frame',
            'category_id' => $category->id,
            'section' => Product::SECTION_SHOP,
        ]);
    }

    public function test_product_form_validation_rejects_category_with_wrong_section(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'PVD Partitions', 'section' => 'studio', 'is_active' => true]
        );

        $response = $this->actingAsAdmin($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Wrong Section Product',
            'slug' => 'wrong-section-product',
            'price' => 1000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('category_id');
    }
}