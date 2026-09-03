<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\PendingOrderExpiry;
use App\Support\CheckoutSnapshot;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutPaymentStateSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);

        Http::preventStrayRequests();
    }

    private function customer(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    private function shopProduct(): Product
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);

        return Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 10,
            'price' => 1000,
        ]);
    }

    private function makePendingOrder(User $user, Product $product, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $user->id,
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9876543210',
            'shipping_address' => '123 Test Building, Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => $product->price,
            'shipping_cost' => 199,
            'total' => $product->price + 199,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'razorpay_order_id' => 'order_'.strtolower(substr(uniqid(), -8)),
            'shipping_snapshot' => CheckoutSnapshot::withSource([
                'full_name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '9876543210',
                'formatted_line' => '123 Test Building, Test Street',
                'city' => 'Mumbai',
                'pincode' => '400001',
                'country' => 'India',
            ], CheckoutSnapshot::SOURCE_CART),
            'expires_at' => now()->addDay(),
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
            'total' => $product->price,
        ]);

        return $order;
    }

    public function test_pending_success_route_contains_no_order_confirmed_wording(): void
    {
        $user = $this->customer();
        $order = $this->makePendingOrder($user, $this->shopProduct());

        $this->actingAs($user)->get(route('checkout.success', $order))
            ->assertRedirect(route('checkout.pay', $order));

        $this->actingAs($user)->get(route('checkout.pay', $order))
            ->assertOk()
            ->assertSee('Complete Payment', false)
            ->assertDontSee('Order Confirmed', false)
            ->assertDontSee('placed successfully', false);
    }

    public function test_paid_success_route_contains_confirmed_wording(): void
    {
        $user = $this->customer();
        $order = $this->makePendingOrder($user, $this->shopProduct(), ['status' => 'paid']);

        $this->actingAs($user)->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('Order Confirmed', false)
            ->assertSee('placed successfully', false);
    }

    public function test_cancelled_and_expired_orders_contain_accurate_copy(): void
    {
        $user = $this->customer();
        $product = $this->shopProduct();

        $cancelled = $this->makePendingOrder($user, $product, ['status' => 'cancelled', 'expires_at' => null]);
        $expired = $this->makePendingOrder($user, $product, [
            'order_number' => Order::generateOrderNumber(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)->get(route('checkout.pay', $cancelled))
            ->assertOk()
            ->assertSee('Payment session cancelled', false)
            ->assertDontSee('Order Confirmed', false);

        $this->actingAs($user)->get(route('checkout.pay', $expired))
            ->assertOk()
            ->assertSee('Payment session expired', false)
            ->assertDontSee('Order Confirmed', false);
    }

    public function test_payment_controller_routes_every_status_correctly(): void
    {
        $user = $this->customer();
        $product = $this->shopProduct();

        $paid = $this->makePendingOrder($user, $product, ['status' => 'paid', 'order_number' => Order::generateOrderNumber()]);
        $this->actingAs($user)->get(route('checkout.pay', $paid))->assertRedirect(route('checkout.success', $paid));

        $pending = $this->makePendingOrder($user, $product, ['order_number' => Order::generateOrderNumber()]);
        $this->actingAs($user)->get(route('checkout.pay', $pending))->assertOk();

        $expired = $this->makePendingOrder($user, $product, [
            'order_number' => Order::generateOrderNumber(),
            'expires_at' => now()->subMinute(),
        ]);
        $this->actingAs($user)->get(route('checkout.pay', $expired))
            ->assertOk()
            ->assertSee('Payment session expired', false);

        $cancelled = $this->makePendingOrder($user, $product, [
            'order_number' => Order::generateOrderNumber(),
            'status' => 'cancelled',
            'expires_at' => null,
        ]);
        $this->actingAs($user)->get(route('checkout.pay', $cancelled))
            ->assertOk()
            ->assertSee('Payment session cancelled', false);
    }

    public function test_pay_page_includes_failure_dismiss_and_retry_handlers(): void
    {
        $user = $this->customer();
        $order = $this->makePendingOrder($user, $this->shopProduct());

        $this->actingAs($user)->get(route('checkout.pay', $order))
            ->assertOk()
            ->assertSee('payment.failed', false)
            ->assertSee('Payment was not completed', false)
            ->assertSee('Return to cart', false)
            ->assertSee('createOrderUrl', false);
    }

    public function test_expiry_conditional_update_cannot_cancel_paid_order(): void
    {
        $user = $this->customer();
        $order = $this->makePendingOrder($user, $this->shopProduct(), ['expires_at' => now()->subMinute()]);
        Order::query()->whereKey($order->id)->update(['status' => 'paid']);

        $this->assertFalse(PendingOrderExpiry::expireIfStillPending($order->fresh()));
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_expire_pending_command_skips_non_pending_rows(): void
    {
        $user = $this->customer();
        $product = $this->shopProduct();

        $expired = $this->makePendingOrder($user, $product, ['expires_at' => now()->subMinute()]);
        $paid = $this->makePendingOrder($user, $product, [
            'order_number' => Order::generateOrderNumber(),
            'expires_at' => now()->subMinute(),
        ]);
        Order::query()->whereKey($paid->id)->update(['status' => 'paid']);

        $this->artisan('orders:expire-pending')->assertSuccessful();
        $this->assertSame('cancelled', $expired->fresh()->status);
        $this->assertSame('paid', $paid->fresh()->status);
    }

    public function test_foreign_order_access_remains_denied(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->makePendingOrder($owner, $this->shopProduct());

        $this->actingAs($intruder)->get(route('checkout.pay', $order))
            ->assertRedirect(StorefrontRoutes::primaryShopUrl())
            ->assertSessionHas('error', 'Order not found.');

        $this->actingAs($intruder)->get(route('checkout.success', $order))
            ->assertRedirect(StorefrontRoutes::primaryShopUrl())
            ->assertSessionHas('error', 'Order not found.');
    }
}
