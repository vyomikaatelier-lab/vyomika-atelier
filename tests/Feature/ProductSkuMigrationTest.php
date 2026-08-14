<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductSkuMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function rollbackSeoMigrationIfApplied(): void
    {
        if (Schema::hasColumn('products', 'robots_index')) {
            $this->artisan('migrate:rollback', [
                '--path' => 'database/migrations/2026_08_13_180000_google_search_product_seo.php',
                '--force' => true,
            ])->assertExitCode(0);
        }
    }

    private function runSeoMigration(): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_08_13_180000_google_search_product_seo.php',
            '--force' => true,
        ]);
    }

    private function createProduct(array $overrides = []): Product
    {
        $category = Category::factory()->create([
            'slug' => 'coffee-tables',
            'section' => Product::SECTION_SHOP,
        ]);

        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ], $overrides));
    }

    public function test_migration_allows_multiple_null_skus(): void
    {
        $this->rollbackSeoMigrationIfApplied();
        $this->createProduct(['sku' => null, 'slug' => 'null-sku-a']);
        $this->createProduct(['sku' => null, 'slug' => 'null-sku-b']);

        $this->runSeoMigration()->assertExitCode(0);

        $this->assertSame(2, Product::query()->whereNull('sku')->count());
    }

    public function test_migration_normalizes_blank_skus_to_null(): void
    {
        $this->rollbackSeoMigrationIfApplied();
        $this->createProduct(['sku' => '', 'slug' => 'blank-sku-a']);
        $this->createProduct(['sku' => '', 'slug' => 'blank-sku-b']);

        $this->runSeoMigration()->assertExitCode(0);

        $this->assertSame(0, Product::query()->where('sku', '')->count());
        $this->assertSame(2, Product::query()->whereNull('sku')->count());
    }

    public function test_migration_succeeds_with_unique_non_empty_skus(): void
    {
        $this->rollbackSeoMigrationIfApplied();
        $this->createProduct(['sku' => 'UNIQUE-A', 'slug' => 'unique-a']);
        $this->createProduct(['sku' => 'UNIQUE-B', 'slug' => 'unique-b']);

        $this->runSeoMigration()->assertExitCode(0);
    }

    public function test_migration_fails_when_duplicate_non_empty_skus_exist(): void
    {
        $this->rollbackSeoMigrationIfApplied();
        $this->createProduct(['sku' => 'DUPE-SKU', 'slug' => 'dupe-a']);
        $this->createProduct(['sku' => 'DUPE-SKU', 'slug' => 'dupe-b']);

        $this->expectException(\RuntimeException::class);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_08_13_180000_google_search_product_seo.php',
            '--force' => true,
        ]);

        $this->assertSame('DUPE-SKU', Product::query()->where('slug', 'dupe-b')->value('sku'));
    }

    public function test_audit_command_reports_duplicates_with_non_zero_exit(): void
    {
        $this->rollbackSeoMigrationIfApplied();
        $this->createProduct(['sku' => 'AUDIT-DUPE', 'slug' => 'audit-dupe-1']);
        $this->createProduct(['sku' => 'AUDIT-DUPE', 'slug' => 'audit-dupe-2']);

        $exitCode = Artisan::call('products:audit-skus');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('AUDIT-DUPE', $output);
        $this->assertStringContainsString('audit-dupe-1', $output);
        $this->assertStringContainsString('audit-dupe-2', $output);
    }

    public function test_audit_command_succeeds_when_no_duplicates(): void
    {
        $this->createProduct(['sku' => 'AUDIT-OK-1', 'slug' => 'audit-ok-1']);
        $this->createProduct(['sku' => null, 'slug' => 'audit-ok-2']);

        $this->assertSame(0, Artisan::call('products:audit-skus'));
    }

    public function test_admin_create_stores_null_sku_when_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create([
            'slug' => 'coffee-tables',
            'section' => Product::SECTION_SHOP,
        ]);

        $this->actingAsAdmin($admin)
            ->post(route('admin.products.store'), [
                'category_id' => $category->id,
                'name' => 'Blank SKU Product',
                'slug' => 'blank-sku-product',
                'description' => 'Test',
                'price' => 1000,
                'stock' => 1,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'sku' => '',
                'is_active' => 1,
                'is_gallery_visible' => 1,
                'robots_index' => 1,
            ])
            ->assertRedirect();

        $product = Product::query()->where('slug', 'blank-sku-product')->first();
        $this->assertNotNull($product);
        $this->assertNull($product->sku);
    }

    public function test_admin_update_normalizes_blank_sku_to_null(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct(['sku' => 'KEEP-SKU', 'slug' => 'update-blank-sku']);

        $this->actingAsAdmin($admin)
            ->put(route('admin.products.update', $product), [
                'category_id' => $product->category_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description ?? 'Desc',
                'price' => $product->price,
                'stock' => $product->stock,
                'section' => $product->section,
                'purchase_mode' => $product->purchase_mode,
                'pricing_type' => $product->pricing_type,
                'sku' => '',
                'is_active' => 1,
                'is_gallery_visible' => 1,
                'robots_index' => 1,
            ])
            ->assertRedirect();

        $this->assertNull($product->fresh()->sku);
    }

    public function test_admin_update_allows_keeping_own_sku(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct(['sku' => 'KEEP-MINE-001', 'slug' => 'keep-mine-product']);

        $this->actingAsAdmin($admin)
            ->put(route('admin.products.update', $product), [
                'category_id' => $product->category_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description ?? 'Desc',
                'price' => $product->price,
                'stock' => $product->stock,
                'section' => $product->section,
                'purchase_mode' => $product->purchase_mode,
                'pricing_type' => $product->pricing_type,
                'sku' => 'KEEP-MINE-001',
                'is_active' => 1,
                'is_gallery_visible' => 1,
                'robots_index' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('KEEP-MINE-001', $product->fresh()->sku);
    }

    public function test_admin_update_normalizes_sku_prefix(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct(['sku' => 'SKU: PREFIX-001', 'slug' => 'prefix-sku-product']);

        $this->actingAsAdmin($admin)
            ->put(route('admin.products.update', $product), [
                'category_id' => $product->category_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description ?? 'Desc',
                'price' => $product->price,
                'stock' => $product->stock,
                'section' => $product->section,
                'purchase_mode' => $product->purchase_mode,
                'pricing_type' => $product->pricing_type,
                'sku' => 'SKU: PREFIX-001',
                'is_active' => 1,
                'is_gallery_visible' => 1,
                'robots_index' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('PREFIX-001', $product->fresh()->sku);
    }

    public function test_admin_update_rejects_duplicate_sku(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = $this->createProduct(['sku' => 'EXISTING-SKU', 'slug' => 'existing-sku-product']);
        $product = $this->createProduct(['sku' => 'OTHER-SKU', 'slug' => 'other-sku-product']);

        $this->actingAsAdmin($admin)
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), [
                'category_id' => $product->category_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description ?? 'Desc',
                'price' => $product->price,
                'stock' => $product->stock,
                'section' => $product->section,
                'purchase_mode' => $product->purchase_mode,
                'pricing_type' => $product->pricing_type,
                'sku' => $existing->sku,
                'is_active' => 1,
                'is_gallery_visible' => 1,
                'robots_index' => 1,
            ])
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('sku');
    }
}
