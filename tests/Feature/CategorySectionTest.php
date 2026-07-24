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

    public function test_canonical_categories_include_railings_fluted_panels_and_metal_furniture(): void
    {
        $slugs = collect(ProductCatalog::canonicalCategories())->pluck('slug')->all();

        $this->assertContains('railings', $slugs);
        $this->assertContains('fluted-panels', $slugs);
        $this->assertContains('metal-furniture', $slugs);
        $this->assertContains('home-decor', $slugs);
    }

    public function test_catalog_sync_seeder_sets_section_on_categories(): void
    {
        $this->seed(CatalogSyncSeeder::class);

        $railings = Category::query()->where('slug', 'railings')->first();
        $fluted = Category::query()->where('slug', 'fluted-panels')->first();
        $metal = Category::query()->where('slug', 'metal-furniture')->first();

        $this->assertNotNull($railings);
        $this->assertSame('railings', $railings->section);
        $this->assertSame('studio', $fluted->section);
        $this->assertSame('studio', $metal->section);
    }

    public function test_category_resolved_section_prefers_db_section_column(): void
    {
        $category = Category::factory()->create([
            'slug' => 'coffee-tables',
            'section' => 'studio',
        ]);

        $this->assertSame('studio', $category->resolvedSection());
    }

    public function test_sync_categories_command_creates_missing_categories(): void
    {
        $this->assertSame(0, Category::query()->count());

        $this->artisan('catalog:sync-categories')
            ->assertSuccessful();

        $this->assertSame(count(ProductCatalog::canonicalCategories()), Category::query()->count());
        $this->assertNotNull(Category::query()->where('slug', 'railings')->first());
        $this->assertNotNull(Category::query()->where('slug', 'home-decor')->first());
    }

    public function test_product_form_validation_accepts_category_matching_section(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create([
            'slug' => 'mirror-frames',
            'section' => 'shop',
        ]);

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
        $category = Category::factory()->create([
            'slug' => 'partitions',
            'section' => 'studio',
        ]);

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
