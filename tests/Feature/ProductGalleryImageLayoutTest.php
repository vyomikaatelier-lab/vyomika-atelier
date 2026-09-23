<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Support\ProductImageSizes;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductGalleryImageLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_stylesheet_uses_cover_for_gallery_and_shop_cards_and_contain_for_pdp(): void
    {
        $css = file_get_contents(public_path('css/amerce.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.am-product-card__thumb img\s*\{[^}]*object-fit:\s*cover/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-design-gallery__media img\s*\{[^}]*object-fit:\s*cover/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-pdp__main-img\s*\{[^}]*object-fit:\s*contain/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-section__body \.am-product-card__thumb\s*\{[^}]*aspect-ratio:\s*3\s*\/\s*4/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-section__body \.am-product-card__thumb img\s*\{[^}]*object-fit:\s*cover/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-section__body \.am-product-card__thumb\s*\{[^}]*padding:\s*0/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-design-gallery__media\s*\{[^}]*aspect-ratio:\s*3\s*\/\s*4/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-design-gallery--portrait \.am-design-gallery__media\s*\{[^}]*aspect-ratio:\s*3\s*\/\s*4/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-work-gallery__media\s*\{[^}]*aspect-ratio:\s*3\s*\/\s*4/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-work-gallery__media img\s*\{[^}]*object-fit:\s*cover/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-studio-spotlight__media\s*\{[^}]*aspect-ratio:\s*3\s*\/\s*4/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.am-studio-spotlight__media img\s*\{[^}]*object-fit:\s*cover/',
            $css
        );
        $this->assertStringNotContainsString(
            '.am-studio-spotlight:first-child .am-studio-spotlight__media { aspect-ratio: 16 / 9; }',
            $css
        );
    }

    public function test_stylesheet_defines_collection_gallery_three_column_grid(): void
    {
        $css = file_get_contents(public_path('css/amerce.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.am-design-gallery__grid\.am-collection-gallery-grid\s*\{[^}]*grid-template-columns:\s*1fr/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width:\s*1024px\)\s*\{[^}]*\.am-design-gallery__grid\.am-collection-gallery-grid[^}]*grid-template-columns:\s*repeat\(3,\s*minmax\(0,\s*1fr\)\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width:\s*640px\)\s*\{[^}]*\.am-design-gallery__grid\.am-collection-gallery-grid[^}]*grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)/s',
            $css
        );
        $this->assertStringNotContainsString(
            'grid-template-columns: repeat(5, 1fr)',
            $css
        );
    }

    public function test_shop_category_gallery_uses_three_column_grid_class(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Grid Layout Handle',
            'slug' => 'grid-layout-handle',
            'description' => 'Three-column gallery layout test.',
            'price' => 1200,
            'stock' => 20,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->get(route('shop.show', 'door-handles'))
            ->assertOk()
            ->assertSee('am-collection-gallery-grid', false)
            ->assertSee('am-design-gallery--portrait', false);
    }

    public function test_shop_category_page_uses_square_cover_product_cards(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Category Gallery Coffee Table',
            'slug' => 'category-gallery-coffee-table',
            'description' => 'Category gallery card layout test.',
            'price' => 15000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('am-collection-gallery-grid', false)
            ->assertSee('am-design-gallery__card', false)
            ->assertSee('Category Gallery Coffee Table', false);
    }

    public function test_homepage_collection_row_renders_published_category_tiles(): void
    {
        foreach (['mirror-frames', 'corner-tables', 'coffee-tables'] as $slug) {
            $category = Category::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => StorefrontRoutes::shopCategoryLabel($slug), 'section' => 'shop', 'is_active' => true]
            );

            Product::query()->create([
                'category_id' => $category->id,
                'name' => StorefrontRoutes::shopCategoryLabel($slug).' Tile Product',
                'slug' => $slug.'-tile-product',
                'price' => 12000,
                'stock' => 2,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => true,
                'is_gallery_visible' => true,
            ]);
        }

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('am-cat-scroll', $html);
        $this->assertStringContainsString('Mirror Frames', $html);
        $this->assertStringContainsString('Corner Tables', $html);
        $this->assertStringContainsString('Coffee Tables', $html);
        $this->assertStringContainsString('/images/shop-heroes/mirror-frames-hero.png', $html);
        $this->assertStringNotContainsString('am-product-grid--with-banner', $html);
        $this->assertStringNotContainsString('PVD Craft, Elevated for Interiors', $html);
        $this->assertStringContainsString('Bespoke Studio Capabilities', $html);
        $this->assertStringContainsString('am-studio-spotlights--portrait', $html);
        $this->assertStringContainsString('am-studio-spotlight--portrait', $html);
        $this->assertStringContainsString('PVD Partitions', $html);
        $this->assertStringContainsString('Designer Railings', $html);
        $this->assertStringContainsString('Corten Steel', $html);
        $this->assertStringContainsString('/studio/pvd-partitions', $html);
        $this->assertStringContainsString('/railings', $html);
        $this->assertStringContainsString('/corten-steel', $html);
        $this->assertStringContainsString('Sq Ft Calculator', $html);
        $this->assertStringContainsString('am-studio-spotlight__form', $html);
        $this->assertStringContainsString('am-studio-spotlight-form', $html);
        $this->assertStringContainsString('Railing quick quote', $html);
        $this->assertStringContainsString('Corten quick quote', $html);
        $this->assertStringContainsString('name="service_slug" value="railings"', $html);
        $this->assertStringContainsString('name="service_slug" value="corten-steel-facade"', $html);
        $this->assertStringContainsString('name="type" value="service_inquiry"', $html);
        $this->assertStringContainsString('The Vyomika Difference', $html);
    }

    public function test_homepage_studio_spotlights_prefer_service_and_landing_page_hero_images(): void
    {
        Service::query()->create([
            'name' => 'PVD Partitions',
            'slug' => 'partitions',
            'summary' => 'Studio partitions.',
            'image' => 'storage/services/partitions-spotlight.jpg',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        SiteSetting::setValue('landing_pages', [
            'railings' => [
                'hero' => ['image' => 'storage/landing-pages/railings-spotlight.jpg'],
            ],
            'corten-steel' => [
                'hero' => ['image' => 'storage/landing-pages/corten-spotlight.jpg'],
            ],
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('/storage/services/partitions-spotlight.jpg', $html);
        $this->assertStringContainsString('/storage/landing-pages/railings-spotlight.jpg', $html);
        $this->assertStringContainsString('/storage/landing-pages/corten-spotlight.jpg', $html);
    }

    public function test_homepage_featured_products_use_grid_without_side_banner(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Homepage Featured Handle',
            'slug' => 'homepage-featured-handle',
            'description' => 'Homepage featured card layout test.',
            'price' => 1200,
            'stock' => 20,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_featured' => true,
            'is_gallery_visible' => true,
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('am-product-grid--portrait', $html);
        $this->assertStringContainsString('am-product-card__thumb', $html);
        $this->assertStringContainsString('Homepage Featured Handle', $html);
        $this->assertStringContainsString('am-product-grid--6', $html);
        $this->assertStringNotContainsString('am-product-grid--with-banner', $html);
        $this->assertStringNotContainsString('am-product-banner', $html);
        $this->assertStringNotContainsString('Discover Your Signature Finish', $html);
    }

    public function test_mirror_frames_gallery_uses_portrait_cover_layout(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Square Mirror Frame',
            'slug' => 'arched-wall-mirror',
            'description' => 'Mirror frames gallery card.',
            'price' => 12000,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->get(route('shop.mirror-frames.index'))
            ->assertOk()
            ->assertSee('am-design-gallery--portrait', false)
            ->assertSee('Square Mirror Frame', false);
    }

    /** @return array<string, array{0: string, 1: string, 2: array<string, string>}> */
    public static function shopCategoryGalleryRoutesProvider(): array
    {
        $routes = [];

        foreach (StorefrontRoutes::shopCategorySlugs() as $slug) {
            if ($slug === 'mirror-frames') {
                $routes["mirror frames"] = [$slug, 'shop.mirror-frames.index', []];

                continue;
            }

            $routes[$slug] = [$slug, 'shop.show', ['slug' => $slug]];
        }

        return $routes;
    }

    /**
     * @dataProvider shopCategoryGalleryRoutesProvider
     */
    public function test_all_shop_category_galleries_use_portrait_cover_layout(
        string $slug,
        string $routeName,
        array $routeParams,
    ): void {
        if ($slug !== 'mirror-frames') {
            $category = Category::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => StorefrontRoutes::shopCategoryLabel($slug),
                    'section' => 'shop',
                    'is_active' => true,
                ]
            );

            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Gallery '.$slug,
                'slug' => 'gallery-'.$slug,
                'description' => 'Portrait gallery card for '.$slug.'.',
                'price' => 15000,
                'stock' => 5,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => true,
                'is_gallery_visible' => true,
            ]);
        } else {
            $category = Category::query()->firstOrCreate(
                ['slug' => 'mirror-frames'],
                ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
            );

            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Gallery mirror-frames',
                'slug' => 'arched-wall-mirror',
                'description' => 'Portrait mirror frames gallery card.',
                'price' => 12000,
                'stock' => 10,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => true,
                'is_gallery_visible' => true,
            ]);
        }

        $response = $this->get(route($routeName, $routeParams))->assertOk();

        $response->assertSee('am-design-gallery--portrait', false);
        $response->assertSee('am-collection-gallery-grid', false);

        if ($slug === 'mirror-frames') {
            $response->assertSee('id="mirror-designs"', false);
        } else {
            $response->assertSee('id="collection-gallery"', false);
        }
    }

    public function test_shop_category_gallery_uses_portrait_cover_layout(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Gallery Coffee Table',
            'slug' => 'gallery-coffee-table',
            'description' => 'Portrait gallery card test product.',
            'price' => 15000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('id="collection-gallery"', false)
            ->assertSee('am-design-gallery--portrait', false)
            ->assertSee('Gallery Coffee Table', false);
    }

    public function test_door_handles_shop_category_gallery_uses_portrait_cover_layout(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Brass Pull Handle',
            'slug' => 'brass-pull-handle',
            'description' => 'Door handle gallery card.',
            'price' => 1200,
            'stock' => 20,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->get(route('shop.show', 'door-handles'))
            ->assertOk()
            ->assertSee('am-design-gallery--portrait', false)
            ->assertSee('Brass Pull Handle', false);
    }

    public function test_admin_product_form_shows_recommended_image_dimensions(): void
    {
        $admin = \App\Models\User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee(ProductImageSizes::galleryDimensionsLabel(), false)
            ->assertSee(ProductImageSizes::galleryLargeDimensionsLabel(), false)
            ->assertSee(ProductImageSizes::pdpDimensionsLabel(), false)
            ->assertSee('fill the frame edge to edge', false);
    }
}
