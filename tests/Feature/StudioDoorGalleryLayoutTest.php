<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Support\ServiceGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioDoorGalleryLayoutTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{service: Service, category: Category, product: Product} */
    private function studioGalleryFixtures(string $serviceSlug, string $studioUrl, string $categorySlug, string $productName, string $productSlug): array
    {
        $service = Service::query()->updateOrCreate(
            ['slug' => $serviceSlug],
            [
                'name' => ucwords(str_replace('-', ' ', $serviceSlug)),
                'summary' => 'Studio gallery test service.',
                'lead_form' => 'popup',
                'is_active' => true,
            ]
        );

        $category = Category::query()->updateOrCreate(
            ['slug' => $categorySlug],
            [
                'name' => ucwords(str_replace('-', ' ', $categorySlug)),
                'section' => 'studio',
                'is_active' => true,
            ]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => $productName,
            'slug' => $productSlug,
            'description' => 'Gallery layout regression product.',
            'price' => 45999,
            'stock' => 5,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        return compact('service', 'category', 'product');
    }

    public function test_portrait_gallery_service_slugs_include_door_studio_pages(): void
    {
        $this->assertSame([
            'slim-profile-door-system',
            'main-entrance-pvd-doors',
        ], ServiceGallery::portraitGalleryServiceSlugs());
    }

    public function test_slim_profile_studio_gallery_uses_portrait_contain_layout(): void
    {
        $this->studioGalleryFixtures(
            'slim-profile-door-system',
            'slim-profile-door-systems',
            'slim-profile-door-system',
            'Slim Profile Pivot Door',
            'slim-profile-pivot-door',
        );

        $this->get(route('studio.show', 'slim-profile-door-systems'))
            ->assertOk()
            ->assertSee('id="studio-gallery"', false)
            ->assertSee('am-design-gallery--portrait', false)
            ->assertSee('am-design-gallery__media', false)
            ->assertSee('Slim Profile Pivot Door', false);
    }

    public function test_main_entrance_pvd_studio_gallery_uses_portrait_contain_layout(): void
    {
        $this->studioGalleryFixtures(
            'main-entrance-pvd-doors',
            'main-entrance-pvd-doors',
            'main-entrance-pvd-doors',
            'Grand Entrance Door',
            'grand-entrance-door',
        );

        $this->get(route('studio.show', 'main-entrance-pvd-doors'))
            ->assertOk()
            ->assertSee('am-design-gallery--portrait', false)
            ->assertSee('Grand Entrance Door', false);
    }

    public function test_partitions_studio_gallery_keeps_default_landscape_cover_layout(): void
    {
        $this->studioGalleryFixtures(
            'partitions',
            'pvd-partitions',
            'partitions',
            'Cascade Fluted Partition',
            'cascade-fluted-partition',
        );

        $this->get(route('studio.show', 'pvd-partitions'))
            ->assertOk()
            ->assertSee('am-design-gallery--service', false)
            ->assertDontSee('am-design-gallery--portrait', false)
            ->assertSee('Cascade Fluted Partition', false);
    }
}
