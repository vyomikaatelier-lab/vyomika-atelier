<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderPaymentService;
use App\Services\RazorpayService;
use App\Support\CartGuard;
use App\Services\CartService;
use App\Support\CategoryPublicationPolicy;
use App\Support\ProductPublicationPolicy;
use App\Support\Seo\JsonLd;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductPublicationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function shopCategory(string $slug = 'coffee-tables', array $overrides = []): Category
    {
        $defaults = [
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'section' => Product::SECTION_SHOP,
            'is_active' => true,
            'sort_order' => 1,
        ];

        $category = Category::query()->firstOrCreate(['slug' => $slug], $defaults);

        if ($overrides !== []) {
            $category->update($overrides);
        }

        return $category->fresh();
    }

    /** @param array<string, mixed> $overrides */
    private function publishedShopProduct(Category $category, array $overrides = []): Product
    {
        return Product::factory()->shop()->create(array_merge([
            'category_id' => $category->id,
            'stock' => 5,
            'price' => 15000,
            'is_active' => true,
            'is_gallery_visible' => true,
            'robots_index' => true,
        ], $overrides));
    }

    public function test_active_visible_indexable_product_returns_200(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category, [
            'slug' => 'published-active-table',
            'name' => 'Published Active Table',
        ]);

        $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('Published Active Table', false);
    }

    public function test_inactive_product_returns_genuine_404_with_noindex(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category, [
            'slug' => 'inactive-policy-table',
            'is_active' => false,
        ]);

        $response = $this->get(route('shop.show', $product->slug));

        $response->assertNotFound();
        $response->assertSee('name="robots" content="noindex,nofollow"', false);
    }

    public function test_inactive_product_is_absent_from_shop_gallery(): void
    {
        $category = $this->shopCategory();
        $this->publishedShopProduct($category, [
            'name' => 'Gallery Inactive Table',
            'is_active' => false,
        ]);

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertDontSee('Gallery Inactive Table', false);
    }

    public function test_inactive_product_is_absent_from_studio_gallery(): void
    {
        Service::query()->create([
            'name' => 'PVD Partitions',
            'slug' => 'partitions',
            'summary' => 'Studio partitions.',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'PVD Partitions', 'section' => Product::SECTION_STUDIO, 'is_active' => true]
        );

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Inactive Studio Panel',
            'is_gallery_visible' => true,
            'is_active' => false,
        ]);

        $this->get(route('studio.show', 'pvd-partitions'))
            ->assertOk()
            ->assertDontSee('Inactive Studio Panel', false);
    }

    public function test_inactive_product_is_absent_from_homepage_queries(): void
    {
        $category = $this->shopCategory();
        $this->publishedShopProduct($category, [
            'name' => 'Homepage Inactive Table',
            'is_active' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Homepage Inactive Table', false);
    }

    public function test_inactive_product_is_absent_from_search(): void
    {
        $category = $this->shopCategory();
        $this->publishedShopProduct($category, [
            'name' => 'Search Inactive Table',
            'is_active' => false,
        ]);

        $this->get(route('search', ['q' => 'Search Inactive']))
            ->assertOk()
            ->assertDontSee('Search Inactive Table', false);
    }

    public function test_inactive_product_is_absent_from_related_products(): void
    {
        $category = $this->shopCategory();
        $visible = $this->publishedShopProduct($category, [
            'slug' => 'visible-related-anchor',
            'name' => 'Visible Related Anchor',
        ]);
        $hidden = $this->publishedShopProduct($category, [
            'slug' => 'hidden-related-target',
            'name' => 'Hidden Related Target',
            'is_active' => false,
        ]);

        $html = $this->get(route('shop.show', $visible->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString($hidden->name, $html);
    }

    public function test_inactive_product_is_absent_from_sitemap(): void
    {
        $category = $this->shopCategory();
        $inactive = $this->publishedShopProduct($category, [
            'slug' => 'sitemap-inactive-policy',
            'is_active' => false,
        ]);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('shop.show', $inactive->slug), $xml);
    }

    public function test_inactive_product_emits_no_product_or_offer_json_ld(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category, ['is_active' => false]);

        $this->assertNull(JsonLd::product($product->fresh()));
    }

    public function test_inactive_product_cannot_enter_cart(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category, ['is_active' => false]);

        $this->post(route('cart.add', $product), ['quantity' => 1])
            ->assertSessionHas('error', CartGuard::MSG_INACTIVE);
    }

    public function test_inactive_product_cannot_enter_buy_now(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category, ['is_active' => false]);

        $this->post(route('cart.add', $product), ['quantity' => 1, 'buy_now' => 1])
            ->assertSessionHas('error', CartGuard::MSG_INACTIVE);
    }

    public function test_inactive_product_cannot_enter_checkout(): void
    {
        $user = User::factory()->create();
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category);

        $this->actingAs($user)
            ->withSession(['cart' => [$product->id => ['quantity' => 1, 'unit_price' => 15000]]]);

        $product->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('checkout.index'))
            ->assertRedirect(StorefrontRoutes::primaryShopUrl());
    }

    public function test_inactive_product_cannot_reach_razorpay_order_creation(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);

        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 15000,
            'shipping_cost' => 199,
            'total' => 15199,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'expires_at' => now()->addDay(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 15000,
            'quantity' => 1,
            'total' => 15000,
        ]);

        $product->update(['is_active' => false]);

        $this->expectException(\RuntimeException::class);
        app(OrderPaymentService::class)->razorpayCheckoutPayload($order->fresh());
    }

    public function test_existing_cart_revalidates_after_product_deactivation(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category);

        $this->withSession(['cart' => [$product->id => ['quantity' => 1, 'unit_price' => 15000]]]);
        app(CartService::class)->all();

        $product->update(['is_active' => false]);

        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    public function test_gallery_hidden_product_is_absent_from_galleries_search_and_sitemap(): void
    {
        $category = $this->shopCategory();
        $hidden = $this->publishedShopProduct($category, [
            'slug' => 'gallery-hidden-table',
            'name' => 'Gallery Hidden Table',
            'is_gallery_visible' => false,
        ]);

        $this->get(route('shop.show', 'coffee-tables'))->assertDontSee('Gallery Hidden Table', false);
        $this->get(route('search', ['q' => 'Gallery Hidden']))->assertDontSee('Gallery Hidden Table', false);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('shop.show', $hidden->slug), $xml);
    }

    public function test_gallery_hidden_direct_url_returns_404(): void
    {
        $category = $this->shopCategory();
        $hidden = $this->publishedShopProduct($category, [
            'slug' => 'gallery-hidden-direct',
            'is_gallery_visible' => false,
        ]);

        $this->get(route('shop.show', $hidden->slug))
            ->assertNotFound()
            ->assertSee('name="robots" content="noindex,nofollow"', false);
    }

    public function test_robots_index_false_is_excluded_from_sitemap_and_outputs_noindex(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category, [
            'slug' => 'robots-noindex-table',
            'robots_index' => false,
        ]);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('shop.show', $product->slug), $xml);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,follow"', false);
    }

    public function test_reactivated_qualifying_product_automatically_returns_200(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category, [
            'slug' => 'reactivated-table',
            'name' => 'Reactivated Table',
            'is_active' => false,
        ]);

        $this->get(route('shop.show', $product->slug))->assertNotFound();

        $product->update(['is_active' => true]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Reactivated Table', false);
    }

    public function test_reactivated_qualifying_product_returns_to_galleries_and_sitemap(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category, [
            'slug' => 'reactivated-sitemap-table',
            'name' => 'Reactivated Sitemap Table',
            'is_active' => false,
        ]);

        $product->update(['is_active' => true]);

        $this->get(route('shop.show', 'coffee-tables'))->assertSee('Reactivated Sitemap Table', false);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertStringContainsString(route('shop.show', $product->slug), $xml);
    }

    public function test_reactivated_invalid_price_product_has_no_purchasable_offer_or_buy_now(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category, [
            'price' => 0,
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
            'is_active' => false,
        ]);

        $product->update(['is_active' => true]);

        $schema = JsonLd::product($product->fresh());
        $this->assertIsArray($schema);
        $this->assertArrayNotHasKey('offers', $schema);
        $this->assertFalse(CartGuard::canDisplayBuyNow($product->fresh()));
    }

    public function test_studio_reactivation_remains_request_quote_only(): void
    {
        Service::query()->create([
            'name' => 'PVD Partitions',
            'slug' => 'partitions',
            'summary' => 'Studio partitions.',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'PVD Partitions', 'section' => Product::SECTION_STUDIO, 'is_active' => true]
        );

        $studio = Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Reactivated Studio Panel',
            'is_active' => false,
            'is_gallery_visible' => true,
        ]);

        $studio->update(['is_active' => true]);

        $this->get(route('studio.show', 'pvd-partitions'))
            ->assertOk()
            ->assertSee('Request Quote', false);

        $this->post(route('cart.add', $studio), ['quantity' => 1])
            ->assertSessionHas('error', CartGuard::MSG_STUDIO);
    }

    public function test_inactive_category_is_absent_from_navigation_and_sitemap(): void
    {
        $category = $this->shopCategory('corner-tables', ['is_active' => false]);

        $this->assertFalse(CategoryPublicationPolicy::isSitemapListed($category));

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('shop.show', 'corner-tables'), $xml);

        $this->get(route('shop.show', 'corner-tables'))->assertNotFound();
    }

    public function test_product_under_inactive_category_is_not_publicly_indexable(): void
    {
        $category = $this->shopCategory('corner-tables', ['is_active' => false]);
        $product = $this->publishedShopProduct($category, ['slug' => 'inactive-category-product']);

        $this->assertFalse(ProductPublicationPolicy::isPubliclyAccessible($product->fresh('category')));
        $this->get(route('shop.show', $product->slug))->assertNotFound();
    }

    public function test_no_inactive_url_is_added_to_robots_txt(): void
    {
        $category = $this->shopCategory();
        $inactive = $this->publishedShopProduct($category, [
            'slug' => 'robots-txt-inactive',
            'is_active' => false,
        ]);

        $body = $this->get(route('robots'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('shop.show', $inactive->slug), $body);
        $this->assertStringContainsString('Sitemap:', $body);
    }

    public function test_publication_policy_matches_cart_guard_for_shop_product(): void
    {
        $category = $this->shopCategory();
        $product = $this->publishedShopProduct($category);

        $this->assertTrue(ProductPublicationPolicy::isCartEligible($product));
        $this->assertTrue(CartGuard::isEligible($product));
    }
}
