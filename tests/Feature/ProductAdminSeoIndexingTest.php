<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductPublicationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * H1 — product `robots_index` must persist both true and false through the
 * admin save path, including the omitted-unchecked-checkbox case.
 */
class ProductAdminSeoIndexingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function shopCategory(): Category
    {
        return Category::factory()->create([
            'slug' => 'coffee-tables',
            'section' => Product::SECTION_SHOP,
        ]);
    }

    private function shopProduct(Category $category, array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'stock' => 5,
            'price' => 15000,
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function payload(Category $category, Product $product, array $overrides = []): array
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

    public function test_update_with_checked_robots_index_persists_true(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category, ['robots_index' => false]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.products.update', $product), $this->payload($category, $product, [
                'robots_index' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($product->fresh()->robots_index);
    }

    public function test_update_with_unchecked_robots_index_persists_false(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category, ['robots_index' => true]);

        // Unchecked checkboxes are omitted from the request entirely.
        $this->actingAsAdmin($this->admin())
            ->put(route('admin.products.update', $product), $this->payload($category, $product))
            ->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->robots_index);
    }

    public function test_editing_an_unrelated_field_does_not_reset_deliberate_noindex(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category, ['robots_index' => false]);

        // The form renders the checkbox unchecked for a noindex product, so a
        // save touching only the description omits robots_index.
        $this->actingAsAdmin($this->admin())
            ->put(route('admin.products.update', $product), $this->payload($category, $product, [
                'description' => 'Updated description only',
            ]))
            ->assertSessionHasNoErrors();

        $fresh = $product->fresh();
        $this->assertSame('Updated description only', $fresh->description);
        $this->assertFalse($fresh->robots_index);
    }

    public function test_create_with_checked_robots_index_persists_true(): void
    {
        $category = $this->shopCategory();

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.products.store'), [
                'category_id' => $category->id,
                'name' => 'Indexable Table',
                'description' => 'A table',
                'price' => 12000,
                'compare_price' => '',
                'stock' => 3,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => '1',
                'is_gallery_visible' => '1',
                'robots_index' => '1',
            ])
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Indexable Table')->firstOrFail();
        $this->assertTrue($product->robots_index);
    }

    public function test_create_with_unchecked_robots_index_persists_false(): void
    {
        $category = $this->shopCategory();

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.products.store'), [
                'category_id' => $category->id,
                'name' => 'Hidden Table',
                'description' => 'A table',
                'price' => 12000,
                'compare_price' => '',
                'stock' => 3,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => '1',
                'is_gallery_visible' => '1',
            ])
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('name', 'Hidden Table')->firstOrFail();
        $this->assertFalse($product->robots_index);
    }

    public function test_storefront_robots_meta_honours_admin_saved_noindex(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category, [
            'slug' => 'meta-honours-noindex',
            'robots_index' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.products.update', $product), $this->payload($category, $product))
            ->assertSessionHasNoErrors();

        $this->get(route('shop.show', $product->fresh()->slug))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);
    }

    public function test_noindex_product_saved_by_admin_is_not_sitemap_eligible(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category, [
            'slug' => 'sitemap-ineligible-noindex',
            'robots_index' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.products.update', $product), $this->payload($category, $product))
            ->assertSessionHasNoErrors();

        $fresh = $product->fresh();
        $this->assertFalse(ProductPublicationPolicy::isSitemapListed($fresh));

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('shop.show', $fresh->slug), $xml);
    }
}
