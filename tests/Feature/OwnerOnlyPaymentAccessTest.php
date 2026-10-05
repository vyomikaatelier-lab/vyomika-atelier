<?php

namespace Tests\Feature;

use App\Mail\AdminNewOrderMail;
use App\Mail\AdminPaymentReceivedMail;
use App\Mail\OrderReceivedMail;
use App\Mail\PaymentSuccessfulMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\CheckoutPayments;
use App\Support\OrderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OwnerOnlyPaymentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'services.razorpay.webhook_secret' => 'whsec_test',
            'services.admin_email' => 'studio@example.com',
            'mail.default' => 'array',
            'mail.from.address' => 'shop@example.com',
            'checkout.payments_enabled' => true,
            'checkout.payments_allowed_emails' => 'owner-test@example.com',
        ]);
    }

    public function test_defaults_leave_payments_off_and_the_allowlist_empty(): void
    {
        $checkout = (string) file_get_contents(config_path('checkout.php'));

        $this->assertMatchesRegularExpression(
            "/'payments_enabled'\\s*=>\\s*filter_var\\(env\\('CHECKOUT_PAYMENTS_ENABLED',\\s*false\\),\\s*FILTER_VALIDATE_BOOLEAN\\)/",
            $checkout
        );
        $this->assertStringContainsString(
            "env('CHECKOUT_PAYMENTS_ALLOWED_EMAILS', '')",
            $checkout
        );
    }

    public function test_allowlist_blocks_other_customers_from_checkout_pay_and_create_order(): void
    {
        Http::preventStrayRequests();
        [$product, $session] = $this->shopCart();
        $other = User::factory()->create(['email' => 'other@example.com']);
        $owner = User::factory()->create(['email' => 'owner-test@example.com']);
        $order = $this->pendingOrder($other, $product);

        $this->actingAs($other)
            ->withSession($session)
            ->post(route('checkout.store'), $this->addressPayload($other))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', CheckoutPayments::UNAVAILABLE_MESSAGE);
        $this->assertSame(1, Order::query()->count());

        $this->actingAs($other)
            ->withSession(['cart' => []])
            ->post(route('checkout.store'), [])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', CheckoutPayments::UNAVAILABLE_MESSAGE);
        $this->assertSame(1, Order::query()->count());

        $this->actingAs($other)
            ->get(route('checkout.pay', $order))
            ->assertOk()
            ->assertSee('Checkout is temporarily unavailable', false);

        $this->actingAs($other)
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id])
            ->assertStatus(503)
            ->assertJson(['message' => CheckoutPayments::UNAVAILABLE_MESSAGE]);
        $this->assertNull($order->fresh()->razorpay_order_id);

        $this->assertTrue(CheckoutPayments::canInitiate($owner));
        $this->assertFalse(CheckoutPayments::canInitiate($other));
    }

    public function test_signed_webhook_still_settles_when_the_allowlist_excludes_the_customer(): void
    {
        Mail::fake();
        $product = $this->product();
        $other = User::factory()->create(['email' => 'other@example.com']);
        $order = $this->pendingOrder($other, $product, [
            'razorpay_order_id' => 'order_allowlist_wh',
            'shipping_snapshot' => [
                CheckoutPayments::SNAPSHOT_SUPPRESS_NOTIFICATIONS => true,
            ],
        ]);

        $payment = [
            'id' => 'pay_allowlist_wh',
            'order_id' => 'order_allowlist_wh',
            'amount' => 100000,
            'currency' => 'INR',
            'status' => 'captured',
        ];
        Http::fake([
            'api.razorpay.com/v1/payments/*' => Http::response($payment, 200),
        ]);

        $payload = [
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => $payment]],
        ];
        $body = json_encode($payload);
        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_test'),
        ], $body)->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('pay_allowlist_wh', $order->fresh()->payment_id);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        $this->assertSame(4, $product->fresh()->stock);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertNull($order->fresh()->payment_email_sent_at);
        $this->assertNull($order->fresh()->admin_payment_notified_at);
    }

    public function test_allowlisted_checkout_marks_the_order_so_later_mail_stays_off(): void
    {
        Mail::fake();
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_owner_create',
                'amount' => 100000,
                'currency' => 'INR',
            ], 200),
        ]);

        [$product, $session] = $this->shopCart();
        $owner = User::factory()->create(['email' => 'owner-test@example.com']);

        $this->actingAs($owner)
            ->withSession($session)
            ->post(route('checkout.store'), $this->addressPayload($owner))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertTrue(CheckoutPayments::orderMailSuppressed($order));
        $this->assertSame('order_owner_create', $order->razorpay_order_id);
        Mail::assertNotQueued(OrderReceivedMail::class);
        Mail::assertNotQueued(AdminNewOrderMail::class);
        Mail::assertNotQueued(PaymentSuccessfulMail::class);
        Mail::assertNotQueued(AdminPaymentReceivedMail::class);
        $this->assertNull($order->order_received_email_sent_at);
        $this->assertSame($product->id, $order->items()->value('product_id'));
    }

    public function test_empty_allowlist_does_not_restrict_a_customer_when_payments_are_on(): void
    {
        config(['checkout.payments_allowed_emails' => '']);
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_open_create',
                'amount' => 100000,
                'currency' => 'INR',
            ], 200),
        ]);

        $product = $this->product();
        $customer = User::factory()->create(['email' => 'any@example.com']);
        $order = $this->pendingOrder($customer, $product);

        $this->actingAs($customer)
            ->withSession([OrderAccess::SESSION_KEY => $order->id])
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id])
            ->assertOk()
            ->assertJson(['order_id' => 'order_open_create']);
    }

    /**
     * @return array{0: Product, 1: array<string, mixed>}
     */
    private function shopCart(): array
    {
        $product = $this->product();

        return [$product, ['cart' => [$product->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]]]];
    }

    private function product(): Product
    {
        $category = Category::factory()->create([
            'slug' => 'coffee-tables',
            'section' => 'shop',
            'is_active' => true,
        ]);

        return Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 5,
            'price' => 1000,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function pendingOrder(User $user, Product $product, array $overrides = []): Order
    {
        $order = Order::query()->create(array_merge([
            'user_id' => $user->id,
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => '9876543210',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'country' => 'India',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'packing_cost' => 0,
            'total' => 1000,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'expires_at' => now()->addDay(),
        ], $overrides));

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
            'total' => $product->price,
        ]);

        return $order;
    }

    /** @return array<string, mixed> */
    private function addressPayload(User $user): array
    {
        return [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'customer_email' => $user->email,
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
}
