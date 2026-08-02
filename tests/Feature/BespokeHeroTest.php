<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BespokeHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_bespoke_collection_hero_upload_syncs_to_shop_page(): void
    {
        Storage::fake('public');
        Category::query()->firstOrCreate(
            ['slug' => 'bespoke-metal-furniture'],
            ['name' => 'Bespoke Metal Furniture', 'section' => 'shop', 'is_active' => true]
        );
        $admin = User::factory()->admin()->create();
        $image = UploadedFile::fake()->image('bespoke-hero.jpg', 600, 480);

        $this->actingAsAdmin($admin)->put(route('admin.collection-pages.update', 'bespoke-metal-furniture'), [
            '_page_save' => '1',
            'hero_title_line1' => 'Bespoke',
            'hero_title_accent' => 'Hero',
            'hero_layout' => 'compact',
            'hero_image_file' => $image,
        ])->assertSessionHasNoErrors();

        $stored = data_get(SiteSetting::getValue('collection_pages', []), 'bespoke-metal-furniture.hero');
        $this->assertSame('Bespoke', $stored['title_line1'] ?? null);
        $this->assertNotEmpty($stored['image'] ?? null);

        $this->get(route('shop.show', 'bespoke-metal-furniture'))
            ->assertOk()
            ->assertSee('Bespoke', false)
            ->assertSee('am-shop-category-hero--compact', false)
            ->assertSee($stored['image'], false);
    }

    public function test_legacy_metal_furniture_collection_override_is_merged_for_bespoke_page(): void
    {
        SiteSetting::setValue('collection_pages', [
            'metal-furniture' => [
                'hero' => [
                    'title_line1' => 'Legacy',
                    'title_accent' => 'Metal',
                    'hero_layout' => 'compact',
                    'image' => 'collections/legacy-hero.jpg',
                ],
            ],
        ]);

        Category::query()->firstOrCreate(
            ['slug' => 'bespoke-metal-furniture'],
            ['name' => 'Bespoke Metal Furniture', 'section' => 'shop', 'is_active' => true]
        );

        $this->get(route('shop.show', 'bespoke-metal-furniture'))
            ->assertOk()
            ->assertSee('Legacy', false)
            ->assertSee('Metal', false)
            ->assertSee('collections/legacy-hero.jpg', false);
    }

    public function test_service_admin_hero_syncs_to_bespoke_shop_collection_page(): void
    {
        Storage::fake('public');
        Category::query()->firstOrCreate(
            ['slug' => 'bespoke-metal-furniture'],
            ['name' => 'Bespoke Metal Furniture', 'section' => 'shop', 'is_active' => true]
        );

        $service = Service::query()->create([
            'name' => 'Bespoke Metal Furniture',
            'slug' => 'bespoke-metal-furniture',
            'lead_form' => 'inline',
            'is_active' => false,
        ]);

        $admin = User::factory()->admin()->create();
        $image = UploadedFile::fake()->image('service-bespoke.jpg', 600, 480);

        $this->actingAsAdmin($admin)->put(route('admin.services.update', $service), [
            'name' => 'Bespoke Metal Furniture',
            'slug' => 'bespoke-metal-furniture',
            'lead_form' => 'inline',
            'is_active' => '0',
            'hero_title_line1' => 'From Service',
            'hero_title_accent' => 'Admin',
            'hero_layout' => 'compact',
            'hero_image_file' => $image,
        ])->assertSessionHasNoErrors();

        $collectionHero = data_get(SiteSetting::getValue('collection_pages', []), 'bespoke-metal-furniture.hero');
        $this->assertSame('From Service', $collectionHero['title_line1'] ?? null);
        $this->assertNotEmpty($collectionHero['image'] ?? null);

        $this->get(route('shop.show', 'bespoke-metal-furniture'))
            ->assertOk()
            ->assertSee('From Service', false)
            ->assertSee($collectionHero['image'], false);
    }
}
