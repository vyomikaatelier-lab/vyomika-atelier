<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function validCheckoutPayload(): array
    {
        return [
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
        ];
    }

    private function verifiedUserWithCart(): array
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);
        $session = ['cart' => [$product->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]]];

        return [$user, $session];
    }

    public function test_checkout_display_drops_invalid_cart_items_and_redirects_when_empty(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['slug' => 'partitions']);
        $studioProduct = Product::factory()->studio()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->withSession(['cart' => [$studioProduct->id => 1]])
            ->get(route('checkout.index'));

        $response->assertRedirect(route('shop.index'));
        $response->assertSessionHas('error', 'Your cart is empty.');
        $this->assertEmpty(session('cart', []));
    }

    public function test_order_creation_rejects_studio_product_in_cart(): void
    {
        [$user] = $this->verifiedUserWithCart();
        $category = Category::factory()->create(['slug' => 'partitions']);
        $studioProduct = Product::factory()->studio()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->withSession(['cart' => [$studioProduct->id => 1]])
            ->post(route('checkout.store'), $this->validCheckoutPayload());

        $response->assertRedirect();
        $this->assertSame(0, Order::query()->count());
    }

    public function test_order_creation_rejects_railings_product_in_cart(): void
    {
        [$user] = $this->verifiedUserWithCart();
        $category = Category::factory()->create(['slug' => 'railings']);
        $railingProduct = Product::factory()->railings()->create(['category_id' => $category->id]);

        $response = $this->actingAs($user)
            ->withSession(['cart' => [$railingProduct->id => 1]])
            ->post(route('checkout.store'), $this->validCheckoutPayload());

        $response->assertRedirect();
        $this->assertSame(0, Order::query()->count());
    }

    public function test_mixed_cart_silently_drops_studio_item_and_keeps_valid_shop_item(): void
    {
        $user = User::factory()->create();
        $shopCategory = Category::factory()->create(['slug' => 'coffee-tables']);
        $studioCategory = Category::factory()->create(['slug' => 'partitions']);
        $shopProduct = Product::factory()->shop()->create(['category_id' => $shopCategory->id]);
        $studioProduct = Product::factory()->studio()->create(['category_id' => $studioCategory->id]);

        $response = $this->actingAs($user)
            ->withSession(['cart' => [
                $shopProduct->id => 1,
                $studioProduct->id => 1,
            ]])
            ->get(route('checkout.index'));

        $response->assertOk();
        $cart = session('cart', []);
        $this->assertArrayHasKey($shopProduct->id, $cart);
        $this->assertArrayNotHasKey($studioProduct->id, $cart);
    }

    public function test_checkout_without_razorpay_configured_creates_no_order(): void
    {
        [$user, $session] = $this->verifiedUserWithCart();

        $response = $this->actingAs($user)
            ->withSession($session)
            ->post(route('checkout.store'), $this->validCheckoutPayload());

        $response->assertRedirect(route('checkout.index'));
        $response->assertSessionHas('error', config('addresses.payment_unavailable_message'));
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

        [$user, $session] = $this->verifiedUserWithCart();

        $response = $this->actingAs($user)
            ->withSession($session)
            ->post(route('checkout.store'), $this->validCheckoutPayload());

        $response->assertRedirect();
        $this->assertSame(1, Order::query()->count());
        $this->assertSame('Jane Doe', Order::query()->value('customer_name'));
        $this->assertNotNull(Order::query()->value('shipping_snapshot'));
    }

    public function test_guest_cannot_post_checkout(): void
    {
        [, $session] = $this->verifiedUserWithCart();

        $response = $this->withSession($session)
            ->post(route('checkout.store'), $this->validCheckoutPayload());

        $response->assertRedirect(route('account.login'));
        $this->assertSame(0, Order::query()->count());
    }
}
