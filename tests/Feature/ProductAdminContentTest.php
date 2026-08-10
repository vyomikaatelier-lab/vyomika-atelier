<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAdminContentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function productPayload(Category $category, Product $product, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => $product->price,
            'compare_price' => '',
            'stock' => $product->stock,
            'section' => $product->section,
            'purchase_mode' => $product->purchase_mode,
            'pricing_type' => $product->pricing_type,
            'is_active' => '1',
            'is_gallery_visible' => '1',
        ], $overrides);
    }

    public function test_admin_can_save_product_page_content_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'Partitions', 'section' => 'studio', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Wave Partition',
            'slug' => 'wave-partition',
            'description' => 'Original description',
            'price' => 1800,
            'stock' => 5,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), $this->productPayload($category, $product, [
            'description' => 'Updated studio description',
            'headline_text' => 'Custom headline for PDP',
            'swatches_note' => 'Custom swatch note for buyers',
            'tab_specifications' => "Material: Grade 304 stainless\nFinish: PVD coated\nLead time: 3–4 weeks",
            'tab_packaging' => '<h3>Custom packaging</h3><p>Extra packaging details.</p>',
            'tab_shipping' => '<h3>Custom shipping</h3><p>Express metro delivery available.</p>',
        ]))->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh();

        $this->assertSame('Updated studio description', $product->description);
        $this->assertSame('Custom headline for PDP', $product->headline_text);
        $this->assertSame('Custom swatch note for buyers', $product->swatches_note);
        $this->assertSame("Material: Grade 304 stainless\nFinish: PVD coated\nLead time: 3–4 weeks", $product->tab_specifications);
        $this->assertSame("Custom packaging\nExtra packaging details.", $product->tab_packaging);
        $this->assertSame("Custom shipping\nExpress metro delivery available.", $product->tab_shipping);
        $this->assertSame([
            'Material: Grade 304 stainless',
            'Finish: PVD coated',
            'Lead time: 3–4 weeks',
        ], $product->specificationLines());

        $response = $this->get(route('shop.show', $product->slug));
        $response->assertOk()
            ->assertSee('Updated studio description', false)
            ->assertSee('Custom headline for PDP', false)
            ->assertSee('Custom swatch note for buyers', false)
            ->assertSee('Material: Grade 304 stainless', false)
            ->assertSee('Finish: PVD coated', false)
            ->assertSee('Lead time: 3–4 weeks', false)
            ->assertSee('Extra packaging details.', false)
            ->assertSee('Express metro delivery available.', false)
            ->assertSee('<ul class="am-pdp-tabs__care-list">', false);
    }

    public function test_pdp_hides_headline_line_when_sku_and_headline_text_are_blank(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'Partitions', 'section' => 'studio', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'No SKU Product',
            'slug' => 'no-sku-product',
            'description' => 'Visible body copy',
            'price' => 1800,
            'stock' => 5,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
            'is_active' => true,
            'sku' => null,
            'headline_text' => null,
        ]);

        $this->assertSame('', $product->resolvedHeadlineText());

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Visible body copy', false)
            ->assertDontSee('am-featured__meta">Pan-India shipping', false)
            ->assertDontSee('SKU:', false);
    }

    public function test_pdp_auto_headline_from_sku_when_headline_text_blank(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'shop-category'],
            ['name' => 'Shop Category', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'SKU Product',
            'slug' => 'sku-product',
            'description' => 'Shop item',
            'price' => 12000,
            'stock' => 3,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'sku' => 'MF-001',
        ]);

        $this->assertSame('SKU: MF-001 · Pan-India shipping', $product->resolvedHeadlineText());

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('SKU: MF-001 · Pan-India shipping', false);
    }

    public function test_admin_form_surfaces_product_page_content_section(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'Partitions', 'section' => 'studio', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Form Labels Product',
            'slug' => 'form-labels-product',
            'price' => 1800,
            'stock' => 1,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('As shown on product page', false)
            ->assertSee('Main description', false)
            ->assertSee('Line under title', false)
            ->assertSee('Note below PVD finish swatches', false)
            ->assertSee('Specifications tab', false)
            ->assertSee('One specification per line', false)
            ->assertSee('Price &amp; discount (as on website)', false)
            ->assertSee('Compare price', false)
            ->assertSee('Discount %', false)
            ->assertSee('product-discount-pct', false)
            ->assertSee('original', false);
    }

    public function test_admin_can_save_mirror_dimensions_and_pdp_shows_all_units(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Sized Mirror',
            'slug' => 'arched-wall-mirror',
            'description' => 'Mirror with dimensions',
            'price' => 18500,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), $this->productPayload($category, $product, [
            'dim_width_ft' => '2',
            'dim_width_in' => '0',
            'dim_height_ft' => '3',
            'dim_height_in' => '0',
        ]))->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh();

        $this->assertSame('60.96', $product->dim_width_cm);
        $this->assertSame('91.44', $product->dim_height_cm);
        $this->assertTrue($product->hasMirrorDimensions());

        $this->get(route('shop.mirror-frames.show', 'arched-wall-mirror'))
            ->assertOk()
            ->assertSee('am-pdp__dimensions', false)
            ->assertSee('am-pdp__dimensions-row', false)
            ->assertSee('>Feet</dt>', false)
            ->assertSee('2 × 3 ft', false)
            ->assertSee('610 × 914 mm', false)
            ->assertSee('61 × 91.4 cm', false)
            ->assertDontSee('900 × 1200 mm standard', false)
            ->assertSeeInOrder([
                'aria-label="Product dimensions"',
                'class="am-pdp-finish" data-pdp-finish',
            ], false);
    }

    public function test_mirror_dimensions_show_feet_and_inches_when_not_whole_feet(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Inch Mirror',
            'slug' => 'inch-detail-mirror',
            'price' => 15000,
            'stock' => 2,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'dim_width_cm' => Product::cmFromFeetInches(2, 6),
            'dim_height_cm' => Product::cmFromFeetInches(4, 0),
        ]);

        $displays = $product->mirrorDimensionDisplays();
        $this->assertSame('2 ft 6 in × 4 ft', $displays['feet']);
        $this->assertSame('762 × 1219 mm', $displays['mm']);
        $this->assertSame('76.2 × 121.9 cm', $displays['cm']);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('2 ft 6 in × 4 ft', false)
            ->assertSee('762 × 1219 mm', false)
            ->assertSee('76.2 × 121.9 cm', false);
    }

    public function test_mirror_dimensions_hidden_when_not_set(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'No Size Mirror',
            'slug' => 'full-length-floor-mirror',
            'description' => 'Mirror without dimensions',
            'price' => 28900,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->get(route('shop.mirror-frames.show', 'full-length-floor-mirror'))
            ->assertOk()
            ->assertDontSee('am-pdp__dimensions', false)
            ->assertDontSee('610 × 914 mm', false);
    }

    public function test_admin_can_save_compare_price_and_pdp_shows_discount_badge(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Discount Mirror',
            'slug' => 'discount-mirror',
            'description' => 'Mirror with sale price',
            'price' => 28999,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), $this->productPayload($category, $product, [
            'price' => '28999',
            'compare_price' => '38999',
        ]))->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh();

        $this->assertSame('28999.00', (string) $product->price);
        $this->assertSame('38999.00', (string) $product->compare_price);
        $this->assertSame(26, $product->discountPercent());

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('₹28,999', false)
            ->assertSee('₹38,999', false)
            ->assertSee('am-featured__price-old', false)
            ->assertSee('am-featured__badge', false)
            ->assertSee('-26%', false);
    }

    public function test_pdp_hides_compare_when_equal_to_price(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Equal Compare Mirror',
            'slug' => 'equal-compare-mirror',
            'price' => 24000,
            'compare_price' => 24000,
            'stock' => 3,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'dim_width_cm' => 60.96,
            'dim_height_cm' => 121.92,
        ]);

        $this->assertNull($product->discountPercent());
        $this->assertFalse($product->hasDisplayComparePrice());

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('₹24,000', false)
            ->assertDontSee('am-featured__price-old', false)
            ->assertSee('am-pdp__dimensions', false)
            ->assertSee('2 × 4 ft', false)
            ->assertSee('610 × 1219 mm', false)
            ->assertSee('61 × 121.9 cm', false);
    }

    public function test_admin_form_shows_discount_percent_control_for_non_handle_products(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'Partitions', 'section' => 'studio', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Discount Pct Product',
            'slug' => 'discount-pct-product',
            'price' => 1800,
            'compare_price' => 2400,
            'stock' => 1,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('id="product-discount-pct"', false)
            ->assertSee('Discount %', false)
            ->assertSee('value="25"', false);
    }

    public function test_admin_normalizes_legacy_html_specifications_to_lines(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'Partitions', 'section' => 'studio', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Legacy Specs Product',
            'slug' => 'legacy-specs-product',
            'price' => 1800,
            'stock' => 2,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), $this->productPayload($category, $product, [
            'tab_specifications' => '<ul><li>Thickness: 1.2mm</li><li>Mounting: Wall &amp; ceiling</li></ul>',
        ]))->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh();

        $this->assertSame("Thickness: 1.2mm\nMounting: Wall & ceiling", $product->tab_specifications);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Thickness: 1.2mm', false)
            ->assertSee('Mounting: Wall & ceiling')
            ->assertDontSee('<ul><li>Thickness: 1.2mm</li><li>Mounting:', false);
    }

    public function test_admin_clears_mirror_dimensions_when_not_mirror_frames(): void
    {
        $admin = User::factory()->admin()->create();
        $mirrors = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );
        $handles = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $mirrors->id,
            'name' => 'Mirror Then Handle',
            'slug' => 'mirror-then-handle',
            'price' => 18500,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'dim_width_cm' => 61,
            'dim_height_cm' => 91,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), $this->productPayload($handles, $product, [
            'name' => 'Mirror Then Handle',
            'slug' => 'mirror-then-handle',
            'price' => '800',
            'dim_width_ft' => '2',
            'dim_width_in' => '0',
            'dim_height_ft' => '3',
            'dim_height_in' => '0',
            'size_options' => [
                ['label' => '8"', 'price' => 800, 'size_inches' => 8],
            ],
        ]))->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh()->load('category');
        $this->assertNull($product->dim_width_cm);
        $this->assertNull($product->dim_height_cm);
        $this->assertFalse($product->hasMirrorDimensions());
        $this->assertCount(1, $product->normalizedSizeOptions());
        $this->assertTrue($product->hasSizeOptions());
    }
}
