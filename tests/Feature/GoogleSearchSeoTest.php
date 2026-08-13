<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\UrlRedirect;
use App\Models\User;
use App\Support\Seo\JsonLd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleSearchSeoTest extends TestCase
{
    use RefreshDatabase;

    private function createShopProduct(array $overrides = []): Product
    {
        $category = Category::factory()->create([
            'slug' => 'coffee-tables',
            'section' => Product::SECTION_SHOP,
        ]);

        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'stock' => 5,
            'price' => 15000,
            'compare_price' => 20000,
            'sku' => 'GSEO-'.uniqid(),
        ], $overrides));
    }

    public function test_active_product_returns_200_with_metadata(): void
    {
        $product = $this->createShopProduct([
            'name' => 'Brass Coffee Table',
            'slug' => 'brass-coffee-table',
            'meta_title' => 'Custom SEO Title',
            'meta_description' => 'Custom meta description for search.',
        ]);

        $html = $this->get(route('shop.show', $product->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<title>Custom SEO Title</title>', $html);
        $this->assertStringContainsString('name="description" content="Custom meta description for search."', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Brass Coffee Table', $html);
    }

    public function test_inactive_product_is_not_public(): void
    {
        $product = $this->createShopProduct(['is_active' => false, 'slug' => 'hidden-product']);

        $this->get(route('shop.show', $product->slug))->assertNotFound();
    }

    public function test_canonical_url_is_absolute_and_self_referencing(): void
    {
        $product = $this->createShopProduct(['slug' => 'canonical-product']);

        $expected = route('shop.show', $product->slug);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('rel="canonical" href="'.$expected.'"', false);
    }

    public function test_open_graph_uses_product_image_fallback(): void
    {
        Storage::fake('public');
        $path = 'products/test.jpg';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('test.jpg', 800, 1067)->getContent());

        $product = $this->createShopProduct([
            'slug' => 'og-product',
            'image' => $path,
            'og_image' => null,
        ]);

        $html = $this->get(route('shop.show', $product->slug))->assertOk()->getContent();

        $this->assertStringContainsString('property="og:type" content="product"', $html);
        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
    }

    public function test_product_json_ld_is_valid_and_uses_selling_price(): void
    {
        $product = $this->createShopProduct([
            'slug' => 'jsonld-product',
            'sku' => 'SKU-JSON-001',
            'price' => 15000,
            'compare_price' => 20000,
            'stock' => 3,
        ]);

        $schema = JsonLd::product($product->fresh());
        $this->assertNotNull($schema);

        $json = json_encode($schema);
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('15000.00', $decoded['offers']['price']);
        $this->assertSame('INR', $decoded['offers']['priceCurrency']);
        $this->assertSame('SKU-JSON-001', $decoded['sku']);
        $this->assertArrayNotHasKey('gtin', $decoded);
        $this->assertArrayNotHasKey('aggregateRating', $decoded);
        $this->assertSame('https://schema.org/NewCondition', $decoded['offers']['itemCondition']);
    }

    public function test_compare_price_is_not_offer_price(): void
    {
        $product = $this->createShopProduct([
            'price' => 1000,
            'compare_price' => 5000,
        ]);

        $schema = JsonLd::product($product);
        $this->assertSame('1000.00', $schema['offers']['price']);
    }

    public function test_breadcrumb_json_ld_on_product_page(): void
    {
        $product = $this->createShopProduct(['slug' => 'breadcrumb-product']);

        $html = $this->get(route('shop.show', $product->slug))->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"BreadcrumbList"', str_replace(' ', '', $html));
    }

    public function test_sitemap_includes_active_products_excludes_inactive(): void
    {
        $active = $this->createShopProduct(['slug' => 'sitemap-active']);
        $inactive = $this->createShopProduct(['slug' => 'sitemap-inactive', 'is_active' => false]);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringContainsString(route('shop.show', $active->slug), $xml);
        $this->assertStringNotContainsString(route('shop.show', $inactive->slug), $xml);
    }

    public function test_sitemap_excludes_noindex_products(): void
    {
        $indexed = $this->createShopProduct(['slug' => 'sitemap-indexed', 'robots_index' => true]);
        $noindex = $this->createShopProduct(['slug' => 'sitemap-noindex', 'robots_index' => false]);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringContainsString(route('shop.show', $indexed->slug), $xml);
        $this->assertStringNotContainsString(route('shop.show', $noindex->slug), $xml);
    }

    public function test_sitemap_is_well_formed_xml(): void
    {
        $this->createShopProduct(['slug' => 'xml-structure-product']);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($xml), 'Sitemap must be valid XML');
        $this->assertSame('urlset', $document->documentElement?->localName);
        $this->assertGreaterThan(0, $document->getElementsByTagName('url')->length);
    }

    public function test_product_json_ld_rendered_with_brand_seller_and_condition(): void
    {
        $product = $this->createShopProduct([
            'slug' => 'rendered-jsonld-product',
            'sku' => 'RENDER-SKU-1',
            'material' => 'Brass',
            'color' => 'Gold',
        ]);

        $html = $this->get(route('shop.show', $product->slug))->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"Product"', str_replace(' ', '', $html));
        $this->assertStringContainsString('"@type":"Brand"', str_replace(' ', '', $html));
        $this->assertStringContainsString('"@type":"Offer"', str_replace(' ', '', $html));
        $this->assertStringContainsString('https://schema.org/NewCondition', $html);
        $this->assertStringContainsString('"material":"Brass"', str_replace(' ', '', $html));
        $this->assertStringContainsString('"color":"Gold"', str_replace(' ', '', $html));
        $this->assertStringNotContainsString('aggregateRating', $html);
    }

    public function test_robots_route_references_configured_sitemap_url(): void
    {
        $expected = rtrim((string) config('app.url'), '/').'/sitemap.xml';

        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.$expected, false)
            ->assertSee('Disallow: /admin', false);
    }

    public function test_cart_and_checkout_are_noindex(): void
    {
        $this->get(route('cart.index'))->assertOk()->assertSee('noindex', false);
    }

    public function test_shop_search_urls_are_noindex(): void
    {
        $this->get(route('shop.index', ['search' => 'table']))
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_slug_change_creates_one_hop_redirect(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createShopProduct(['slug' => 'old-slug-name']);

        $this->actingAsAdmin($admin)
            ->put(route('admin.products.update', $product), $this->validProductPayload($product, [
                'slug' => 'new-slug-name',
            ]))
            ->assertRedirect();

        $this->get('/shop/old-slug-name')->assertRedirect(route('shop.show', 'new-slug-name'));
        $this->assertSame(1, UrlRedirect::query()->where('from_path', '/shop/old-slug-name')->count());
    }

    public function test_duplicate_sku_rejected_in_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = $this->createShopProduct(['sku' => 'DUPE-SKU-1']);
        $product = $this->createShopProduct(['sku' => 'UNIQUE-SKU-2']);

        $this->actingAsAdmin($admin)
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->validProductPayload($product, [
                'sku' => $existing->sku,
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('sku');
    }

    public function test_product_image_markup_has_dimensions_and_alt(): void
    {
        Storage::fake('public');
        $path = 'products/pdp.jpg';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('pdp.jpg', 900, 1200)->getContent());

        $product = $this->createShopProduct([
            'slug' => 'image-markup-product',
            'image' => $path,
            'image_alt' => 'Brushed brass coffee table in studio',
        ]);

        $html = $this->get(route('shop.show', $product->slug))->assertOk()->getContent();

        $this->assertStringContainsString('alt="Brushed brass coffee table in studio"', $html);
        $this->assertStringContainsString('width="', $html);
        $this->assertStringContainsString('height="', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
    }

    public function test_noindex_product_has_robots_meta(): void
    {
        $product = $this->createShopProduct([
            'slug' => 'noindex-product',
            'robots_index' => false,
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);
    }

    /** @param  array<string, mixed>  $overrides */
    private function validProductPayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => $product->price,
            'compare_price' => $product->compare_price,
            'sku' => $product->sku,
            'stock' => $product->stock,
            'section' => $product->section,
            'purchase_mode' => $product->purchase_mode,
            'pricing_type' => $product->pricing_type,
            'is_active' => 1,
            'is_featured' => 0,
            'is_gallery_visible' => 1,
            'robots_index' => 1,
        ], $overrides);
    }
}
