<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductImageSizes;
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
    }

    public function test_shop_category_gallery_uses_square_cover_layout(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Gallery Coffee Table',
            'slug' => 'gallery-coffee-table',
            'description' => 'Square gallery card test product.',
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
            ->assertSee('am-design-gallery--square', false)
            ->assertSee('Gallery Coffee Table', false);
    }

    public function test_door_handles_shop_category_gallery_uses_square_cover_layout(): void
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
            ->assertSee('am-design-gallery--square', false)
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
