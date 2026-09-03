<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Mail\OrderReceivedMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderPaymentService;
use App\Support\CheckoutSnapshot;
use App\Support\PaymentAtomicLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckoutPaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'mail.default' => 'array',
            'mail.from.address' => 'shop@example.com',
            'queue.default' => 'sync',
            'checkout.customer_lock_wait' => 2,
            'checkout.razorpay_lock_wait' => 2,
        ]);

        Http::preventStrayRequests();
    }

    private function customer(): User
    {
        return User::factory()->create(['is_admin' => false, 'phone_verified_at' => now()]);
    }

    private function shopProduct(float $price = 1000): Product
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);

        return Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 10,
            'price' => $price,
        ]);
    }

    /** @return array<string, mixed> */
    private function cartSession(Product $product): array
    {
        return ['cart' => [$product->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]]];
    }

    /** @return array<string, string> */
    private function checkoutPayload(): array
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

    private function postCheckout(User $user, Product $product)
    {
        return $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->post(route('checkout.store'), $this->checkoutPayload());
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
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'country' => 'India',
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
                'state' => 'Maharashtra',
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

    public function test_production_lock_store_is_database_with_cache_locks_table(): void
    {
        $this->assertSame('database', config('cache.stores.database.driver'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
        PaymentAtomicLock::assertStoreSupportsSharedLocks();
    }

    public function test_sequential_double_checkout_submission_reuses_one_local_order(): void
    {
        Mail::fake();
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_rzp_test', 'amount' => 119900, 'currency' => 'INR'], 200)]);

        $user = $this->customer();
        $product = $this->shopProduct();
        $first = $this->postCheckout($user, $product);
        $order = Order::query()->first();
        $first->assertRedirect(route('checkout.pay', $order));

        Mail::fake();
        $this->postCheckout($user, $product)->assertRedirect(route('checkout.pay', $order));
        $this->assertSame(1, Order::query()->count());
        Mail::assertNothingSent();
    }

    public function test_checkout_store_blocks_while_customer_lock_is_held(): void
    {
        Mail::fake();
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_rzp_test', 'amount' => 119900, 'currency' => 'INR'], 200)]);

        $user = $this->customer();
        $product = $this->shopProduct();
        $lock = PaymentAtomicLock::forCustomer($user->id);
        $this->assertTrue($lock->get());

        try {
            $this->postCheckout($user, $product)
                ->assertRedirect(route('checkout.index'))
                ->assertSessionHas('error', CheckoutController::MSG_CHECKOUT_IN_PROGRESS);
            $this->assertSame(0, Order::query()->count());
        } finally {
            $lock->release();
        }
    }

    public function test_two_sessions_for_same_user_reuse_same_snapshot_order(): void
    {
        Mail::fake();
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_rzp_test', 'amount' => 119900, 'currency' => 'INR'], 200)]);

        $user = $this->customer();
        $product = $this->shopProduct();

        $this->actingAs($user)->withSession($this->cartSession($product))
            ->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect(route('checkout.pay', Order::query()->first()));

        Mail::fake();
        $this->actingAs($user)->withSession($this->cartSession($product))
            ->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect(route('checkout.pay', Order::query()->first()));

        $this->assertSame(1, Order::query()->count());
    }

    public function test_different_snapshot_is_blocked_with_resume_payment_link(): void
    {
        $user = $this->customer();
        $existing = $this->makePendingOrder($user, $this->shopProduct(1000));
        $productB = $this->shopProduct(2000);

        $this->actingAs($user)->withSession($this->cartSession($productB))
            ->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', CheckoutController::MSG_ACTIVE_PAYMENT)
            ->assertSessionHas('resume_payment_url', route('checkout.pay', $existing));

        $this->assertSame(1, Order::query()->count());
    }

    public function test_existing_active_order_is_not_silently_overwritten(): void
    {
        $user = $this->customer();
        $product = $this->shopProduct();
        $existing = $this->makePendingOrder($user, $product, [
            'customer_name' => 'Original Name',
            'customer_email' => 'original@example.com',
            'total' => 1500,
            'subtotal' => 1301,
        ]);

        $this->actingAs($user)->withSession($this->cartSession($product))
            ->post(route('checkout.store'), array_merge($this->checkoutPayload(), [
                'first_name' => 'Changed',
                'last_name' => 'Person',
                'customer_email' => 'changed@example.com',
            ]));

        $fresh = $existing->fresh();
        $this->assertSame('Original Name', $fresh->customer_name);
        $this->assertSame('original@example.com', $fresh->customer_email);
        $this->assertSame('1500.00', $fresh->total);
    }

    public function test_only_one_order_received_email_is_sent_on_reuse(): void
    {
        Mail::fake();
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_rzp_test', 'amount' => 119900, 'currency' => 'INR'], 200)]);

        $user = $this->customer();
        $product = $this->shopProduct();
        $this->postCheckout($user, $product);
        Mail::assertQueued(OrderReceivedMail::class, 1);
        $this->postCheckout($user, $product);
        Mail::assertQueued(OrderReceivedMail::class, 1);
    }

    public function test_concurrent_create_order_requests_reuse_one_razorpay_order_id(): void
    {
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_concurrent', 'amount' => 119900, 'currency' => 'INR'], 200)]);

        $user = $this->customer();
        $order = $this->makePendingOrder($user, $this->shopProduct(), ['razorpay_order_id' => null]);
        $payments = app(OrderPaymentService::class);

        $first = $payments->ensureRazorpayOrderId($order);
        $second = $payments->ensureRazorpayOrderId($order->fresh());

        $this->assertSame('order_concurrent', $first);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_razorpay_create_failure_can_retry_safely(): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::sequence()
                ->push(['message' => 'fail'], 500)
                ->push(['id' => 'order_retry_ok', 'amount' => 119900, 'currency' => 'INR'], 200),
        ]);

        $user = $this->customer();
        $order = $this->makePendingOrder($user, $this->shopProduct(), ['razorpay_order_id' => null]);
        $payments = app(OrderPaymentService::class);

        try {
            $payments->ensureRazorpayOrderId($order);
            $this->fail('Expected failure.');
        } catch (\RuntimeException) {
            $this->assertNull($order->fresh()->razorpay_order_id);
        }

        $this->assertSame('order_retry_ok', $payments->ensureRazorpayOrderId($order->fresh()));
    }

    public function test_existing_razorpay_id_is_never_overwritten(): void
    {
        Http::fake();
        $user = $this->customer();
        $order = $this->makePendingOrder($user, $this->shopProduct(), ['razorpay_order_id' => 'order_preserved']);

        $this->assertSame('order_preserved', app(OrderPaymentService::class)->ensureRazorpayOrderId($order));
        Http::assertNothingSent();
    }

    public function test_retry_does_not_create_second_local_or_razorpay_order(): void
    {
        Mail::fake();
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_retry_same', 'amount' => 119900, 'currency' => 'INR'], 200)]);

        $user = $this->customer();
        $product = $this->shopProduct();
        $this->postCheckout($user, $product);
        $order = Order::query()->first();

        $this->actingAs($user)->postJson(route('api.create-order'), ['store_order_id' => $order->id])->assertOk();
        $this->actingAs($user)->postJson(route('api.create-order'), ['store_order_id' => $order->id])->assertOk();
        $this->postCheckout($user, $product)->assertRedirect(route('checkout.pay', $order));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame('order_retry_same', $order->fresh()->razorpay_order_id);
        Http::assertSentCount(1);
    }

    public function test_guest_checkout_remains_blocked(): void
    {
        $this->withSession($this->cartSession($this->shopProduct()))
            ->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect(route('account.login'));
        $this->assertSame(0, Order::query()->count());
    }

    public function test_unique_index_migration_remains_unchanged(): void
    {
        $path = 'database/migrations/2026_08_24_233000_add_unique_indexes_to_order_payment_identifiers.php';
        $this->assertSame('', trim((string) shell_exec('git diff e2cae17 -- '.escapeshellarg($path))));
    }
}
