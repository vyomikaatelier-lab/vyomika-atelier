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
use App\Models\PaymentWebhookReceipt;
use App\Models\User;
use App\Services\RazorpayService;
use App\Support\CartGuard;
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
            'checkout.payments_unrestricted' => false,
            'checkout.payments_allowed_emails' => 'owner-test@example.com',
            'checkout.payments_expires_at' => now()->addMinutes(30)->toIso8601String(),
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
        $this->assertStringContainsString(
            "env('CHECKOUT_PAYMENTS_EXPIRES_AT', '')",
            $checkout
        );
        $this->assertMatchesRegularExpression(
            "/'payments_unrestricted'\\s*=>\\s*filter_var\\(env\\('CHECKOUT_PAYMENTS_UNRESTRICTED',\\s*false\\),\\s*FILTER_VALIDATE_BOOLEAN\\)/",
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
        $this->assertSame('paid', PaymentWebhookReceipt::query()->where('order_id', $order->id)->value('outcome'));
        $this->assertSame(hash('sha256', $body), PaymentWebhookReceipt::query()->value('payload_sha256'));
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

        [$product, $session] = $this->shopCart([
            'sku' => 'VA-LIVE-TEST-R1',
            'is_gallery_visible' => false,
            'robots_index' => false,
        ]);
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

    public function test_empty_allowlist_denies_initiation_when_payments_are_on(): void
    {
        config(['checkout.payments_allowed_emails' => '']);
        $product = $this->product();
        $customer = User::factory()->create(['email' => 'any@example.com']);
        $order = $this->pendingOrder($customer, $product);

        $this->assertFalse(CheckoutPayments::canInitiate($customer));
        $this->actingAs($customer)
            ->withSession([OrderAccess::SESSION_KEY => $order->id])
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id])
            ->assertStatus(503)
            ->assertJson(['message' => CheckoutPayments::UNAVAILABLE_MESSAGE]);
        $this->assertNull($order->fresh()->razorpay_order_id);
    }

    public function test_missing_or_passed_expiry_denies_initiation(): void
    {
        $owner = User::factory()->create(['email' => 'owner-test@example.com']);

        config(['checkout.payments_expires_at' => '']);
        $this->assertFalse(CheckoutPayments::windowOpen());
        $this->assertFalse(CheckoutPayments::canInitiate($owner));

        config(['checkout.payments_expires_at' => now()->subMinute()->toIso8601String()]);
        $this->assertFalse(CheckoutPayments::canInitiate($owner));
    }

    public function test_ordinary_order_by_the_allowlisted_customer_is_not_suppressed(): void
    {
        Mail::fake();
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_normal_mail',
                'amount' => 100000,
                'currency' => 'INR',
            ], 200),
        ]);

        [, $session] = $this->shopCart();
        $owner = User::factory()->create(['email' => 'owner-test@example.com']);

        $this->actingAs($owner)
            ->withSession($session)
            ->post(route('checkout.store'), $this->addressPayload($owner) + [
                'suppress_notifications' => '1',
            ])
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertFalse(CheckoutPayments::orderMailSuppressed($order));
        Mail::assertQueued(OrderReceivedMail::class, 1);
        Mail::assertQueued(AdminNewOrderMail::class, 1);
    }

    public function test_one_test_order_settles_after_initiation_is_disabled(): void
    {
        Mail::fake();
        $payment = [
            'id' => 'pay_once',
            'order_id' => 'order_once',
            'amount' => 100000,
            'currency' => 'INR',
            'status' => 'captured',
        ];
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_once',
                'amount' => 100000,
                'currency' => 'INR',
            ], 200),
            'api.razorpay.com/v1/payments/*' => Http::response($payment, 200),
        ]);

        [$product, $session] = $this->shopCart([
            'sku' => 'VA-LIVE-TEST-ONCE',
            'slug' => 'va-live-test-once',
            'is_gallery_visible' => false,
        ]);
        $owner = User::factory()->create(['email' => 'owner-test@example.com']);

        $this->actingAs($owner)
            ->withSession($session)
            ->post(route('checkout.store'), $this->addressPayload($owner))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertTrue(CheckoutPayments::orderMailSuppressed($order));
        $this->assertSame('order_once', $order->razorpay_order_id);
        $this->assertFalse(CheckoutPayments::canInitiate($owner));

        $this->actingAs($owner)
            ->withSession($session)
            ->post(route('checkout.store'), $this->addressPayload($owner))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', CheckoutPayments::UNAVAILABLE_MESSAGE);
        $this->assertSame(1, Order::query()->count());

        config(['checkout.payments_enabled' => false]);
        $this->assertTrue(CheckoutPayments::canContinuePayment($owner, $order->fresh()));

        $this->actingAs($owner)
            ->get(route('checkout.pay', $order))
            ->assertOk()
            ->assertViewIs('checkout.pay');
        $this->actingAs($owner)
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id])
            ->assertOk()
            ->assertJson(['order_id' => 'order_once']);

        $other = User::factory()->create(['email' => 'other@example.com']);
        $this->actingAs($other)->get(route('shop.show', $product->slug))->assertNotFound();
        $this->actingAs($other)
            ->from(route('cart.index'))
            ->post(route('cart.add', $product), ['quantity' => 1])
            ->assertSessionHas('error', CartGuard::MSG_INACTIVE);
        $this->actingAs($other)
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id])
            ->assertNotFound();

        $captured = [
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => $payment]],
        ];
        $body = json_encode($captured);
        $this->postSignedWebhook($body)->assertOk()->assertJson(['status' => 'ok']);
        $this->postSignedWebhook($body)->assertOk()->assertJson(['status' => 'already_processed']);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(1, PaymentWebhookReceipt::query()->count());
        $this->assertSame('paid', PaymentWebhookReceipt::query()->value('outcome'));
        $this->assertSame(hash('sha256', $body), PaymentWebhookReceipt::query()->value('payload_sha256'));
        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        $paid = json_encode([
            'event' => 'order.paid',
            'payload' => ['payment' => ['entity' => $payment]],
        ]);
        $this->postSignedWebhook($paid)->assertOk()->assertJson(['status' => 'already_processed']);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(2, PaymentWebhookReceipt::query()->count());
        $this->assertSame('already_processed', PaymentWebhookReceipt::query()->where('event', 'order.paid')->value('outcome'));
    }

    public function test_passed_expiry_blocks_resume_but_signed_webhook_still_settles(): void
    {
        Mail::fake();
        config([
            'checkout.payments_enabled' => false,
            'checkout.payments_expires_at' => now()->subMinute()->toIso8601String(),
        ]);
        $payment = [
            'id' => 'pay_after_expiry',
            'order_id' => 'order_after_expiry',
            'amount' => 100000,
            'currency' => 'INR',
            'status' => 'captured',
        ];
        Http::fake([
            'api.razorpay.com/v1/payments/*' => Http::response($payment, 200),
        ]);

        $product = $this->product([
            'sku' => 'VA-LIVE-TEST-EXP',
            'is_gallery_visible' => false,
        ]);
        $owner = User::factory()->create(['email' => 'owner-test@example.com']);
        $order = $this->pendingOrder($owner, $product, [
            'razorpay_order_id' => 'order_after_expiry',
            'shipping_snapshot' => [
                CheckoutPayments::SNAPSHOT_SUPPRESS_NOTIFICATIONS => true,
            ],
        ]);

        $this->assertFalse(CheckoutPayments::canContinuePayment($owner, $order));
        $this->actingAs($owner)
            ->get(route('checkout.pay', $order))
            ->assertOk()
            ->assertSee('Checkout is temporarily unavailable', false);

        $body = json_encode([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => $payment]],
        ]);
        $this->postSignedWebhook($body)->assertOk()->assertJson(['status' => 'ok']);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame('paid', PaymentWebhookReceipt::query()->value('outcome'));
        Mail::assertNothingQueued();
    }

    public function test_invalid_webhook_signature_does_not_settle_or_store_a_receipt(): void
    {
        $product = $this->product();
        $owner = User::factory()->create(['email' => 'owner-test@example.com']);
        $order = $this->pendingOrder($owner, $product, [
            'razorpay_order_id' => 'order_bad_sig',
        ]);
        $body = json_encode([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_bad',
                'order_id' => 'order_bad_sig',
                'amount' => 100000,
                'currency' => 'INR',
                'status' => 'captured',
            ]]],
        ]);

        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => 'not-a-valid-signature',
        ], $body)->assertStatus(400);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->payment_id);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(0, PaymentWebhookReceipt::query()->count());
    }

    public function test_one_rupee_meets_the_gateway_minimum(): void
    {
        $this->assertSame(100, RazorpayService::MIN_AMOUNT_PAISE);
        $this->assertSame(100, RazorpayService::amountPaiseFromRupees(1));
        $this->assertLessThan(RazorpayService::MIN_AMOUNT_PAISE, RazorpayService::amountPaiseFromRupees('0.99'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Product, 1: array<string, mixed>}
     */
    private function shopCart(array $overrides = []): array
    {
        $product = $this->product($overrides);

        return [$product, ['cart' => [$product->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]]]];
    }

    /** @param array<string, mixed> $overrides */
    private function product(array $overrides = []): Product
    {
        $category = Category::factory()->create([
            'slug' => 'coffee-tables',
            'section' => 'shop',
            'is_active' => true,
        ]);

        return Product::factory()->shop()->create(array_merge([
            'category_id' => $category->id,
            'stock' => 5,
            'price' => 1000,
        ], $overrides));
    }

    private function postSignedWebhook(string $body): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_test'),
        ], $body);
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
