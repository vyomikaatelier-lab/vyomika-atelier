<?php

namespace Tests\Feature;

use App\Http\Controllers\CollectionGalleryController;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Exhibition;
use App\Models\LegalPage;
use App\Models\MediaFile;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Support\CmsSettings;
use App\Support\ProductCatalog;
use App\Support\ServiceGallery;
use App\Support\Seo\PageSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end checks that an admin edit is what the public page renders, i.e.
 * the database is the source of truth rather than a config default.
 */
class AdminFrontendSyncTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function shopCategory(string $name, string $slug): Category
    {
        return $this->category($name, $slug, 'shop');
    }

    private function category(string $name, string $slug, string $section): Category
    {
        return Category::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'section' => $section,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    /** @param array<string, mixed> $overrides */
    private function createProductAsAdmin(Category $category, string $name, string $slug, array $overrides = []): void
    {
        $this->actingAsAdmin($this->admin())
            ->post(route('admin.products.store'), array_merge([
                'category_id' => $category->id,
                'name' => $name,
                'slug' => $slug,
                'price' => 48000,
                'stock' => 4,
                'section' => 'shop',
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => '1',
                'is_gallery_visible' => '1',
            ], $overrides))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_project_edit_appears_on_the_public_projects_gallery(): void
    {
        Storage::fake('public');

        $project = Project::query()->create([
            'project_name' => 'Andheri Loft',
            'description' => 'Old summary',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.projects.update', $project), [
                '_page_save' => '1',
                'project_name' => 'Andheri Loft',
                'description' => 'A brass and glass mezzanine study.',
                'city' => 'Mumbai',
                'is_active' => '1',
                'display_order' => 1,
            ])
            ->assertRedirect(route('admin.projects.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('A brass and glass mezzanine study.', false)
            ->assertSee('Andheri Loft');
    }

    public function test_deactivating_a_project_removes_it_from_the_storefront(): void
    {
        $project = Project::query()->create([
            'project_name' => 'Andheri Loft',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.projects.update', $project), [
                '_page_save' => '1',
                'project_name' => 'Andheri Loft',
                'display_order' => 1,
            ])
            ->assertRedirect(route('admin.projects.index'));

        $this->assertFalse($project->fresh()->is_active);
        $this->get('/projects/andheri-loft')->assertStatus(301)->assertRedirect(route('projects.index'));
        $this->get(route('projects.index'))->assertOk()->assertDontSee('Andheri Loft');
    }

    public function test_blog_edit_appears_on_the_public_post(): void
    {
        $post = BlogPost::query()->create([
            'title' => 'Living With Brass',
            'slug' => 'living-with-brass',
            'excerpt' => 'Old excerpt',
            'status' => 'published',
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.blog.update', $post), [
                '_page_save' => '1',
                'title' => 'Living With Brass',
                'excerpt' => 'Patina, care and pairing notes.',
                'status' => 'published',
                'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
                'hero_image_alt' => 'Living With Brass — Vyomika Atelier editorial',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.blog.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('blog.show', 'living-with-brass'))
            ->assertOk()
            ->assertSee('Patina, care and pairing notes.', false);
    }

    public function test_blog_index_does_not_fall_back_to_config_posts_once_rows_exist(): void
    {
        // A draft-only blog used to make the storefront read config('blog.posts'),
        // so admin edits were shadowed by the seeded copy.
        BlogPost::query()->create([
            'title' => 'Living With Brass',
            'slug' => 'living-with-brass',
            'excerpt' => 'Draft excerpt',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $response = $this->get(route('blog.index'))->assertOk();

        foreach (config('blog.posts', []) as $seeded) {
            if (filled($seeded['title'] ?? null)) {
                $response->assertDontSee($seeded['title'], false);
            }
        }

        $this->get(route('blog.show', 'living-with-brass'))->assertNotFound();
    }

    public function test_publishing_a_post_from_a_draft_only_blog_shows_the_admin_copy(): void
    {
        $post = BlogPost::query()->create([
            'title' => 'Living With Brass',
            'slug' => 'living-with-brass',
            'excerpt' => 'Draft excerpt',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.blog.update', $post), [
                '_page_save' => '1',
                'title' => 'Living With Brass',
                'excerpt' => 'Patina, care and pairing notes.',
                'status' => 'published',
                'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
                'hero_image_alt' => 'Living With Brass — Vyomika Atelier editorial',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.blog.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('blog.index'))->assertOk()->assertSee('Living With Brass');
        $this->get(route('blog.show', 'living-with-brass'))
            ->assertOk()
            ->assertSee('Patina, care and pairing notes.', false);
    }

    public function test_service_edit_appears_on_the_public_service_page(): void
    {
        $service = Service::query()->create([
            'name' => 'Fluted Screens',
            'slug' => 'fluted-screens',
            'summary' => 'Old summary',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.services.update', $service), [
                '_page_save' => '1',
                'name' => 'Fluted Screens',
                'slug' => 'fluted-screens',
                'summary' => 'Hand-finished fluted metal screens.',
                'lead_form' => 'popup',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.services.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('services.show', 'fluted-screens'))
            ->assertOk()
            ->assertSee('Hand-finished fluted metal screens.', false);

        $this->get(route('services.index'))->assertOk()->assertSee('Fluted Screens');
    }

    public function test_exhibition_edit_appears_on_the_about_page(): void
    {
        $exhibition = Exhibition::query()->create([
            'slug' => 'india-design-id',
            'name' => 'India Design ID',
            'city' => 'New Delhi',
            'year' => 2026,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.exhibitions.update', $exhibition), [
                '_page_save' => '1',
                'name' => 'India Design ID',
                'city' => 'New Delhi',
                'year' => 2026,
                'description' => 'Our debut showcase of PVD partitions.',
                'gallery_managed' => '1',
                'sort_order' => 1,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.exhibitions.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('India Design ID');
    }

    public function test_exhibition_storage_gallery_images_appear_on_the_about_page(): void
    {
        Storage::fake('public');

        $path = 'exhibitions/index-2023-gallery.jpg';
        Storage::disk('public')->put($path, 'fake-image-content');

        Exhibition::query()->create([
            'slug' => 'index-2023',
            'name' => 'INDEX 2023',
            'city' => 'Mumbai',
            'year' => 2023,
            'gallery' => [$path],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('INDEX 2023')
            ->assertSee(asset('storage/'.$path), false);
    }

    public function test_exhibition_cover_image_appears_alongside_gallery_on_about_page(): void
    {
        Storage::fake('public');

        $coverPath = 'exhibitions/cover-only.jpg';
        $galleryPath = 'exhibitions/gallery-only.jpg';
        Storage::disk('public')->put($coverPath, 'cover');
        Storage::disk('public')->put($galleryPath, 'gallery');

        Exhibition::query()->create([
            'slug' => 'uk-construction-week-2025',
            'name' => 'UK Construction Week',
            'city' => 'London',
            'year' => 2025,
            'cover_image' => $coverPath,
            'gallery' => [$galleryPath],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('UK Construction Week')
            ->assertSee(asset('storage/'.$coverPath), false)
            ->assertSee(asset('storage/'.$galleryPath), false)
            ->assertSee('am-about-gallery__featured', false);
    }

    public function test_legal_page_edit_appears_on_the_public_policy_page(): void
    {
        // The storefront resolves legal pages by the short config key.
        LegalPage::query()->updateOrCreate(
            ['slug' => 'privacy'],
            [
                'title' => 'Privacy Policy',
                'sections' => [['heading' => 'Intro', 'paragraphs' => ['Original']]],
            ]
        );

        $page = LegalPage::query()->where('slug', 'privacy')->firstOrFail();

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.legal.update', $page), [
                'title' => 'Privacy Policy',
                'section_headings' => ['Data we collect'],
                'section_paragraphs' => ['Only what is needed to fulfil your order.'],
            ])
            ->assertRedirect(route('admin.legal.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Data we collect')
            ->assertSee('Only what is needed to fulfil your order.', false);
    }

    public function test_brand_settings_appear_in_the_storefront_layout(): void
    {
        $this->actingAsAdmin($this->admin())
            ->put(route('admin.settings.update'), [
                'current_password' => 'password',
                'brand_name' => 'Vyomika Atelier',
                'phone' => '9812345678',
                'email' => 'hello@vyomika.test',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        CmsSettings::hydrate();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('hello@vyomika.test', false);
    }

    public function test_static_page_seo_edit_appears_in_page_meta(): void
    {
        $this->actingAsAdmin($this->admin())
            ->put(route('admin.static-pages.update', 'about'), [
                'meta_title' => 'About Vyomika Atelier — Metal Craft Studio',
                'meta_description' => 'Our studio, our makers, our finishes.',
                'robots' => 'index',
            ])
            ->assertRedirect(route('admin.static-pages.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('About Vyomika Atelier — Metal Craft Studio', false)
            ->assertSee('Our studio, our makers, our finishes.', false);
    }

    public function test_product_created_in_admin_appears_on_its_shop_collection_page(): void
    {
        $category = $this->shopCategory('Coffee Tables', 'coffee-tables');

        $this->createProductAsAdmin($category, 'Meridian Brass Coffee Table', 'meridian-brass-coffee-table');

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('Meridian Brass Coffee Table');
    }

    public function test_admin_sort_order_controls_collection_page_order(): void
    {
        $category = $this->shopCategory('Coffee Tables', 'coffee-tables');

        Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Lower Priority Table',
            'slug' => 'lower-priority-table',
            'sort_order' => 5,
            'is_gallery_visible' => true,
        ]);

        Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Higher Priority Table',
            'slug' => 'higher-priority-table',
            'sort_order' => 50,
            'is_gallery_visible' => true,
        ]);

        $products = CollectionGalleryController::galleryProductsForCategory(
            $category,
            ProductCatalog::productSlugsForShopPage('coffee-tables')
        );

        $this->assertSame('Higher Priority Table', $products->first()->name);
        $this->assertTrue($products->contains('name', 'Lower Priority Table'));
    }

    public function test_products_in_an_admin_created_category_appear_in_the_shop(): void
    {
        $category = $this->shopCategory('Steel Planters', 'steel-planters');

        $this->createProductAsAdmin($category, 'Terra Steel Planter', 'terra-steel-planter');

        $this->get(route('shop.show', 'steel-planters'))
            ->assertOk()
            ->assertSee('Terra Steel Planter');
    }

    public function test_product_created_in_admin_appears_on_the_studio_service_gallery(): void
    {
        Service::query()->create([
            'name' => 'PVD Partitions',
            'slug' => 'partitions',
            'summary' => 'Precision stainless partitions.',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $category = $this->category('PVD Partitions', 'partitions', 'studio');

        $this->createProductAsAdmin($category, 'Cascade Fluted Partition', 'cascade-fluted-partition', [
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
        ]);

        $this->get(route('studio.show', 'pvd-partitions'))
            ->assertOk()
            ->assertSee('Cascade Fluted Partition');
    }

    public function test_admin_sort_order_controls_studio_service_gallery_order(): void
    {
        $service = Service::query()->create([
            'name' => 'PVD Partitions',
            'slug' => 'partitions',
            'summary' => 'Precision stainless partitions.',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $category = $this->category('PVD Partitions', 'partitions', 'studio');

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Lower Partition',
            'slug' => 'lower-partition',
            'sort_order' => 3,
            'is_gallery_visible' => true,
        ]);

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Higher Partition',
            'slug' => 'higher-partition',
            'sort_order' => 30,
            'is_gallery_visible' => true,
        ]);

        $products = ServiceGallery::productsFor($service);

        $this->assertSame('Higher Partition', $products->first()->name);
    }

    public function test_deactivating_a_product_removes_it_from_the_collection_page(): void
    {
        $category = $this->shopCategory('Coffee Tables', 'coffee-tables');

        $this->createProductAsAdmin($category, 'Meridian Brass Coffee Table', 'meridian-brass-coffee-table');

        $product = Product::query()->where('slug', 'meridian-brass-coffee-table')->firstOrFail();

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.products.update', $product), [
                'category_id' => $category->id,
                'name' => 'Meridian Brass Coffee Table',
                'slug' => 'meridian-brass-coffee-table',
                'price' => 48000,
                'stock' => 4,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->is_active);

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertDontSee('Meridian Brass Coffee Table');
    }

    public function test_visiting_a_mirror_frame_page_does_not_overwrite_the_admin_product(): void
    {
        $catalog = require database_path('data/mirror-frames-catalog.php');
        $item = $catalog[0];

        $category = $this->shopCategory('Mirror Frames', 'mirror-frames');

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Arch Mirror — Champagne PVD',
            'slug' => $item['slug'],
            'price' => 21500,
            'stock' => 2,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => false,
            'is_gallery_visible' => true,
        ]);

        // A public GET must not reseed the row from the static catalog.
        $this->get(route('shop.mirror-frames.show', $item['slug']))->assertNotFound();

        $product->refresh();
        $this->assertSame('Arch Mirror — Champagne PVD', $product->name);
        $this->assertSame('21500.00', (string) $product->price);
        $this->assertFalse($product->is_active);
    }

    public function test_announcement_bar_edit_appears_in_the_storefront_layout(): void
    {
        $this->actingAsAdmin($this->admin())
            ->put(route('admin.settings.update'), [
                'current_password' => 'password',
                'brand_name' => 'Vyomika Atelier',
                'announcement_text' => 'Monsoon studio hours: 10am to 6pm',
                'announcement_link_label' => 'Plan a visit',
                'announcement_link_href' => '/contact',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        CmsSettings::hydrate();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Monsoon studio hours: 10am to 6pm', false);
    }

    public function test_category_rename_appears_in_the_shop_filter_list(): void
    {
        $category = $this->shopCategory('Coffee Tables', 'coffee-tables');

        $this->createProductAsAdmin($category, 'Meridian Brass Coffee Table', 'meridian-brass-coffee-table');

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.categories.update', $category), [
                'name' => 'Coffee Tables',
                'section' => 'shop',
                'description' => 'Low tables in brass and steel.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('Coffee Tables');
    }

    public function test_deactivating_a_category_hides_its_products_from_the_shop(): void
    {
        $category = $this->shopCategory('Coffee Tables', 'coffee-tables');

        $this->createProductAsAdmin($category, 'Meridian Brass Coffee Table', 'meridian-brass-coffee-table');

        $category->update(['is_active' => false]);

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertNotFound();
    }

    public function test_hiding_every_exhibition_empties_the_about_section(): void
    {
        $exhibition = Exhibition::query()->create([
            'slug' => 'india-design-id',
            'name' => 'India Design ID',
            'city' => 'New Delhi',
            'year' => 2026,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->get(route('about'))->assertOk()->assertSee('India Design ID');

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.exhibitions.update', $exhibition), [
                '_page_save' => '1',
                'name' => 'India Design ID',
                'city' => 'New Delhi',
                'year' => 2026,
                'gallery_managed' => '1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.exhibitions.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($exhibition->fresh()->is_active);

        // The config seed events must not reappear now that the admin owns rows.
        $response = $this->get(route('about'))->assertOk();
        $response->assertDontSee('India Design ID');

        foreach (config('about.exhibitions.events', []) as $seeded) {
            $response->assertDontSee($seeded['name']);
        }
    }

    public function test_hiding_every_project_empties_the_homepage_portfolio(): void
    {
        Project::query()->create([
            'project_name' => 'Andheri Loft',
            'is_active' => false,
            'display_order' => 1,
        ]);

        $response = $this->get(route('home'))->assertOk();

        foreach (config('site.portfolio', []) as $seeded) {
            if (filled($seeded['title'] ?? null)) {
                $response->assertDontSee($seeded['title'], false);
            }
        }
    }

    public function test_media_alt_text_edit_persists_and_shows_in_the_library(): void
    {
        Storage::fake('public');

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.media.store'), [
                'file' => UploadedFile::fake()->image('brass-console.jpg'),
                'alt' => 'Brass console table',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $media = MediaFile::query()->latest('id')->firstOrFail();
        $this->assertSame('Brass console table', $media->alt);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.media.update', $media), ['alt' => 'Brass console table, PVD finish'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Brass console table, PVD finish', $media->fresh()->alt);

        $this->actingAsAdmin($this->admin())
            ->get(route('admin.media.index'))
            ->assertOk()
            ->assertSee('Brass console table, PVD finish', false);
    }

    public function test_collection_page_edit_appears_on_the_public_collection(): void
    {
        $this->shopCategory('Coffee Tables', 'coffee-tables');

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.collection-pages.update', 'coffee-tables'), [
                '_page_save' => '1',
                'hero_title' => 'Low tables, high craft',
                'hero_subtitle' => 'Brass and steel coffee tables built to order.',
            ])
            ->assertSessionHasNoErrors();

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('Low tables, high craft', false);
    }

    public function test_independent_landing_edit_appears_on_the_railings_page(): void
    {
        $this->actingAsAdmin($this->admin())
            ->put(route('admin.independent-pages.update', 'railings'), [
                '_landing_save' => '1',
                'hero_title' => 'Railings engineered for the monsoon',
                'hero_subtitle' => 'Stainless and glass balustrades, installed pan-India.',
            ])
            ->assertSessionHasNoErrors();

        $this->get(route('railings.index'))
            ->assertOk()
            ->assertSee('Railings engineered for the monsoon', false);
    }

    public function test_url_redirect_created_in_admin_takes_effect(): void
    {
        $this->actingAsAdmin($this->admin())
            ->post(route('admin.redirects.store'), [
                'from_path' => '/old-partitions',
                'to_url' => '/shop',
                'status_code' => 301,
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->get('/old-partitions')->assertRedirect('/shop');
    }

    public function test_seo_defaults_come_from_the_database_when_config_is_not_hydrated(): void
    {
        $this->actingAsAdmin($this->admin())
            ->put(route('admin.settings.update'), [
                'current_password' => 'password',
                'brand_name' => 'Vyomika Atelier',
                'default_meta_title' => 'Vyomika Atelier — Studio Metalwork',
                'default_meta_description' => 'PVD partitions, doors and metal furniture.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Simulate a request where boot-time hydration did not run: the stored
        // value must still beat the config seed.
        config(['site.seo' => [
            'default_title' => 'Stale config title',
            'default_description' => 'Stale config description',
        ]]);

        $defaults = PageSeo::siteDefaults();
        $this->assertSame('Vyomika Atelier — Studio Metalwork', $defaults['title']);
        $this->assertSame('PVD partitions, doors and metal furniture.', $defaults['description']);

        $this->get(route('contact.index'))
            ->assertOk()
            ->assertSee('PVD partitions, doors and metal furniture.', false)
            ->assertDontSee('Stale config', false);
    }

    public function test_page_hero_edit_appears_on_the_about_page(): void
    {
        Storage::fake('public');

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.page-heroes.update', 'about'), [
                '_page_save' => '1',
                'hero_title' => 'Metal, made deliberately',
                'hero_subtitle' => 'A studio practice in brass, steel and stone.',
            ])
            ->assertRedirect(route('admin.page-heroes.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Metal, made deliberately', false);
    }
}
