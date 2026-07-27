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
            'tab_specifications' => '<h3>Custom specs</h3><p>Bespoke specification copy.</p>',
            'tab_packaging' => '<h3>Custom packaging</h3><p>Extra packaging details.</p>',
            'tab_shipping' => '<h3>Custom shipping</h3><p>Express metro delivery available.</p>',
        ]))->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh();

        $this->assertSame('Updated studio description', $product->description);
        $this->assertSame('Custom headline for PDP', $product->headline_text);
        $this->assertSame('Custom swatch note for buyers', $product->swatches_note);
        $this->assertStringContainsString('Custom specs', (string) $product->tab_specifications);

        $response = $this->get(route('shop.show', $product->slug));
        $response->assertOk()
            ->assertSee('Updated studio description', false)
            ->assertSee('Custom headline for PDP', false)
            ->assertSee('Custom swatch note for buyers', false)
            ->assertSee('Bespoke specification copy.', false)
            ->assertSee('Extra packaging details.', false)
            ->assertSee('Express metro delivery available.', false);
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
            ->assertSee('Specifications tab', false);
    }
}
