<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSyncPreservesAdminStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_sync_seeder_does_not_overwrite_existing_product_fields(): void
    {
        $category = Category::factory()->create(['slug' => 'mirror-frames', 'section' => 'shop']);
        $product = Product::factory()->create([
            'slug' => 'champagne-wave-partition',
            'category_id' => $category->id,
            'name' => 'Owner Final Name',
            'price' => 99999,
            'is_active' => false,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
        ]);

        $this->seed(CatalogSyncSeeder::class);

        $product->refresh();

        $this->assertSame('Owner Final Name', $product->name);
        $this->assertSame(99999.0, (float) $product->price);
        $this->assertFalse($product->is_active);
    }

    public function test_catalog_sync_seeder_does_not_reactivate_disabled_category(): void
    {
        $this->seed(CatalogSyncSeeder::class);

        Category::query()->where('slug', 'coffee-tables')->update(['is_active' => false]);

        $this->seed(CatalogSyncSeeder::class);

        $this->assertFalse(Category::query()->where('slug', 'coffee-tables')->value('is_active'));
    }

    public function test_catalog_sync_dry_run_writes_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAsAdmin($admin);

        Category::query()->delete();
        Product::query()->delete();

        CatalogSyncSeeder::$dryRun = true;
        $this->seed(CatalogSyncSeeder::class);
        CatalogSyncSeeder::$dryRun = false;

        $this->assertSame(0, Category::query()->count());
        $this->assertSame(0, Product::query()->count());
    }
}
