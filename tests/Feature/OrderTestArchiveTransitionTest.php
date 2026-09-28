<?php

namespace Tests\Feature;

use App\Mail\AdminPaymentReceivedMail;
use App\Mail\PaymentSuccessfulMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderTestArchive;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class OrderTestArchiveTransitionTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private ?string $activePaymentId = null;

    private ?string $activeGatewayId = null;

    private ?string $activeRefundStatus = null;

    protected function setUp(): void
    {
        parent::setUp();

        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if ($this->activePaymentId === null || $this->activeGatewayId === null) {
                return Http::response([], 500);
            }

            if (str_ends_with($request->url(), '/refund')) {
                $body = json_decode($request->body(), true);
                $body = is_array($body) ? $body : [];

                return Http::response([
                    'id' => 'rfnd_'.($this->activeRefundStatus ?? 'processed'),
                    'payment_id' => $this->activePaymentId,
                    'amount' => (int) ($body['amount'] ?? 0),
                    'currency' => 'INR',
                    'status' => $this->activeRefundStatus ?? 'processed',
                    'receipt' => (string) ($body['receipt'] ?? ''),
                    'notes' => is_array($body['notes'] ?? null) ? $body['notes'] : [],
                ], 200);
            }

            return Http::response($this->capturedBody($this->activePaymentId, $this->activeGatewayId), 200);
        });
        config([
            'cache.stores.database.connection' => 'sqlite',
            'cache.stores.database.lock_connection' => 'sqlite',
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'services.razorpay.webhook_secret' => 'whsec_test',
            'services.admin_email' => 'admin@example.com',
            'mail.default' => 'array',
            'mail.from.address' => 'shop@example.com',
            'mail.from.name' => 'Test Shop',
            'queue.default' => 'sync',
            'checkout.payments_enabled' => false,
        ]);
    }

    public function test_callback_settlement_keeps_archive_metadata_and_returns_the_order_to_the_default_list(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
        [$order, $product] = $this->archivedPending($owner, 'VA-CB-SETTLE');
        $archive = $this->archiveSnapshot($order);
        $paymentId = 'pay_cb_settle';
        $this->useGateway($paymentId, (string) $order->razorpay_order_id);

        $this->post(route('checkout.pay.verify', $order), $this->callbackPayload($order, $paymentId))
            ->assertRedirect(route('checkout.success', $order));

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertNotNull($fresh->payment_id);
        $this->assertNotNull($fresh->stock_deducted_at);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertArchiveUnchanged($fresh, $archive);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);
        Mail::assertQueued(AdminPaymentReceivedMail::class, 1);

        $this->actingAsAdmin($manager)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('VA-CB-SETTLE', false)
            ->assertSee('Paid', false)
            ->assertSee('Archived test order', false);

        $this->actingAsAdmin($manager)
            ->get(route('admin.orders.index', ['archived' => 1]))
            ->assertForbidden();

        Http::assertSentCount(1);
    }

    public function test_captured_webhook_keeps_archive_metadata_and_makes_the_order_visible(): void
    {
        Mail::fake();
        $owner = $this->owner();
        [$order, $product] = $this->archivedPending($owner, 'VA-HOOK-PAID');
        $archive = $this->archiveSnapshot($order);
        $paymentId = 'pay_hook_paid';
        $gatewayId = (string) $order->razorpay_order_id;
        $this->useGateway($paymentId, $gatewayId);

        $this->postWebhook($this->capturedEntity($paymentId, $gatewayId))
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertNotNull($fresh->stock_deducted_at);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertArchiveUnchanged($fresh, $archive);
        $this->assertDefaultListShows($owner, 'VA-HOOK-PAID', 'Paid');
    }

    public function test_additional_capture_requires_reconciliation_and_stays_visible(): void
    {
        Mail::fake();
        $owner = $this->owner();
        [$order] = $this->archivedPending($owner, 'VA-HOOK-EXTRA');
        $gatewayId = (string) $order->razorpay_order_id;
        $first = 'pay_hook_first';
        $second = 'pay_hook_extra';
        $this->useGateway($first, $gatewayId);

        $this->postWebhook($this->capturedEntity($first, $gatewayId))->assertOk();
        $archive = $this->archiveSnapshot($order->fresh());
        $this->useGateway($second, $gatewayId);

        $this->postWebhook($this->capturedEntity($second, $gatewayId))
            ->assertOk()
            ->assertJson(['status' => 'duplicate_capture_flagged']);

        $fresh = $order->fresh();
        $this->assertSame('duplicate_capture', $fresh->reconciliation_reason);
        $this->assertTrue($fresh->needsPaymentReview());
        $this->assertArchiveUnchanged($fresh, $archive);
        $this->assertDefaultListShows($owner, 'VA-HOOK-EXTRA', 'Reconciliation required');
    }

    public function test_late_capture_after_cancellation_or_expiry_requires_reconciliation_and_stays_visible(): void
    {
        Mail::fake();
        $owner = $this->owner();

        [$cancelled, $cancelledProduct] = $this->archivedPending($owner, 'VA-LATE-CANCEL', ['status' => 'cancelled']);
        $cancelledArchive = $this->archiveSnapshot($cancelled);
        $cancelledPayment = 'pay_late_cancel';
        $cancelledGateway = (string) $cancelled->razorpay_order_id;
        $this->useGateway($cancelledPayment, $cancelledGateway);

        $this->postWebhook($this->capturedEntity($cancelledPayment, $cancelledGateway))
            ->assertOk()
            ->assertJson(['status' => 'reconciliation_required']);

        $cancelledFresh = $cancelled->fresh();
        $this->assertSame('reconciliation_required', $cancelledFresh->status);
        $this->assertSame('captured_after_cancel', $cancelledFresh->reconciliation_reason);
        $this->assertNull($cancelledFresh->stock_deducted_at);
        $this->assertSame(4, (int) $cancelledProduct->fresh()->stock);
        $this->assertArchiveUnchanged($cancelledFresh, $cancelledArchive);
        $this->assertDefaultListShows($owner, 'VA-LATE-CANCEL', 'Reconciliation required');

        [$expired, $expiredProduct] = $this->archivedPending($owner, 'VA-LATE-EXPIRE', [
            'expires_at' => now()->subHour(),
        ]);
        $expiredArchive = $this->archiveSnapshot($expired);
        $expiredPayment = 'pay_late_expire';
        $expiredGateway = (string) $expired->razorpay_order_id;
        $this->useGateway($expiredPayment, $expiredGateway);

        $this->postWebhook($this->capturedEntity($expiredPayment, $expiredGateway))
            ->assertOk()
            ->assertJson(['status' => 'reconciliation_required']);

        $expiredFresh = $expired->fresh();
        $this->assertSame('reconciliation_required', $expiredFresh->status);
        $this->assertSame('captured_after_expiry', $expiredFresh->reconciliation_reason);
        $this->assertNull($expiredFresh->stock_deducted_at);
        $this->assertSame(4, (int) $expiredProduct->fresh()->stock);
        $this->assertArchiveUnchanged($expiredFresh, $expiredArchive);
        $this->assertDefaultListShows($owner, 'VA-LATE-EXPIRE', 'Reconciliation required');
        Mail::assertNothingQueued();
    }

    public function test_refund_pending_and_processed_states_remain_visible_without_clearing_archive_metadata(): void
    {
        Mail::fake();
        $owner = $this->owner();

        [$pendingOrder] = $this->archivedPending($owner, 'VA-REFUND-PEND');
        $this->settle($pendingOrder, 'pay_refund_pending');
        $pendingArchive = $this->archiveSnapshot($pendingOrder->fresh());
        $this->useGateway((string) $pendingOrder->fresh()->payment_id, (string) $pendingOrder->razorpay_order_id, 'pending');
        $this->submitRefund($owner, $pendingOrder->fresh());

        $pendingFresh = $pendingOrder->fresh();
        $this->assertSame('pending', OrderRefund::query()->where('order_id', $pendingOrder->id)->value('status'));
        $this->assertSame('pending', $pendingFresh->refund_status);
        $this->assertGreaterThan(0, (int) $pendingFresh->refund_pending_amount_paise);
        $this->assertArchiveUnchanged($pendingFresh, $pendingArchive);
        $this->assertDefaultListShows($owner, 'VA-REFUND-PEND', 'Archived test order');

        [$processedOrder] = $this->archivedPending($owner, 'VA-REFUND-DONE');
        $this->settle($processedOrder, 'pay_refund_done');
        $processedArchive = $this->archiveSnapshot($processedOrder->fresh());
        $this->useGateway((string) $processedOrder->fresh()->payment_id, (string) $processedOrder->razorpay_order_id, 'processed');
        $this->submitRefund($owner, $processedOrder->fresh());

        $processedFresh = $processedOrder->fresh();
        $this->assertSame('processed', OrderRefund::query()->where('order_id', $processedOrder->id)->value('status'));
        $this->assertSame('refunded', $processedFresh->refund_status);
        $this->assertGreaterThan(0, (int) $processedFresh->refunded_amount_paise);
        $this->assertArchiveUnchanged($processedFresh, $processedArchive);
        $this->assertDefaultListShows($owner, 'VA-REFUND-DONE', 'Archived test order');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Order, 1: Product}
     */
    private function archivedPending(User $owner, string $number, array $overrides = []): array
    {
        $order = Order::create(array_merge([
            'order_number' => $number,
            'customer_name' => 'Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'total' => 1000,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'payment_id' => null,
            'razorpay_order_id' => 'order_'.strtolower(str_replace('-', '_', $number)),
            'stock_deducted_at' => null,
            'captured_amount_paise' => null,
            'refunded_amount_paise' => 0,
            'refund_pending_amount_paise' => 0,
            'refund_status' => 'none',
            'expires_at' => now()->addDay(),
        ], $overrides));
        $product = $this->attachItem($order);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), [
                'current_password' => 'password',
                'order_number_confirmation' => $number,
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame(OrderTestArchive::REASON_ARCHIVE, $order->fresh()->admin_archive_reason);

        return [$order->fresh(), $product];
    }

    private function settle(Order $order, string $paymentId): void
    {
        $this->useGateway($paymentId, (string) $order->razorpay_order_id);

        $this->post(route('checkout.pay.verify', $order), $this->callbackPayload($order, $paymentId))
            ->assertRedirect(route('checkout.success', $order));
    }

    private function submitRefund(User $owner, Order $order): void
    {
        $page = $this->actingAsAdmin($owner)->get(route('admin.orders.show', $order));
        $page->assertOk();
        preg_match('/name="idempotency_key" value="([^"]+)"/', $page->getContent(), $matches);
        $idempotency = $matches[1] ?? '';
        $this->assertNotSame('', $idempotency);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.store', $order), [
                'idempotency_key' => $idempotency,
                'kind' => 'full',
                'reason_code' => 'customer_request',
                'current_password' => 'password',
                'order_number_confirmation' => $order->order_number,
                'include_shipping' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    private function useGateway(string $paymentId, string $gatewayId, ?string $refundStatus = null): void
    {
        $this->activePaymentId = $paymentId;
        $this->activeGatewayId = $gatewayId;
        $this->activeRefundStatus = $refundStatus;
    }

    /**
     * @return array<string, mixed>
     */
    private function capturedBody(string $paymentId, string $gatewayId): array
    {
        return [
            'id' => $paymentId,
            'order_id' => $gatewayId,
            'amount' => 100000,
            'currency' => 'INR',
            'status' => 'captured',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function capturedEntity(string $paymentId, string $gatewayId): array
    {
        return $this->capturedBody($paymentId, $gatewayId);
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function postWebhook(array $payment)
    {
        $body = json_encode([
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => $payment,
                ],
            ],
        ]);
        $signature = hash_hmac('sha256', (string) $body, 'whsec_test');

        return $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ], $body);
    }

    /**
     * @return array<string, string>
     */
    private function callbackPayload(Order $order, string $paymentId): array
    {
        $gatewayId = (string) $order->razorpay_order_id;

        return [
            'razorpay_payment_id' => $paymentId,
            'razorpay_order_id' => $gatewayId,
            'razorpay_signature' => hash_hmac('sha256', $gatewayId.'|'.$paymentId, 'rzp_test_secret'),
        ];
    }

    /**
     * @return array{at: string, by: int, reason: string}
     */
    private function archiveSnapshot(Order $order): array
    {
        return [
            'at' => (string) $order->admin_archived_at,
            'by' => (int) $order->admin_archived_by_user_id,
            'reason' => (string) $order->admin_archive_reason,
        ];
    }

    /**
     * @param  array{at: string, by: int, reason: string}  $snapshot
     */
    private function assertArchiveUnchanged(Order $order, array $snapshot): void
    {
        $this->assertSame($snapshot['at'], (string) $order->admin_archived_at);
        $this->assertSame($snapshot['by'], (int) $order->admin_archived_by_user_id);
        $this->assertSame($snapshot['reason'], (string) $order->admin_archive_reason);
    }

    private function assertDefaultListShows(User $owner, string $number, string $status): void
    {
        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee($number, false)
            ->assertSee($status, false)
            ->assertSee('Archived test order', false);
    }

    private function attachItem(Order $order): Product
    {
        $category = Category::factory()->create(['slug' => 'archive-tx-'.uniqid()]);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 4,
            'price' => 1000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
        ]);

        return $product;
    }

    private function owner(): User
    {
        return User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
    }
}
