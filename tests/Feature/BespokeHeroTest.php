<?php

namespace Tests\Feature;

use App\Models\Category;
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

    public function test_compact_hero_save_clears_legacy_tablet_and_mobile_images(): void
    {
        SiteSetting::setValue('collection_pages', [
            'bespoke-metal-furniture' => [
                'hero' => [
                    'hero_layout' => 'compact',
                    'image' => 'collections/desktop.jpg',
                    'image_tablet' => 'collections/tablet.jpg',
                    'image_mobile' => 'collections/mobile.jpg',
                ],
            ],
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->put(route('admin.collection-pages.update', 'bespoke-metal-furniture'), [
            '_page_save' => '1',
            'hero_layout' => 'compact',
            'hero_title_line1' => 'Still Compact',
        ])->assertSessionHasNoErrors();

        $stored = data_get(SiteSetting::getValue('collection_pages', []), 'bespoke-metal-furniture.hero');
        $this->assertSame('Still Compact', $stored['title_line1'] ?? null);
        $this->assertArrayNotHasKey('image_tablet', $stored);
        $this->assertArrayNotHasKey('image_mobile', $stored);
    }
}
