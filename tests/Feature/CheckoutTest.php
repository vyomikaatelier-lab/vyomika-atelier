<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function validCheckoutPayload(): array
    {
        return [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'country' => 'India',
            'payment_method' => 'razorpay',
        ];
    }

    public function test_checkout_display_drops_invalid_cart_items_and_redirects_when_empty(): void
    {
        $category = Category::factory()->create(['slug' => 'partitions']);
        $studioProduct = Product::factory()->studio()->create(['category_id' => $category->id]);

        // A Studio item somehow ended up in the session cart (legacy/forged state).
        $this->withSession(['cart' => [$studioProduct->id => 1]]);

        $response = $this->get(route('checkout.index'));

        $response->assertRedirect(route('shop.index'));
        $response->assertSessionHas('error', 'Your cart is empty.');
        $this->assertEmpty(session('cart', []));
    }

    public function test_order_creation_rejects_studio_product_in_cart(): void
    {
        $category = Category::factory()->create(['slug' => 'partitions']);
        $studioProduct = Product::factory()->studio()->create(['category_id' => $category->id]);

        $this->withSession(['cart' => [$studioProduct->id => 1]]);

        $response = $this->post(route('checkout.store'), $this->validCheckoutPayload());

        $response->assertRedirect();
        $this->assertSame(0, Order::query()->count());
    }

    public function test_order_creation_rejects_railings_product_in_cart(): void
    {
        $category = Category::factory()->create(['slug' => 'railings']);
        $railingProduct = Product::factory()->railings()->create(['category_id' => $category->id]);

        $this->withSession(['cart' => [$railingProduct->id => 1]]);

        $response = $this->post(route('checkout.store'), $this->validCheckoutPayload());

        $response->assertRedirect();
        $this->assertSame(0, Order::query()->count());
    }

    public function test_mixed_cart_silently_drops_studio_item_and_keeps_valid_shop_item(): void
    {
        $shopCategory = Category::factory()->create(['slug' => 'coffee-tables']);
        $studioCategory = Category::factory()->create(['slug' => 'partitions']);
        $shopProduct = Product::factory()->shop()->create(['category_id' => $shopCategory->id]);
        $studioProduct = Product::factory()->studio()->create(['category_id' => $studioCategory->id]);

        $this->withSession(['cart' => [
            $shopProduct->id => 1,
            $studioProduct->id => 1,
        ]]);

        $response = $this->get(route('checkout.index'));

        $response->assertOk();
        $cart = session('cart', []);
        $this->assertArrayHasKey($shopProduct->id, $cart);
        $this->assertArrayNotHasKey($studioProduct->id, $cart);
    }

    public function test_checkout_without_razorpay_configured_creates_no_order(): void
    {
        // phpunit.xml sets RAZORPAY_KEY/SECRET empty, so the service is unconfigured.
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id]);

        $this->withSession(['cart' => [$product->id => 1]]);

        $response = $this->post(route('checkout.store'), $this->validCheckoutPayload());

        $response->assertRedirect(route('checkout.index'));
        $response->assertSessionHas('error');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_accepts_first_and_last_name_without_customer_name_field(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test',
            'services.razorpay.secret' => 'secret_test',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'api.razorpay.com/*' => \Illuminate\Support\Facades\Http::response(['id' => 'order_test_xyz'], 200),
        ]);

        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);

        $this->withSession(['cart' => [$product->id => 1]]);

        $payload = $this->validCheckoutPayload();
        unset($payload['customer_name']);
        $payload['first_name'] = 'Jane';
        $payload['last_name'] = 'Doe';

        $response = $this->post(route('checkout.store'), $payload);

        $response->assertRedirect();
        $this->assertSame(1, Order::query()->count());
        $this->assertSame('Jane Doe', Order::query()->value('customer_name'));
    }
}
