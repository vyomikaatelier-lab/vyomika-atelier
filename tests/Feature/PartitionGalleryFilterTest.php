<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Support\ServiceGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartitionGalleryFilterTest extends TestCase
{
    use RefreshDatabase;

    private function partitionsServiceAndCategory(): array
    {
        $service = Service::query()->firstOrCreate(
            ['slug' => 'partitions'],
            [
                'name' => 'PVD Partitions',
                'summary' => 'Precision stainless partitions.',
                'lead_form' => 'popup',
                'is_active' => true,
            ]
        );

        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            [
                'name' => 'PVD Partitions',
                'section' => 'studio',
                'is_active' => true,
            ]
        );

        return [$service, $category];
    }

    public function test_studio_partitions_gallery_excludes_catalog_filler_products(): void
    {
        [$service, $category] = $this->partitionsServiceAndCategory();

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Aurelian Gold PVD Steel Mesh Partition',
            'slug' => 'aurelian-gold-pvd-steel-mesh-partition',
            'image' => 'products/aurelian-gold.jpg',
            'sku' => 'VA-PART-001',
            'is_gallery_visible' => true,
        ]);

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Champagne Brush Mesh Screen',
            'slug' => 'champagne-brush-mesh-screen-14',
            'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
            'sku' => 'SSM-P014',
            'is_gallery_visible' => true,
        ]);

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => '',
            'slug' => 'broken-placeholder-partition',
            'image' => null,
            'sku' => 'SSM-BROKEN',
            'is_gallery_visible' => true,
        ]);

        $products = ServiceGallery::productsFor($service);

        $this->assertCount(1, $products);
        $this->assertSame('Aurelian Gold PVD Steel Mesh Partition', $products->first()->name);
    }

    public function test_studio_partitions_page_hides_catalog_filler_products(): void
    {
        [$service, $category] = $this->partitionsServiceAndCategory();

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Geometric Gold PVD Partition',
            'slug' => 'geometric-gold-pvd-partition',
            'image' => 'products/geometric-gold.jpg',
            'sku' => 'VA-PART-002',
            'is_gallery_visible' => true,
        ]);

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Ice Matrix Ice Series',
            'slug' => 'ice-matrix-ice-series-22',
            'image' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80',
            'sku' => 'SSM-P022',
            'is_gallery_visible' => true,
        ]);

        $this->get(route('studio.show', 'pvd-partitions'))
            ->assertOk()
            ->assertSee('Geometric Gold PVD Partition', false)
            ->assertDontSee('Ice Matrix Ice Series', false)
            ->assertDontSee('Champagne Brush Mesh Screen', false);
    }

    public function test_catalog_hide_filler_command_hides_filler_rows(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            [
                'name' => 'PVD Partitions',
                'section' => 'studio',
                'is_active' => true,
            ]
        );

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Champagne Brush Ice Series',
            'slug' => 'champagne-brush-ice-series-09',
            'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
            'sku' => 'SSM-P009',
            'is_gallery_visible' => true,
        ]);

        $this->artisan('catalog:hide-filler')
            ->assertSuccessful();

        $this->assertFalse(
            Product::query()->where('slug', 'champagne-brush-ice-series-09')->value('is_gallery_visible')
        );
    }
}
