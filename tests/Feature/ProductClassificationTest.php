<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\StorefrontRoutes;
use Database\Seeders\CatalogSyncSeeder;
use Database\Seeders\CorrectCatalogClassificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_product_breadcrumbs_point_to_shop(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables', 'name' => 'Coffee Tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id]);

        $labels = array_column(StorefrontRoutes::productBreadcrumbs($product), 'label');

        $this->assertSame(['Home', 'Shop', 'Coffee Tables', $product->name], $labels);
    }

    public function test_studio_product_breadcrumbs_point_to_studio(): void
    {
        $category = Category::factory()->create(['slug' => 'partitions', 'name' => 'PVD Partitions']);
        $product = Product::factory()->studio()->create(['category_id' => $category->id]);

        $labels = array_column(StorefrontRoutes::productBreadcrumbs($product), 'label');

        $this->assertSame(['Home', 'Studio', 'PVD Partitions', $product->name], $labels);
    }

    public function test_database_classification_overrides_slug_fallback(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);

        // Slug/category alone would resolve to "shop" via ProductCatalog, but an
        // explicit DB section must win (DB-first classification).
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
        ]);

        $this->assertTrue($product->isStudioItem());
        $this->assertFalse($product->isShopProduct());
        $this->assertFalse($product->usesCheckoutFlow());
    }

    public function test_unclassified_legacy_product_fails_closed_to_enquiry(): void
    {
        $category = Category::factory()->create(['slug' => 'totally-unrecognised-category']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'totally-unrecognised-product',
            'section' => null,
            'purchase_mode' => null,
        ]);

        $this->assertFalse($product->usesCheckoutFlow());
        $this->assertSame('enquiry', $product->resolvedPurchaseMode());
    }

    /**
     * Slim Hinged Suite Door (and all slim hinged/sliding/telescopic slim-profile
     * designs) must classify as Studio > Slim Profile Door Systems, driven by the
     * real catalog data + CorrectCatalogClassificationSeeder — not a hardcoded guess.
     */
    public function test_slim_hinged_suite_door_classifies_as_studio_slim_profile(): void
    {
        $this->seed(CatalogSyncSeeder::class);
        $this->seed(CorrectCatalogClassificationSeeder::class);

        $product = Product::query()->where('slug', 'slim-hinged-suite-door')->first();

        $this->assertNotNull($product, 'Expected slim-hinged-suite-door to exist after CatalogSyncSeeder.');
        $this->assertSame(Product::SECTION_STUDIO, $product->section);
        $this->assertSame(Product::PURCHASE_MODE_ENQUIRY, $product->purchase_mode);
        $this->assertTrue($product->isStudioItem());
        $this->assertTrue($product->usesEnquiryFlow());
        $this->assertFalse($product->usesCheckoutFlow());
        $this->assertSame('slim-profile-door-system', $product->category?->slug);
    }

    public function test_correct_catalog_classification_seeder_is_idempotent(): void
    {
        $this->seed(CatalogSyncSeeder::class);

        $this->seed(CorrectCatalogClassificationSeeder::class);
        $firstPassSnapshot = Product::query()->orderBy('slug')->pluck('section', 'slug')->all();

        // Running it again must not change anything further and must not
        // create or delete any product rows.
        $countBefore = Product::query()->count();
        $this->seed(CorrectCatalogClassificationSeeder::class);
        $countAfter = Product::query()->count();

        $this->assertSame($countBefore, $countAfter);
        $this->assertSame($firstPassSnapshot, Product::query()->orderBy('slug')->pluck('section', 'slug')->all());
    }
}
