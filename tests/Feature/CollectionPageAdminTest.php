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

        ])->assertRedirect(route('admin.collection-pages.edit', ['slug' => 'coffee-tables', 'saved' => 1]));



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
}


