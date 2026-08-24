<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Support\ServiceGallery;
use Database\Seeders\CatalogSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFillerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_sync_does_not_seed_forty_slim_profile_fillers(): void
    {
        $this->seed(CatalogSyncSeeder::class);

        $this->assertSame(
            3,
            Product::query()->whereHas('category', fn ($q) => $q->where('slug', 'slim-profile-door-system'))->count()
        );
    }

    public function test_is_catalog_filler_detects_auto_generated_service_gallery_skus(): void
    {
        $category = Category::factory()->create(['slug' => 'slim-profile-door-system', 'section' => 'studio']);

        $filler = Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Brushed Brass Frameless Portal',
            'slug' => 'brushed-brass-frameless-portal-04',
            'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
            'sku' => 'SSM-SD004',
        ]);

        $real = Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Slim Profile Pivot Door',
            'slug' => 'slim-profile-pivot-door',
            'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
            'sku' => 'SSM-SPD-001',
        ]);

        $this->assertTrue($filler->isCatalogFiller());
        $this->assertFalse($real->isCatalogFiller());
    }

    public function test_catalog_purge_filler_deactivates_filler_rows(): void
    {
        $category = Category::factory()->create(['slug' => 'partitions', 'section' => 'studio']);

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Champagne Brush Ice Series',
            'slug' => 'champagne-brush-ice-series-09',
            'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
            'sku' => 'SSM-P009',
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Owner Partition',
            'slug' => 'owner-partition',
            'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
            'sku' => 'VA-PART-001',
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->artisan('catalog:purge-filler')
            ->assertSuccessful();

        $this->assertFalse(Product::query()->where('slug', 'champagne-brush-ice-series-09')->value('is_active'));
        $this->assertTrue(Product::query()->where('slug', 'owner-partition')->value('is_active'));
    }

    public function test_slim_profile_studio_gallery_shows_real_category_products(): void
    {
        $service = Service::query()->firstOrCreate(
            ['slug' => 'slim-profile-door-system'],
            [
                'name' => 'Slim Profile Door Systems',
                'summary' => 'Studio gallery test service.',
                'lead_form' => 'popup',
                'is_active' => true,
            ]
        );

        $category = Category::query()->firstOrCreate(
            ['slug' => 'slim-profile-door-system'],
            [
                'name' => 'Slim Profile Door Systems',
                'section' => 'studio',
                'is_active' => true,
            ]
        );

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Owner Slim Pivot Door',
            'slug' => 'owner-slim-pivot-door',
            'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
            'sku' => 'VA-DOOR-001',
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Rose Gold Sliding Portal',
            'slug' => 'rose-gold-sliding-portal-08',
            'image' => 'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=800&q=80',
            'sku' => 'SSM-SD008',
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $products = ServiceGallery::productsFor($service);

        $this->assertCount(1, $products);
        $this->assertSame('Owner Slim Pivot Door', $products->first()->name);

        $this->get(route('studio.show', 'slim-profile-door-systems'))
            ->assertOk()
            ->assertSee('Owner Slim Pivot Door', false)
            ->assertDontSee('Rose Gold Sliding Portal', false);
    }
}
