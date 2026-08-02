<?php



namespace Tests\Feature;



use App\Models\Category;

use App\Models\SiteSetting;

use App\Models\User;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Http\UploadedFile;

use Illuminate\Support\Facades\Storage;

use Tests\TestCase;



class CollectionPageAdminTest extends TestCase

{

    use RefreshDatabase;



    public function test_admin_can_upload_responsive_collection_hero_images(): void

    {

        Storage::fake('public');

        $admin = User::factory()->admin()->create();

        $desktop = UploadedFile::fake()->image('coffee-desktop.jpg', 1600, 900);

        $tablet = UploadedFile::fake()->image('coffee-tablet.jpg', 1200, 800);



        $this->actingAsAdmin($admin)->put(route('admin.collection-pages.update', 'coffee-tables'), [

            'hero_title' => 'Coffee Tables Hero',

            'hero_image_file' => $desktop,

            'hero_image_tablet_file' => $tablet,

        ])->assertRedirect(route('admin.collection-pages.index'));



        $stored = data_get(SiteSetting::getValue('collection_pages', []), 'coffee-tables.hero');

        $this->assertSame('Coffee Tables Hero', $stored['title'] ?? null);

        $this->assertNotEmpty($stored['image'] ?? null);

        $this->assertNotEmpty($stored['image_tablet'] ?? null);

        Storage::disk('public')->assertExists($stored['image']);

        Storage::disk('public')->assertExists($stored['image_tablet']);



        $this->get(route('shop.show', 'coffee-tables'))

            ->assertOk()

            ->assertSee('Coffee Tables Hero', false)

            ->assertSee('am-shop-category-hero', false)

            ->assertSee('am-shop-category-hero__media', false)

            ->assertSee('am-shop-category-hero__content', false)

            ->assertSee('<picture>', false)

            ->assertSee($stored['image'], false)

            ->assertSee($stored['image_tablet'], false)

            ->assertDontSee('am-shop-category-hero--artwork', false);

    }



    public function test_admin_can_save_structured_collection_hero_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->put(route('admin.collection-pages.update', 'coffee-tables'), [
            'hero_eyebrow' => 'Custom Eyebrow',
            'hero_title_line1' => 'Custom',
            'hero_title_accent' => 'Coffee',
            'hero_title_line2' => 'Tables',
            'hero_tagline' => 'Crafted to be',
            'hero_tagline_accent' => 'yours.',
            'hero_highlights' => "Line one\nLine two",
            'hero_footer_tagline' => 'Bold forms.',
            'hero_footer_tagline_accent' => 'Built to last.',
            'hero_cta_primary_label' => 'Browse',
            'hero_cta_primary_href' => '#collection-gallery',
            'hero_layout' => 'compact',
            'hero_image_position' => 'left',
        ])->assertRedirect(route('admin.collection-pages.index'));

        $stored = data_get(SiteSetting::getValue('collection_pages', []), 'coffee-tables.hero');
        $this->assertSame('Custom Eyebrow', $stored['eyebrow'] ?? null);
        $this->assertSame('Coffee', $stored['title_accent'] ?? null);
        $this->assertSame('yours.', $stored['tagline_accent'] ?? null);
        $this->assertSame(['Line one', 'Line two'], $stored['highlights'] ?? null);
        $this->assertSame('compact', $stored['hero_layout'] ?? null);
        $this->assertSame('left', $stored['image_position'] ?? null);

        Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('Custom Eyebrow', false)
            ->assertSee('Coffee', false)
            ->assertSee('yours.', false)
            ->assertSee('am-shop-category-hero--compact', false)
            ->assertSee('am-shop-category-hero--image-left', false);
    }

    public function test_shop_category_pages_render_split_hero_layout(): void
    {
        Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('am-shop-category-hero', false)
            ->assertSee('am-shop-category-hero__media', false)
            ->assertSee('am-shop-category-hero__content', false)
            ->assertDontSee('am-shop-category-hero--artwork', false)
            ->assertDontSee('am-mirror-frames-hero', false);
    }

    public function test_shop_category_hero_uses_public_shop_hero_images(): void
    {
        Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('am-shop-category-hero__media', false)
            ->assertSee('/images/shop-heroes/coffee-tables-hero.png', false)
            ->assertSee('<picture>', false)
            ->assertDontSee('am-shop-category-hero--artwork', false)
            ->assertDontSee('/storage/images/shop-heroes/coffee-tables-hero.png', false);
    }

    public function test_bespoke_collection_admin_shows_compact_single_hero_upload_despite_legacy_override(): void
    {
        SiteSetting::setValue('collection_pages', [
            'metal-furniture' => [
                'hero' => [
                    'title_line1' => 'Legacy',
                    'hero_layout' => 'default',
                    'image' => 'collections/legacy.jpg',
                    'image_tablet' => 'collections/legacy-tablet.jpg',
                ],
            ],
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->get(route('admin.collection-pages.edit', 'bespoke-metal-furniture'))
            ->assertOk()
            ->assertSee('Hero image (all devices)', false)
            ->assertSee('600 × 480 px', false)
            ->assertSee('One image 600×480', false)
            ->assertDontSee('Tablet / iPad image', false)
            ->assertDontSee('Mobile image (phones', false);
    }
}


