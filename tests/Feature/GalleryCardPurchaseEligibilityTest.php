<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\RazorpayService;
use App\Support\CartGuard;
use App\Support\ProductReviewDisplay;
use App\Support\Seo\JsonLd;
use App\Support\StorefrontPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryCardPurchaseEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function shopCategory(string $slug = 'coffee-tables'): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'section' => Product::SECTION_SHOP,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    private function galleryProduct(Category $category, array $overrides = []): Product
    {
        return Product::factory()->shop()->create(array_merge([
            'category_id' => $category->id,
            'stock' => 5,
            'is_gallery_visible' => true,
            'name' => 'Gallery Test Product '.uniqid(),
            'description' => 'Long gallery description that must not appear on category cards.',
        ], $overrides));
    }

    private function categoryGalleryHtml(Product $product): string
    {
        return $this->get(route('shop.show', $product->category?->slug ?? 'coffee-tables'))
            ->assertOk()
            ->getContent();
    }

    public function test_gallery_card_shows_main_product_title(): void
    {
        $category = $this->shopCategory();
        $product = $this->galleryProduct($category, ['name' => 'Brass Side Table']);

        $html = $this->categoryGalleryHtml($product);
        $this->assertStringContainsString('Brass Side Table', $html);
    }

    public function test_gallery_card_hides_long_description(): void
    {
        $category = $this->shopCategory();
        $product = $this->galleryProduct($category, [
            'name' => 'Hidden Description Table',
            'description' => 'This long admin description must stay off gallery cards.',
        ]);

        $html = $this->categoryGalleryHtml($product);

        $this->assertStringContainsString('Hidden Description Table', $html);
        $this->assertStringNotContainsString('This long admin description must stay off gallery cards.', $html);
    }

    public function test_genuine_rating_appears_only_when_real_review_data_exists(): void
    {
        $product = Product::factory()->shop()->make([
            'review_count' => 12,
            'review_average' => 4.5,
        ]);

        $summary = ProductReviewDisplay::summary($product);
        $this->assertNotNull($summary);
        $this->assertSame(4.5, $summary['average']);
        $this->assertSame(12, $summary['count']);

        $html = view('partials.am-product-rating', ['product' => $product])->render();
        $this->assertStringContainsString('am-product-card__rating', $html);
        $this->assertStringContainsString('(12)', $html);
    }

    public function test_no_rating_row_without_real_reviews(): void
    {
        $category = $this->shopCategory();
        $product = $this->galleryProduct($category, ['name' => 'Unreviewed Coffee Table']);

        $this->assertNull(ProductReviewDisplay::summary($product));

        $html = $this->categoryGalleryHtml($product);
        $this->assertStringNotContainsString('am-product-card__rating', $html);
        $this->assertStringNotContainsString('★★★★★', $html);
    }

    public function test_null_price_shop_product_has_no_buy_now_on_gallery(): void
    {
        $category = $this->shopCategory();
        $product = $this->galleryProduct($category, [
            'name' => 'Quotation Only Table',
            'price' => 0,
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
        ]);

        $unsaved = Product::factory()->shop()->make(['price' => null]);
        $this->assertFalse(CartGuard::canDisplayBuyNow($unsaved));

        $html = $this->categoryGalleryHtml($product);
        $this->assertStringContainsString('Quotation Only Table', $html);
        $this->assertStringContainsString('View Details', $html);
        $this->assertStringNotContainsString('name="buy_now"', $html);
    }

    public function test_zero_price_shop_product_has_no_buy_now_on_gallery(): void
    {
        $category = $this->shopCategory();
        $product = $this->galleryProduct($category, [
            'name' => 'Zero Price Table',
            'price' => 0,
        ]);

        $html = $this->categoryGalleryHtml($product);
        $this->assertStringContainsString('Zero Price Table', $html);
        $this->assertStringNotContainsString('>Buy Now</button>', $html);
    }

    public function test_negative_price_shop_product_has_no_buy_now_on_gallery(): void
    {
        $category = $this->shopCategory();
        $product = $this->galleryProduct($category, [
            'name' => 'Negative Price Table',
            'price' => -500,
        ]);

        $html = $this->categoryGalleryHtml($product);
        $this->assertStringContainsString('Negative Price Table', $html);
        $this->assertStringNotContainsString('>Buy Now</button>', $html);
    }

    public function test_invalid_prices_cannot_enter_cart(): void
    {
        $category = $this->shopCategory();

        foreach ([0, -100] as $price) {
            $product = $this->galleryProduct($category, ['price' => $price]);

            $this->post(route('cart.add', $product), ['quantity' => 1])
                ->assertSessionHas('error', CartGuard::MSG_NO_PRICE);

            $this->assertEmpty(session('cart', []));
        }

        $quotationOnly = $this->galleryProduct($category, [
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
            'price' => 0,
        ]);

        $this->post(route('cart.add', $quotationOnly), ['quantity' => 1])
            ->assertSessionHas('error', CartGuard::MSG_NO_PRICE);
    }

    public function test_null_price_model_is_not_purchasable(): void
    {
        $product = Product::factory()->shop()->make(['price' => null]);

        $this->assertFalse(CartGuard::isEligible($product));
        $this->assertSame(CartGuard::MSG_NO_PRICE, CartGuard::checkoutEligibility($product));
    }

    public function test_invalid_prices_cannot_enter_buy_now(): void
    {
        $category = $this->shopCategory();

        foreach ([0, -100] as $price) {
            $product = $this->galleryProduct($category, ['price' => $price]);

            $this->post(route('cart.add', $product), ['quantity' => 1, 'buy_now' => 1])
                ->assertSessionHas('error', CartGuard::MSG_NO_PRICE);

            $this->assertNull(session('buy_now'));
        }

        $quotationOnly = $this->galleryProduct($category, [
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
            'price' => 0,
        ]);

        $this->post(route('cart.add', $quotationOnly), ['quantity' => 1, 'buy_now' => 1])
            ->assertSessionHas('error', CartGuard::MSG_NO_PRICE);
    }

    public function test_checkout_cannot_create_invalid_price_order(): void
    {
        $user = User::factory()->create();
        $category = $this->shopCategory();
        $product = $this->galleryProduct($category, ['price' => 0]);

        $response = $this->actingAs($user)
            ->withSession(['cart' => [$product->id => ['quantity' => 1]]])
            ->post(route('checkout.store'), [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'customer_email' => 'jane@example.com',
                'customer_phone' => '9876543210',
                'house_building' => '123 Test Building',
                'street' => 'Test Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
                'country' => 'India',
                'payment_method' => 'razorpay',
                'billing_same_as_shipping' => '1',
            ]);

        $response->assertRedirect();
        $this->assertSame(0, Order::query()->count());
    }

    public function test_razorpay_cannot_receive_invalid_local_total(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 0,
            'shipping_cost' => 0,
            'total' => 0,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertNull(app(RazorpayService::class)->createOrder($order));
    }

    public function test_valid_fixed_price_product_remains_purchasable(): void
    {
        $category = $this->shopCategory();
        $product = $this->galleryProduct($category, [
            'name' => 'Purchasable Coffee Table',
            'price' => 14000,
        ]);

        $html = $this->categoryGalleryHtml($product);
        $this->assertStringContainsString('Purchasable Coffee Table', $html);
        $this->assertStringContainsString('>Buy Now</button>', $html);

        $this->post(route('cart.add', $product), ['quantity' => 1])
            ->assertSessionHas('success');

        $this->assertSame(1, session('cart')[$product->id]['quantity'] ?? null);
    }

    public function test_valid_selected_variant_uses_trusted_price(): void
    {
        $category = $this->shopCategory('door-handles');
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 5,
            'price' => 20000,
            'size_options' => [
                ['label' => 'Small', 'price' => 14000],
                ['label' => 'Large', 'price' => 18000],
            ],
        ]);

        $this->post(route('cart.add', $product), [
            'quantity' => 1,
            'size_label' => 'Small',
        ])->assertSessionHas('success');

        $item = app(CartService::class)->all()->first();
        $this->assertSame(14000.0, $item['unit_price']);
        $this->assertSame('Small', $item['size_label']);
    }

    public function test_mandatory_variant_without_selection_cannot_proceed(): void
    {
        $category = $this->shopCategory('door-handles');
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 5,
            'price' => 20000,
            'size_options' => [
                ['label' => 'Small', 'price' => 14000],
                ['label' => 'Large', 'price' => 18000],
            ],
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 1])
            ->assertSessionHas('error', CartGuard::MSG_VARIANT_REQUIRED);

        $this->assertEmpty(session('cart', []));
    }

    public function test_browser_price_manipulation_is_ignored(): void
    {
        $category = $this->shopCategory();
        $product = $this->galleryProduct($category, ['price' => 15000]);

        $this->post(route('cart.add', $product), [
            'quantity' => 1,
            'price' => 1,
            'unit_price' => 1,
            'total' => 1,
        ])->assertSessionHas('success');

        $item = app(CartService::class)->all()->first();
        $this->assertSame(15000.0, $item['unit_price']);
    }

    public function test_product_json_ld_does_not_publish_zero_price_offer(): void
    {
        $category = $this->shopCategory();
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'price' => 0,
            'stock' => 5,
        ]);

        $schema = JsonLd::product($product->fresh());
        $this->assertIsArray($schema);
        $this->assertArrayNotHasKey('offers', $schema);
    }

    public function test_studio_units_still_display_correctly(): void
    {
        $this->assertSame('From ₹1,250 / sq ft', StorefrontPrice::listingLabel(
            Product::factory()->studio()->make(['price' => 1250])
        ));
        $this->assertSame('₹18,000 / panel', StorefrontPrice::listingLabel(
            Product::factory()->studio()->create(['price' => 18000, 'pricing_type' => 'panel'])
        ));
        $this->assertSame('₹7,500 / pc', StorefrontPrice::listingLabel(
            Product::factory()->studio()->create(['price' => 7500, 'pricing_type' => 'piece'])
        ));
    }

    public function test_studio_products_remain_request_quote_only(): void
    {
        \App\Models\Service::query()->create([
            'name' => 'PVD Partitions',
            'slug' => 'partitions',
            'summary' => 'Precision stainless partitions.',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'PVD Partitions', 'section' => Product::SECTION_STUDIO, 'is_active' => true]
        );

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Studio Panel Product',
            'is_gallery_visible' => true,
        ]);

        $this->get(route('studio.show', 'pvd-partitions'))
            ->assertOk()
            ->assertSee('Request Quote', false)
            ->assertDontSee('>Buy Now</button>', false);
    }

    public function test_blog_tree_remains_unchanged_vs_approved_baseline(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $output = shell_exec('git -C '.escapeshellarg($repoRoot).' diff b01aa8a -- resources/views/blog 2>&1');

        $this->assertSame('', trim((string) $output));
    }
}
