<?php

namespace Tests\Feature;

use App\Exceptions\RazorpayReconciliationRequiredException;
use App\Mail\AdminPaymentReceivedMail;
use App\Mail\PaymentSuccessfulMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderPaymentService;
use App\Services\RazorpayService;
use App\Support\OrderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RazorpayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'services.razorpay.webhook_secret' => 'whsec_test',
            'services.admin_email' => 'admin@example.com',
            'mail.default' => 'array',
            'mail.from.address' => 'shop@example.com',
            'mail.from.name' => 'Test Shop',
            'queue.default' => 'sync',
        ]);
    }

    private function actingForOrder(Order $order)
    {
        $user = User::factory()->create([
            'email' => $order->customer_email,
            'mobile' => preg_replace('/\D/', '', $order->customer_phone) ?: '9876543210',
        ]);

        return $this->actingAs($user)
            ->withSession([OrderAccess::SESSION_KEY => $order->id]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'expires_at' => now()->addDay(),
        ], $overrides));
    }

    private function addShopItem(Order $order, int $stock = 5, int $quantity = 1): Product
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => $stock,
            'price' => 1000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => $quantity,
            'total' => $product->price * $quantity,
        ]);

        return $product;
    }

    private function signature(string $razorpayOrderId, string $paymentId): string
    {
        return hash_hmac('sha256', $razorpayOrderId.'|'.$paymentId, 'rzp_test_secret');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakePayment(string $paymentId, string $razorpayOrderId, array $overrides = []): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/*' => Http::response(array_merge([
                'id' => $paymentId,
                'order_id' => $razorpayOrderId,
                'amount' => 119900,
                'currency' => 'INR',
                'status' => 'captured',
            ], $overrides), 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function postWebhook(array $payment, string $event = 'payment.captured')
    {
        $payload = [
            'event' => $event,
            'payload' => [
                'payment' => [
                    'entity' => $payment,
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'whsec_test');

        return $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ], $body);
    }

    public function test_create_order_api_requires_store_order_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.create-order'), [
            'amount' => 10000,
            'currency' => 'INR',
            'receipt' => 'TEST-1',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_order_api_for_store_order(): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_api_test',
                'amount' => 119900,
                'currency' => 'INR',
            ], 200),
        ]);

        $order = $this->makeOrder();

        $response = $this->actingForOrder($order)
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id]);

        $response->assertOk()
            ->assertJson([
                'order_id' => 'order_api_test',
                'amount' => 119900,
                'currency' => 'INR',
                'key' => 'rzp_test_key',
            ]);

        $this->assertSame('order_api_test', $order->fresh()->razorpay_order_id);
    }

    public function test_missing_stored_razorpay_order_id_fails(): void
    {
        Http::preventStrayRequests();

        $order = $this->makeOrder();
        $paymentId = 'pay_missing_order';
        $signature = $this->signature('order_forged', $paymentId);

        $response = $this->actingForOrder($order)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id' => 'order_forged',
                'razorpay_signature' => $signature,
            ]);

        $response->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->payment_id);
    }

    public function test_submitted_order_id_mismatch_fails(): void
    {
        Http::preventStrayRequests();

        $order = $this->makeOrder(['razorpay_order_id' => 'order_stored']);
        $paymentId = 'pay_mismatch';
        $signature = $this->signature('order_other', $paymentId);

        $response = $this->actingForOrder($order)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id' => 'order_other',
                'razorpay_signature' => $signature,
            ]);

        $response->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_verify_payment_api_rejects_invalid_signature(): void
    {
        Http::preventStrayRequests();

        $order = $this->makeOrder(['razorpay_order_id' => 'order_verify_test']);

        $response = $this->actingForOrder($order)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => 'pay_test',
                'razorpay_order_id' => 'order_verify_test',
                'razorpay_signature' => 'invalid',
            ]);

        $response->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_valid_hmac_plus_captured_payment_succeeds(): void
    {
        $order = $this->makeOrder(['razorpay_order_id' => 'order_paid_test']);
        $paymentId = 'pay_valid_test';
        $this->fakePayment($paymentId, 'order_paid_test');

        $response = $this->actingForOrder($order)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id' => 'order_paid_test',
                'razorpay_signature' => $this->signature('order_paid_test', $paymentId),
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame($paymentId, $order->fresh()->payment_id);
    }

    public function test_valid_hmac_plus_authorized_payment_does_not_mark_paid(): void
    {
        $order = $this->makeOrder(['razorpay_order_id' => 'order_auth_test']);
        $paymentId = 'pay_authorized';
        $this->fakePayment($paymentId, 'order_auth_test', ['status' => 'authorized']);

        $response = $this->actingForOrder($order)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id' => 'order_auth_test',
                'razorpay_signature' => $this->signature('order_auth_test', $paymentId),
            ]);

        $response->assertStatus(409);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->payment_id);
    }

    public function test_payment_amount_mismatch_fails(): void
    {
        $order = $this->makeOrder(['razorpay_order_id' => 'order_amt_test']);
        $paymentId = 'pay_wrong_amount';
        $this->fakePayment($paymentId, 'order_amt_test', ['amount' => 100]);

        $response = $this->actingForOrder($order)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id' => 'order_amt_test',
                'razorpay_signature' => $this->signature('order_amt_test', $paymentId),
            ]);

        $response->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_currency_mismatch_fails(): void
    {
        $order = $this->makeOrder(['razorpay_order_id' => 'order_ccy_test']);
        $paymentId = 'pay_wrong_ccy';
        $this->fakePayment($paymentId, 'order_ccy_test', ['currency' => 'USD']);

        $response = $this->actingForOrder($order)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id' => 'order_ccy_test',
                'razorpay_signature' => $this->signature('order_ccy_test', $paymentId),
            ]);

        $response->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_razorpay_api_failure_does_not_mark_paid(): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/*' => Http::response(['error' => ['description' => 'secret']], 500),
        ]);

        $order = $this->makeOrder(['razorpay_order_id' => 'order_api_fail']);
        $paymentId = 'pay_api_fail';

        $response = $this->actingForOrder($order)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id' => 'order_api_fail',
                'razorpay_signature' => $this->signature('order_api_fail', $paymentId),
            ]);

        $response->assertStatus(503);
        $response->assertJson(['message' => 'Payment confirmation is temporarily unavailable. Please wait a moment.']);
        $response->assertDontSee('secret');
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_repeated_callback_does_not_duplicate_stock_or_email(): void
    {
        Mail::fake();

        $order = $this->makeOrder(['razorpay_order_id' => 'order_repeat']);
        $product = $this->addShopItem($order, 5);
        $paymentId = 'pay_repeat';
        $this->fakePayment($paymentId, 'order_repeat');
        $signature = $this->signature('order_repeat', $paymentId);

        $payments = app(OrderPaymentService::class);
        $payments->verifyAndComplete($order, $paymentId, 'order_repeat', $signature);
        $payments->verifyAndComplete($order->fresh(), $paymentId, 'order_repeat', $signature);

        Mail::assertQueued(PaymentSuccessfulMail::class, 1);
        Mail::assertQueued(AdminPaymentReceivedMail::class, 1);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
    }

    public function test_valid_webhook_retry_remains_idempotent(): void
    {
        Mail::fake();

        $order = $this->makeOrder(['razorpay_order_id' => 'order_hook']);
        $product = $this->addShopItem($order, 3);
        $paymentId = 'pay_hook';
        $this->fakePayment($paymentId, 'order_hook');

        $payload = [
            'id' => $paymentId,
            'order_id' => 'order_hook',
            'status' => 'captured',
            'amount' => 119900,
            'currency' => 'INR',
        ];

        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'ok']);
        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'already_processed']);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(2, $product->fresh()->stock);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);
    }

    public function test_captured_payment_after_local_expiry_is_flagged_without_fulfilment(): void
    {
        $logs = collect();
        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logs) {
            $logs->push($event);
        });
        Mail::fake();

        $order = $this->makeOrder([
            'razorpay_order_id' => 'order_expired',
            'expires_at' => now()->subHour(),
        ]);
        $product = $this->addShopItem($order, 5);
        $paymentId = 'pay_expired';
        $this->fakePayment($paymentId, 'order_expired');

        $this->expectException(RazorpayReconciliationRequiredException::class);

        try {
            app(OrderPaymentService::class)->verifyAndComplete(
                $order,
                $paymentId,
                'order_expired',
                $this->signature('order_expired', $paymentId),
            );
        } finally {
            $fresh = $order->fresh();
            $this->assertSame('pending', $fresh->status);
            $this->assertNull($fresh->payment_id);
            $this->assertSame(5, $product->fresh()->stock);
            Mail::assertNothingSent();
            $this->assertTrue($logs->contains(function (MessageLogged $event) use ($order) {
                return $event->message === 'Razorpay captured payment requires reconciliation.'
                    && ($event->context['event'] ?? null) === 'razorpay.reconciliation_required'
                    && ($event->context['order_id'] ?? null) === $order->id
                    && ! array_key_exists('customer_email', $event->context)
                    && ! array_key_exists('customer_phone', $event->context);
            }));
        }
    }

    public function test_webhook_captured_payment_after_expiry_returns_reconciliation_required(): void
    {
        $logs = collect();
        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logs) {
            $logs->push($event);
        });

        $order = $this->makeOrder([
            'razorpay_order_id' => 'order_hook_expired',
            'status' => 'cancelled',
            'expires_at' => null,
        ]);
        $product = $this->addShopItem($order, 4);
        $paymentId = 'pay_hook_expired';
        $this->fakePayment($paymentId, 'order_hook_expired');

        $this->postWebhook([
            'id' => $paymentId,
            'order_id' => 'order_hook_expired',
            'status' => 'captured',
            'amount' => 119900,
            'currency' => 'INR',
        ])->assertOk()->assertJson(['status' => 'reconciliation_required']);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertTrue($logs->contains(fn (MessageLogged $event) => ($event->context['event'] ?? null) === 'razorpay.reconciliation_required'));
    }

    public function test_razorpay_service_rejects_amount_below_minimum(): void
    {
        $service = app(RazorpayService::class);
        $result = $service->createPaymentOrder(50, 'TEST-LOW');

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['status']);
    }
}
