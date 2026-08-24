<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
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
            'image' => '/images/test-product.jpg',
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->get(route('shop.show', 'door-handles'))
            ->assertOk()
            ->assertSee('am-collection-gallery-grid', false)
            ->assertSee('am-design-gallery--portrait', false);
    }

    public function test_shop_index_uses_square_cover_product_cards(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Shop Index Coffee Table',
            'slug' => 'shop-index-coffee-table',
            'description' => 'Shop index card layout test.',
            'price' => 15000,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'image' => '/images/test-product.jpg',
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('am-product-grid--shop', false)
            ->assertSee('am-product-card__thumb', false)
            ->assertSee('Shop Index Coffee Table', false);
    }

    public function test_homepage_featured_products_use_square_cover_product_cards(): void
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
            'image' => '/images/test-product.jpg',
            'is_active' => true,
            'is_featured' => true,
            'is_gallery_visible' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('am-product-card__thumb', false)
            ->assertSee('Homepage Featured Handle', false);
    }

    public function test_homepage_new_products_section_lists_newest_product_first(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Older Coffee Table',
            'slug' => 'older-coffee-table',
            'price' => 12000,
            'stock' => 2,
            'sort_order' => 1,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'image' => '/images/test-product.jpg',
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Newest Coffee Table',
            'slug' => 'newest-coffee-table',
            'price' => 14000,
            'stock' => 2,
            'sort_order' => 2,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'image' => '/images/test-product.jpg',
            'is_active' => true,
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Older Coffee Table'),
            strpos($html, 'Newest Coffee Table'),
            'Homepage product grid should render newest products before older ones (left to right).'
        );
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
            'image' => '/images/test-product.jpg',
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
            'image' => '/images/test-product.jpg',
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
            'image' => '/images/test-product.jpg',
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
            'image' => '/images/test-product.jpg',
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
            'image' => '/images/test-product.jpg',
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
