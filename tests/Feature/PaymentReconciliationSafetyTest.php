<?php

namespace Tests\Feature;

use App\Exceptions\RazorpayReconciliationRequiredException;
use App\Mail\PaymentSuccessfulMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderPaymentService;
use App\Services\PendingOrderExpiry;
use App\Services\RazorpayService;
use App\Support\CheckoutPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentReconciliationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private bool $paymentsFaked = false;

    /** @var array<string, array<string, mixed>> */
    private array $scriptedPayments = [];

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
            'queue.default' => 'sync',
            'checkout.payments_enabled' => true,
        ]);
    }

    public function test_captured_payment_with_stock_is_paid_once(): void
    {
        Mail::fake();
        $order = $this->makeOrder();
        $product = $this->addItem($order, 3);
        $this->capture($order, 'pay_ok');

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('pay_ok', $order->fresh()->payment_id);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);

        $this->capture($order->fresh(), 'pay_ok');
        $this->assertSame(2, $product->fresh()->stock);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);
    }

    public function test_captured_payment_with_insufficient_stock_is_reconciliation_required(): void
    {
        $order = $this->makeOrder();
        $product = $this->addItem($order, 0, 1);
        $this->capture($order, 'pay_nostock');

        $fresh = $order->fresh();
        $this->assertSame('reconciliation_required', $fresh->status);
        $this->assertSame('pay_nostock', $fresh->payment_id);
        $this->assertSame('insufficient_stock', $fresh->reconciliation_reason);
        $this->assertNull($fresh->stock_deducted_at);
        $this->assertNull($fresh->expires_at);
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_reconciliation_order_cannot_start_another_payment(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['is_admin' => false]);
        $order = $this->makeOrder([
            'user_id' => $user->id,
            'status' => 'reconciliation_required',
            'payment_id' => 'pay_held',
            'reconciliation_reason' => 'insufficient_stock',
            'expires_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('checkout.pay', $order))
            ->assertOk()
            ->assertSee('Payment received — order under review', false)
            ->assertDontSee('checkout.razorpay.com', false)
            ->assertDontSee('api/create-order', false);

        $this->actingAs($user)
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id])
            ->assertStatus(422)
            ->assertJson(['message' => 'Payment was received and your order is under review.']);

        $this->assertSame('pay_held', $order->fresh()->payment_id);
    }

    public function test_expiry_skips_reconciliation_and_captured_pending_rows(): void
    {
        $review = $this->makeOrder([
            'status' => 'reconciliation_required',
            'payment_id' => 'pay_review',
            'reconciliation_reason' => 'insufficient_stock',
            'expires_at' => now()->subHour(),
        ]);
        $capturedPending = $this->makeOrder([
            'order_number' => Order::generateOrderNumber(),
            'status' => 'pending',
            'payment_id' => 'pay_still_pending',
            'expires_at' => now()->subHour(),
        ]);
        $unpaid = $this->makeOrder([
            'order_number' => Order::generateOrderNumber(),
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('orders:expire-pending')
            ->expectsOutput('Expired 1 pending order(s); skipped 1.')
            ->assertSuccessful();

        $this->assertSame('reconciliation_required', $review->fresh()->status);
        $this->assertSame('pay_review', $review->fresh()->payment_id);
        $this->assertSame('pending', $capturedPending->fresh()->status);
        $this->assertSame('pay_still_pending', $capturedPending->fresh()->payment_id);
        $this->assertSame('cancelled', $unpaid->fresh()->status);
        $this->assertFalse(PendingOrderExpiry::expireIfStillPending($review->fresh()));
    }

    public function test_repeated_webhook_is_idempotent_and_second_capture_is_flagged(): void
    {
        $order = $this->makeOrder();
        $product = $this->addItem($order, 4);

        $this->postWebhook($this->paymentPayload($order, 'pay_first'))
            ->assertOk()
            ->assertJson(['status' => 'ok']);
        $this->postWebhook($this->paymentPayload($order, 'pay_first'))
            ->assertOk()
            ->assertJson(['status' => 'already_processed']);

        $this->postWebhook($this->paymentPayload($order, 'pay_second'))
            ->assertOk()
            ->assertJson(['status' => 'duplicate_capture_flagged']);

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('pay_first', $fresh->payment_id);
        $this->assertSame(['pay_second'], $fresh->reconciliation_meta['extra_payment_ids']);
        $this->assertSame(3, $product->fresh()->stock);

        $this->postWebhook($this->paymentPayload($order, 'pay_second'))
            ->assertOk()
            ->assertJson(['status' => 'duplicate_capture_flagged']);
        $this->assertSame(['pay_second'], $order->fresh()->reconciliation_meta['extra_payment_ids']);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_invalid_signature_and_mismatched_payment_change_nothing(): void
    {
        $order = $this->makeOrder();
        $this->addItem($order, 2);

        $this->postWebhook($this->paymentPayload($order, 'pay_bad'), 'payment.captured', 'wrong-secret')
            ->assertStatus(400);

        $this->scriptPayment([
            'id' => 'pay_wrong',
            'order_id' => 'order_other',
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'authorized',
        ]);

        $signature = hash_hmac('sha256', $order->razorpay_order_id.'|pay_wrong', 'rzp_test_secret');
        $shopper = User::factory()->create(['is_admin' => false, 'email' => $order->customer_email]);
        $order->update(['user_id' => $shopper->id]);
        $this->actingAs($shopper)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => 'pay_wrong',
                'razorpay_order_id' => $order->razorpay_order_id,
                'razorpay_signature' => $signature,
            ])->assertStatus(400);

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->payment_id);
        $this->assertNull($fresh->reconciliation_reason);
    }

    public function test_payment_id_already_on_another_order_is_not_reassigned(): void
    {
        $other = $this->makeOrder([
            'order_number' => Order::generateOrderNumber(),
            'razorpay_order_id' => 'order_other_owner',
            'status' => 'paid',
            'payment_id' => 'pay_taken',
        ]);
        $order = $this->makeOrder();
        $product = $this->addItem($order, 6);
        $this->capture($order, 'pay_taken');

        $fresh = $order->fresh();
        $this->assertSame('reconciliation_required', $fresh->status);
        $this->assertNull($fresh->payment_id);
        $this->assertSame(['pay_taken'], $fresh->reconciliation_meta['conflicting_payment_ids']);
        $this->assertSame('paid', $other->fresh()->status);
        $this->assertSame('pay_taken', $other->fresh()->payment_id);
        $this->assertSame(6, $product->fresh()->stock);
        $this->assertNull($fresh->stock_deducted_at);
    }

    public function test_checkout_switch_blocks_initiation_but_not_callback_webhook_or_admin(): void
    {
        config(['checkout.payments_enabled' => false]);
        $this->assertFalse(CheckoutPayments::enabled());

        $user = User::factory()->create(['is_admin' => false]);
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 4,
            'price' => 1000,
        ]);

        $this->get(route('cart.index'))->assertOk();
        $this->actingAs($user)
            ->post(route('cart.add', $product), $this->purchaseInput())
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Checkout is temporarily unavailable', false)
            ->assertDontSee('rzp_test_key', false);

        $this->actingAs($user)->post(route('checkout.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '9999999999',
            'country' => 'India',
            'house_building' => '1 Test House',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
        ])->assertRedirect(route('checkout.index'));
        $this->assertSame(0, Order::query()->count());

        $order = $this->makeOrder(['user_id' => $user->id]);
        $this->addItem($order, 4);

        $this->actingAs($user)
            ->get(route('checkout.pay', $order))
            ->assertOk()
            ->assertSee('Checkout is temporarily unavailable', false)
            ->assertDontSee('checkout.razorpay.com', false);

        $this->actingAs($user)
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id])
            ->assertStatus(503);

        $this->postWebhook($this->paymentPayload($order, 'pay_switch'))
            ->assertOk()
            ->assertJson(['status' => 'ok']);
        $this->assertSame('paid', $order->fresh()->status);

        $this->actingAsAdmin()
            ->get(route('admin.orders.index'))
            ->assertOk();
        $this->actingAsAdmin()
            ->get(route('admin.orders.show', $order))
            ->assertOk();
    }

    public function test_customer_and_admin_show_reconciliation_without_a_mark_paid_control(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $order = $this->makeOrder([
            'user_id' => $user->id,
            'status' => 'reconciliation_required',
            'payment_id' => 'pay_visible',
            'razorpay_order_id' => 'order_visible',
            'reconciliation_reason' => 'insufficient_stock',
            'expires_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('checkout.pay', $order))
            ->assertOk()
            ->assertSee('Payment received — order under review', false)
            ->assertDontSee('Order Confirmed', false)
            ->assertDontSee('pay_visible', false);

        $this->actingAs($user)
            ->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('Payment received — order under review', false)
            ->assertDontSee('Order Confirmed', false);

        $this->actingAs($user)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('Payment received — order under review', false);

        $admin = $this->actingAsAdmin();
        $admin->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Reconciliation required', false)
            ->assertSee('pay_visible', false)
            ->assertSee('Status changes are locked', false)
            ->assertDontSee('name="status"', false);

        $admin->put(route('admin.orders.update', $order), [
            'status' => 'paid',
            'admin_notes' => 'Reviewed with the studio.',
        ])->assertRedirect()->assertSessionHasErrors('status');

        $fresh = $order->fresh();
        $this->assertSame('reconciliation_required', $fresh->status);
        $this->assertNull($fresh->admin_notes);

        $admin->put(route('admin.orders.update', $order), [
            'admin_notes' => 'Hold for stock.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Hold for stock.', $order->fresh()->admin_notes);
        $this->assertSame('reconciliation_required', $order->fresh()->status);
    }

    public function test_second_captured_payment_shows_review_and_keeps_the_original_payment(): void
    {
        Mail::fake();
        $user = User::factory()->create(['is_admin' => false, 'email' => 'jane@example.com']);
        $order = $this->makeOrder(['user_id' => $user->id]);
        $product = $this->addItem($order, 4);

        $this->postWebhook($this->paymentPayload($order, 'pay_original'))
            ->assertOk()
            ->assertJson(['status' => 'ok']);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);

        $secondSignature = hash_hmac('sha256', $order->razorpay_order_id.'|pay_extra', 'rzp_test_secret');
        $this->scriptPayment([
            'id' => 'pay_extra',
            'order_id' => $order->razorpay_order_id,
            'amount' => RazorpayService::amountPaiseFromRupees($order->total),
            'currency' => 'INR',
            'status' => 'captured',
        ]);

        $this->post(route('checkout.pay.verify', $order), [
            'razorpay_payment_id' => 'pay_extra',
            'razorpay_order_id' => $order->razorpay_order_id,
            'razorpay_signature' => $secondSignature,
        ])->assertRedirect(route('checkout.pay', $order));

        $this->postWebhook($this->paymentPayload($order, 'pay_extra'))
            ->assertOk()
            ->assertJson(['status' => 'duplicate_capture_flagged']);

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('pay_original', $fresh->payment_id);
        $this->assertSame(['pay_extra'], $fresh->reconciliation_meta['extra_payment_ids']);
        $this->assertSame('duplicate_capture', $fresh->reconciliation_reason);
        $this->assertSame(3, $product->fresh()->stock);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);

        $this->actingAs($user)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => 'pay_extra',
                'razorpay_order_id' => $order->razorpay_order_id,
                'razorpay_signature' => $secondSignature,
            ])
            ->assertStatus(409)
            ->assertJson(['message' => 'Payment was received and your order is under review.'])
            ->assertJsonMissing(['success' => true]);

        $forbidden = [
            'Order Confirmed',
            'placed successfully',
            'payment successful',
            'We will begin processing',
            'pay_original',
            'pay_extra',
        ];

        foreach ([route('checkout.pay', $order), route('checkout.success', $order), route('account')] as $url) {
            $page = $this->actingAs($user)->get($url)->assertOk()->assertSee(Order::REVIEW_HEADING, false);
            foreach ($forbidden as $phrase) {
                $page->assertDontSee($phrase, false);
            }
        }

        $this->actingAs($user)
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id])
            ->assertStatus(422);

        $admin = $this->actingAsAdmin();
        $admin->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Reconciliation required', false)
            ->assertSee('pay_original', false)
            ->assertSee('pay_extra', false)
            ->assertSee('Recorded status: Paid', false);

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame('pay_original', $order->fresh()->payment_id);
    }

    public function test_repeating_the_same_payment_on_reconciliation_changes_nothing(): void
    {
        $order = $this->makeOrder();
        $product = $this->addItem($order, 0);
        $this->postWebhook($this->paymentPayload($order, 'pay_same'))
            ->assertOk()
            ->assertJson(['status' => 'reconciliation_required']);

        $before = $order->fresh()->only([
            'status',
            'payment_id',
            'reconciliation_reason',
            'reconciliation_meta',
            'stock_deducted_at',
        ]);

        $this->postWebhook($this->paymentPayload($order, 'pay_same'))
            ->assertOk()
            ->assertJson(['status' => 'reconciliation_required']);

        $this->assertSame($before, $order->fresh()->only(array_keys($before)));
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_expiry_skips_an_order_after_payment_has_committed(): void
    {
        $order = $this->makeOrder();
        $product = $this->addItem($order, 3);
        $this->capture($order, 'pay_then_expire');
        $order->refresh();
        $this->assertSame('paid', $order->status);
        $order->update(['expires_at' => now()->subHour()]);

        $this->assertFalse(PendingOrderExpiry::expireIfStillPending($order->fresh()));
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('pay_then_expire', $order->fresh()->payment_id);
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_verify_payment_does_not_report_success_for_reconciliation(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'email' => 'jane@example.com']);
        $order = $this->makeOrder([
            'user_id' => $user->id,
            'status' => 'reconciliation_required',
            'payment_id' => 'pay_held_again',
            'reconciliation_reason' => 'insufficient_stock',
        ]);
        $this->addItem($order, 0);
        $signature = hash_hmac('sha256', $order->razorpay_order_id.'|pay_held_again', 'rzp_test_secret');
        $this->scriptPayment([
            'id' => 'pay_held_again',
            'order_id' => $order->razorpay_order_id,
            'amount' => RazorpayService::amountPaiseFromRupees($order->total),
            'currency' => 'INR',
            'status' => 'captured',
        ]);

        $this->actingAs($user)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => 'pay_held_again',
                'razorpay_order_id' => $order->razorpay_order_id,
                'razorpay_signature' => $signature,
            ])
            ->assertStatus(409)
            ->assertJsonMissing(['success' => true]);

        $this->assertSame('reconciliation_required', $order->fresh()->status);
        $this->assertSame('pay_held_again', $order->fresh()->payment_id);
    }

    public function test_empty_line_item_order_is_not_marked_paid(): void
    {
        $order = $this->makeOrder();
        $this->capture($order, 'pay_empty_lines');

        $fresh = $order->fresh();
        $this->assertSame('reconciliation_required', $fresh->status);
        $this->assertSame('pay_empty_lines', $fresh->payment_id);
        $this->assertSame('no_line_items', $fresh->reconciliation_reason);
        $this->assertNull($fresh->stock_deducted_at);
    }

    public function test_pending_razorpay_order_without_capture_cannot_be_marked_processing(): void
    {
        $order = $this->makeOrder();

        $this->actingAsAdmin()
            ->put(route('admin.orders.update', $order), [
                'status' => 'processing',
                'admin_notes' => 'Pack after confirmation.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->admin_notes);
        $this->assertNull($fresh->payment_id);
        $this->assertNull($fresh->reconciliation_reason);
        $this->assertNull($fresh->reconciliation_meta);
        $this->assertNull($fresh->stock_deducted_at);
    }

    public function test_stock_change_after_checkout_does_not_partially_deduct(): void
    {
        $order = $this->makeOrder();
        $product = $this->addItem($order, 2, 2);
        $product->update(['stock' => 1]);

        $this->capture($order, 'pay_short');

        $this->assertSame('reconciliation_required', $order->fresh()->status);
        $this->assertSame('pay_short', $order->fresh()->payment_id);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertNull($order->fresh()->stock_deducted_at);
    }

    private function capture(Order $order, string $paymentId): void
    {
        $this->scriptPayment([
            'id' => $paymentId,
            'order_id' => $order->razorpay_order_id,
            'amount' => RazorpayService::amountPaiseFromRupees($order->total),
            'currency' => 'INR',
            'status' => 'captured',
        ]);

        try {
            app(OrderPaymentService::class)->completeFromGateway(
                $order,
                $paymentId,
                (string) $order->razorpay_order_id,
            );
        } catch (RazorpayReconciliationRequiredException) {
            // Customer verify throws; gateway completion returns a status.
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
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
            'razorpay_order_id' => 'order_'.strtolower(substr(uniqid(), -8)),
            'expires_at' => now()->addDay(),
        ], $overrides));
    }

    private function addItem(Order $order, int $stock, int $quantity = 1): Product
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables-'.strtolower(substr(uniqid(), -6))]);
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

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(Order $order, string $paymentId): array
    {
        return [
            'id' => $paymentId,
            'order_id' => $order->razorpay_order_id,
            'status' => 'captured',
            'amount' => RazorpayService::amountPaiseFromRupees($order->fresh()->total),
            'currency' => 'INR',
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function scriptPayment(array $payment): void
    {
        $this->scriptedPayments[(string) $payment['id']] = $payment;

        if ($this->paymentsFaked) {
            return;
        }

        $this->paymentsFaked = true;
        Http::fake(function ($request) {
            foreach ($this->scriptedPayments as $id => $payment) {
                if (str_contains($request->url(), (string) $id)) {
                    return Http::response($payment, 200);
                }
            }

            return Http::response(['message' => 'not scripted'], 404);
        });
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function postWebhook(array $payment, string $event = 'payment.captured', ?string $secret = null)
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
        $signature = hash_hmac('sha256', $body, $secret ?? 'whsec_test');
        $this->scriptPayment($payment);

        return $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ], $body);
    }
}
