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
}
