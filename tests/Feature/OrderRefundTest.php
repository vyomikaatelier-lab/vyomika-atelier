<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\Product;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OrderRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'services.razorpay.webhook_secret' => 'whsec_test',
            'checkout.payments_enabled' => false,
            'checkout.refund_recovery_max_pages' => 100,
        ]);

        Http::preventStrayRequests();
    }

    public function test_full_refund_cancels_the_order_and_restores_stock_once(): void
    {
        [$order, $product] = $this->paidOrder();
        $this->fakeRefund(function (array $body) {
            return $this->refundBody($body, 'processed', 'rfnd_full');
        });

        $refund = $this->submit($order, 'full');

        $this->assertSame(OrderRefund::STATUS_PROCESSED, $refund->status);
        $this->assertSame('rfnd_full', $refund->gateway_refund_id);
        $this->assertSame('pay_refund', $order->fresh()->payment_id);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->refund_status);
        $this->assertSame(119900, (int) $order->fresh()->refunded_amount_paise);
        $this->assertSame(19900, (int) $refund->shipping_amount_paise);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(1, $refund->events()->count() > 0 ? $refund->lines()->where('stock_restoration', 'restored')->count() : 0);

        $this->postWebhook($this->refundBody([
            'amount' => 119900,
            'receipt' => $refund->receipt,
            'notes' => [
                'order_number' => $order->order_number,
                'refund_id' => (string) $refund->id,
                'receipt' => $refund->receipt,
            ],
        ], 'processed', 'rfnd_full'));

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(1, OrderRefund::query()->count());
    }

    public function test_partial_refunds_shipping_and_a_second_partial_stay_within_the_captured_amount(): void
    {
        [$order, $first, $second] = $this->paidOrder(twoLines: true);
        $this->fakeRefund(fn (array $body) => $this->refundBody($body, 'processed', 'rfnd_'.$body['amount']));

        $firstItem = OrderItem::query()->where('order_id', $order->id)->where('product_id', $first->id)->firstOrFail();
        $secondItem = OrderItem::query()->where('order_id', $order->id)->where('product_id', $second->id)->firstOrFail();

        $firstRefund = $this->submit($order, 'partial', [
            $firstItem->id => 1,
        ], includeShipping: true);

        $this->assertSame(40000 + 19900, (int) $firstRefund->amount_paise);
        $this->assertSame('partial', $firstRefund->kind);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(4, $first->fresh()->stock);
        $this->assertSame(3, $second->fresh()->stock);

        $secondRefund = $this->submit($order->fresh(), 'partial', [
            $secondItem->id => 1,
        ], includeShipping: true, key: 'idem-partial-second');

        $this->assertSame(60000, (int) $secondRefund->amount_paise);
        $this->assertSame(0, (int) $secondRefund->shipping_amount_paise);
        $this->assertSame(119900, (int) $order->fresh()->refunded_amount_paise);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(4, $first->fresh()->stock);
        $this->assertSame(4, $second->fresh()->stock);
    }

    public function test_same_key_retries_use_the_identical_body_and_a_different_body_is_rejected(): void
    {
        [$order] = $this->paidOrder();
        $bodies = [];
        $keys = [];
        $calls = 0;

        Http::fake(function (Request $request) use (&$bodies, &$keys, &$calls, $order) {
            if (str_contains($request->url(), '/payments/pay_refund') && ! str_contains($request->url(), '/refund')) {
                return Http::response($this->capturedPayment(), 200);
            }

            $calls++;
            $body = json_decode($request->body(), true);
            $bodies[] = $request->body();
            $keys[] = $request->header('X-Refund-Idempotency')[0] ?? null;

            if ($calls === 1) {
                return Http::response(['message' => 'in progress'], 409);
            }

            return Http::response($this->refundBody($body, 'processed', 'rfnd_retry'), 200);
        });

        $refund = $this->submit($order, 'full');
        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $refund->status);
        $this->assertSame(1, (int) $refund->submit_attempts);

        $owner = User::query()->where('admin_role', AdminRole::OWNER)->firstOrFail();
        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.retry', [$order, $refund]), [
                'current_password' => 'password',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Refund completed.');

        $this->assertSame(OrderRefund::STATUS_PROCESSED, $refund->fresh()->status);
        $this->assertSame(2, (int) $refund->fresh()->submit_attempts);
        $this->assertCount(2, $bodies);
        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertSame($keys[0], $keys[1]);
        $this->assertGreaterThanOrEqual(10, strlen((string) $keys[0]));

        DB::table('order_refunds')->where('id', $refund->id)->update([
            'request_body_sha256' => str_repeat('ab', 32),
            'status' => OrderRefund::STATUS_SUBMIT_UNCERTAIN,
        ]);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.retry', [$order, $refund->fresh()]), [
                'current_password' => 'password',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('refund');

        $this->assertCount(2, $bodies);
        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $refund->fresh()->status);
    }

    public function test_timeout_then_webhook_processes_the_refund_without_a_new_key(): void
    {
        [$order, $product] = $this->paidOrder();
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/refund')) {
                throw new ConnectionException('timed out');
            }

            return Http::response($this->capturedPayment(), 200);
        });

        $refund = $this->submit($order, 'full');
        $key = $refund->idempotency_key;
        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $refund->status);
        $this->assertSame(4, $product->fresh()->stock);

        $this->postWebhook($this->refundBody([
            'amount' => 119900,
            'receipt' => $refund->receipt,
            'notes' => [
                'order_number' => $order->order_number,
                'refund_id' => (string) $refund->id,
                'receipt' => $refund->receipt,
            ],
        ], 'processed', 'rfnd_hook'));

        $fresh = $refund->fresh();
        $this->assertSame(OrderRefund::STATUS_PROCESSED, $fresh->status);
        $this->assertSame($key, $fresh->idempotency_key);
        $this->assertSame('rfnd_hook', $fresh->gateway_refund_id);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_webhook_before_the_request_finishes_restores_stock_once(): void
    {
        [$order, $product] = $this->paidOrder();
        Http::fake(function (Request $request) use ($order) {
            if (! str_ends_with($request->url(), '/refund')) {
                return Http::response($this->capturedPayment(), 200);
            }

            $body = json_decode($request->body(), true);
            $entity = $this->refundBody($body, 'processed', 'rfnd_race');
            $refund = OrderRefund::query()->where('receipt', $body['receipt'])->firstOrFail();
            DB::table('order_refunds')->where('id', $refund->id)->update([
                'status' => OrderRefund::STATUS_PROCESSED,
                'gateway_refund_id' => 'rfnd_race',
                'gateway_status' => 'processed',
                'processed_at' => now(),
            ]);
            app(\App\Services\StockRestoration::class)->restore($order->fresh(), $refund->fresh());
            $order->fresh()->forceFill(['status' => 'cancelled', 'refund_status' => 'refunded', 'refunded_amount_paise' => 119900])->save();

            return Http::response($entity, 200);
        });

        $refund = $this->submit($order, 'full');

        $this->assertSame(OrderRefund::STATUS_PROCESSED, $refund->status);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(1, $refund->lines()->where('stock_restoration', 'restored')->count());
    }

    public function test_reconciliation_duplicate_capture_and_cod_orders_are_refused(): void
    {
        [$review] = $this->paidOrder();
        $review->forceFill([
            'status' => 'reconciliation_required',
            'reconciliation_reason' => 'duplicate_capture',
            'reconciliation_meta' => ['extra_payment_ids' => ['pay_extra']],
        ])->save();

        $this->fakeRefund(fn (array $body) => $this->refundBody($body, 'processed', 'rfnd_no'));
        $owner = $this->owner();

        $this->actingAsAdmin($owner)
            ->withSession([
                AdminAccess::SESSION_KEY => true,
                'order_refund_idempotency.'.$review->id => 'idem-review',
            ])
            ->post(route('admin.orders.refunds.store', $review), $this->payload($review, 'full', 'idem-review'))
            ->assertRedirect()
            ->assertSessionHasErrors('refund');

        $this->assertSame(0, OrderRefund::query()->count());
        Http::assertNothingSent();

        [$duplicate] = $this->paidOrder(overrides: [
            'payment_id' => 'pay_refund_dup',
            'razorpay_order_id' => 'order_refund_dup',
        ]);
        $duplicate->forceFill([
            'reconciliation_reason' => 'duplicate_capture',
            'reconciliation_meta' => ['extra_payment_ids' => ['pay_second']],
        ])->save();

        $this->actingAsAdmin($owner)
            ->withSession([
                AdminAccess::SESSION_KEY => true,
                'order_refund_idempotency.'.$duplicate->id => 'idem-dup-key',
            ])
            ->post(route('admin.orders.refunds.store', $duplicate), $this->payload($duplicate, 'full', 'idem-dup-key'))
            ->assertRedirect()
            ->assertSessionHasErrors('refund');

        $cod = $this->orderRecord([
            'payment_method' => 'cod',
            'payment_id' => null,
            'razorpay_order_id' => null,
            'status' => 'paid',
        ]);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.store', $cod), $this->payload($cod, 'full', 'idem-cod-key'))
            ->assertRedirect()
            ->assertSessionHasErrors('idempotency_key');
    }

    public function test_checkout_stays_disabled_while_an_existing_capture_can_be_refunded(): void
    {
        [$order] = $this->paidOrder();
        $customer = User::factory()->create(['is_admin' => false]);
        $order->forceFill(['user_id' => $customer->id])->save();
        $this->fakeRefund(fn (array $body) => $this->refundBody($body, 'pending', 'rfnd_pending'));

        $this->assertFalse(config('checkout.payments_enabled'));
        $this->actingAs($customer)
            ->post(route('api.create-order'), ['store_order_id' => $order->id])
            ->assertStatus(503);

        $refund = $this->submit($order->fresh(), 'full');
        $this->assertSame(OrderRefund::STATUS_PENDING, $refund->status);
        $this->assertFalse((bool) config('checkout.payments_enabled'));
    }

    public function test_customer_pages_hide_gateway_references_and_logs_are_sanitized(): void
    {
        [$order] = $this->paidOrder();
        $customer = User::factory()->create(['is_admin' => false]);
        $order->forceFill(['user_id' => $customer->id])->save();
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/refund')) {
                throw new ConnectionException('timed out');
            }

            return Http::response($this->capturedPayment(), 200);
        });
        $refund = $this->submit($order, 'full');

        Log::listen(function (MessageLogged $message) use ($order): void {
            $encoded = json_encode($message->context);
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString('jane@example.com', $encoded);
            $this->assertStringNotContainsString('9999999999', $encoded);
            $this->assertStringNotContainsString('whsec_test', $encoded);
            $this->assertStringNotContainsString('rzp_test_secret', $encoded);
            $this->assertArrayNotHasKey('signature', $message->context);
            $this->assertStringNotContainsString((string) $order->customer_email, $message->message);
        });

        $this->postWebhook($this->refundBody([
            'amount' => 1,
            'receipt' => $refund->receipt,
            'notes' => [
                'order_number' => $order->order_number,
                'refund_id' => (string) $refund->id,
                'receipt' => $refund->receipt,
                'customer_email' => 'jane@example.com',
            ],
        ], 'processed', 'rfnd_mismatch'), assertOk: false);

        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $refund->fresh()->status);

        $order->forceFill([
            'status' => 'cancelled',
            'refund_status' => 'refunded',
            'refunded_amount_paise' => 119900,
        ])->save();
        $refund->forceFill([
            'status' => OrderRefund::STATUS_PROCESSED,
            'gateway_refund_id' => 'rfnd_secret_ref',
        ])->save();

        $this->actingAs($customer)
            ->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('Your payment was received and fully refunded (₹1199.00).', false)
            ->assertDontSee('rfnd_secret_ref', false)
            ->assertDontSee('pay_refund', false)
            ->assertDontSee('Studio hold', false);
        $this->assertNoUnpaidCancellationCopy($this->actingAs($customer)->followingRedirects()->get(route('checkout.pay', $order)));

        $this->actingAs($customer)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('Your payment was received and fully refunded (₹1199.00).', false)
            ->assertDontSee('rfnd_secret_ref', false);
        $this->assertNoUnpaidCancellationCopy($this->actingAs($customer)->get(route('account')));
    }

    public function test_paid_status_dropdown_cannot_cancel_and_unpaid_cancellation_still_can(): void
    {
        [$paid] = $this->paidOrder();
        $owner = $this->owner();

        $this->actingAsAdmin($owner)
            ->put(route('admin.orders.update', $paid), [
                'status' => 'cancelled',
                'admin_notes' => 'Try the dropdown',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertNull($paid->fresh()->admin_notes);

        $pending = $this->orderRecord([
            'status' => 'pending',
            'payment_id' => null,
            'razorpay_order_id' => null,
            'stock_deducted_at' => null,
        ]);

        $this->actingAsAdmin($owner)
            ->put(route('admin.orders.update', $pending), [
                'status' => 'cancelled',
                'admin_notes' => 'Unpaid cancel',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $pending->fresh()->status);

        $customer = User::factory()->create(['is_admin' => false]);
        $pending->forceFill(['user_id' => $customer->id])->save();

        $this->actingAs($customer)
            ->get(route('checkout.pay', $pending))
            ->assertOk()
            ->assertSee('This payment session was cancelled or expired', false)
            ->assertSee('was not confirmed', false)
            ->assertSee('Your cart is unchanged', false);

        $this->actingAs($customer)
            ->get(route('checkout.success', $pending))
            ->assertOk()
            ->assertSee('This payment session was cancelled or expired', false)
            ->assertSee('Your cart is unchanged', false);
    }

    public function test_recovery_paginates_and_does_not_create_a_refund(): void
    {
        [$order, $product] = $this->paidOrder();
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/refund')) {
                throw new ConnectionException('timed out');
            }

            if (str_ends_with($path, '/refunds')) {
                return null;
            }

            return Http::response($this->capturedPayment(), 200);
        });
        $refund = $this->submit($order, 'full');
        $before = OrderRefund::query()->count();

        Http::fake(function (Request $request) use ($refund, $order) {
            if (! str_contains($request->url(), '/refunds')) {
                throw new \RuntimeException('unexpected '.$request->url());
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $skip = (int) ($query['skip'] ?? 0);
            if ($skip === 0) {
                $items = [];
                for ($i = 0; $i < 100; $i++) {
                    $items[] = [
                        'id' => 'rfnd_other_'.$i,
                        'payment_id' => 'pay_other',
                        'amount' => 100,
                        'currency' => 'INR',
                        'status' => 'processed',
                        'receipt' => 'other'.$i,
                        'notes' => [],
                    ];
                }

                return Http::response(['items' => $items], 200);
            }

            return Http::response(['items' => [[
                'id' => 'rfnd_recovered',
                'payment_id' => 'pay_refund',
                'amount' => 119900,
                'currency' => 'INR',
                'status' => 'processed',
                'receipt' => $refund->receipt,
                'notes' => [
                    'order_number' => $order->order_number,
                    'refund_id' => (string) $refund->id,
                    'receipt' => $refund->receipt,
                ],
            ]]], 200);
        });

        $this->artisan('orders:reconcile-refunds')
            ->assertOk()
            ->expectsOutputToContain('Reconciled 1 refund(s)');

        $this->assertSame($before, OrderRefund::query()->count());
        $this->assertSame(OrderRefund::STATUS_PROCESSED, $refund->fresh()->status);
        $this->assertSame('rfnd_recovered', $refund->fresh()->gateway_refund_id);
        $this->assertSame($refund->idempotency_key, $refund->fresh()->idempotency_key);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_stock_restoration_failure_rolls_back_processed_and_deadlock_retries_locally(): void
    {
        [$order, $product] = $this->paidOrder();
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/refund')) {
                throw new ConnectionException('timed out');
            }

            return Http::response($this->capturedPayment(), 200);
        });
        $refund = $this->submit($order, 'full');
        DB::table('order_refund_lines')->where('order_refund_id', $refund->id)->update(['quantity' => 50]);

        $this->postWebhook($this->refundBody([
            'amount' => 119900,
            'receipt' => $refund->receipt,
            'notes' => [
                'order_number' => $order->order_number,
                'refund_id' => (string) $refund->id,
                'receipt' => $refund->receipt,
            ],
        ], 'processed', 'rfnd_stock_fail'), assertOk: false)->assertStatus(500);

        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $refund->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);

        DB::table('order_refund_lines')->where('order_refund_id', $refund->id)->update(['quantity' => 1]);
        $thrown = false;
        DB::listen(function ($query) use (&$thrown) {
            if ($thrown || ! str_contains(strtolower($query->sql), 'products')) {
                return;
            }

            $thrown = true;
            $previous = new \PDOException('Deadlock found');
            $previous->errorInfo = ['40001', 1213, 'Deadlock found'];

            throw new \Illuminate\Database\QueryException('sqlite', $query->sql, $query->bindings, $previous);
        });

        $this->postWebhook($this->refundBody([
            'amount' => 119900,
            'receipt' => $refund->receipt,
            'notes' => [
                'order_number' => $order->order_number,
                'refund_id' => (string) $refund->id,
                'receipt' => $refund->receipt,
            ],
        ], 'processed', 'rfnd_stock_fail'));

        $this->assertTrue($thrown);
        $this->assertSame(OrderRefund::STATUS_PROCESSED, $refund->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_browser_amount_and_payment_id_are_ignored(): void
    {
        [$order] = $this->paidOrder();
        $seen = [];
        $this->fakeRefund(function (array $body, Request $request) use (&$seen) {
            $seen[] = [$request->url(), $body['amount']];

            return $this->refundBody($body, 'processed', 'rfnd_bound');
        });

        $this->submit($order, 'full', extra: [
            'payment_id' => 'pay_from_browser',
            'amount' => 100,
            'amount_paise' => 100,
        ]);

        $this->assertSame('https://api.razorpay.com/v1/payments/pay_refund/refund', $seen[0][0]);
        $this->assertSame(119900, $seen[0][1]);
        $this->assertSame('pay_refund', $order->fresh()->payment_id);
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => 'invalid',
        ], '{"event":"refund.processed"}')->assertStatus(400);
    }

    public function test_open_sibling_blocks_cancellation_until_the_captured_total_is_processed(): void
    {
        [$order, $first, $second] = $this->paidOrder(twoLines: true);
        $firstItem = OrderItem::query()->where('order_id', $order->id)->where('product_id', $first->id)->firstOrFail();
        $secondItem = OrderItem::query()->where('order_id', $order->id)->where('product_id', $second->id)->firstOrFail();
        $this->fakeRefund(function (array $body) {
            $pending = (int) ($body['amount'] ?? 0) === 40000;

            return $this->refundBody($body, $pending ? 'pending' : 'processed', 'rfnd_'.$body['amount']);
        });

        $pendingRefund = $this->submit($order, 'partial', [$firstItem->id => 1]);
        $processedRefund = $this->submit($order->fresh(), 'partial', [$secondItem->id => 1], true, 'idem-remainder');

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame('pending', $order->refund_status);
        $this->assertSame(40000, (int) $order->refund_pending_amount_paise);
        $this->assertSame(79900, (int) $order->refunded_amount_paise);
        $this->assertSame(3, $first->fresh()->stock);
        $this->assertSame(4, $second->fresh()->stock);
        $this->assertCapturedSurfaces($order, 'Your payment was received. ₹799.00 has been refunded, and a further refund of ₹400.00 is in progress.');

        $this->postWebhook($this->refundEntity($pendingRefund, 'failed'), true, 'refund.failed');

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame('partial', $order->refund_status);
        $this->assertSame(0, (int) $order->refund_pending_amount_paise);
        $this->assertSame(79900, (int) $order->refunded_amount_paise);
        $this->assertSame(3, $first->fresh()->stock);
        $this->assertCapturedSurfaces($order, 'Your payment was received. ₹799.00 has been refunded.');

        $pendingRefund->refresh();
        $pendingRefund->forceFill([
            'status' => OrderRefund::STATUS_PENDING,
            'failed_at' => null,
            'failure_code' => null,
        ])->save();
        $order->forceFill([
            'refund_status' => 'pending',
            'refund_pending_amount_paise' => 40000,
        ])->save();

        $this->postWebhook($this->refundEntity($pendingRefund, 'processed'), true, 'refund.processed');
        $this->postWebhook($this->refundEntity($processedRefund->fresh(), 'processed'));

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('refunded', $order->refund_status);
        $this->assertSame(119900, (int) $order->refunded_amount_paise);
        $this->assertSame(0, (int) $order->refund_pending_amount_paise);
        $this->assertSame(4, $first->fresh()->stock);
        $this->assertSame(4, $second->fresh()->stock);
        $this->assertSame(1, $pendingRefund->lines()->where('stock_restoration', 'restored')->count());
        $this->assertCapturedSurfaces($order, 'Your payment was received and fully refunded (₹1199.00).');
    }

    public function test_reverse_processing_cancels_only_when_the_last_open_refund_completes(): void
    {
        [$order, $first, $second] = $this->paidOrder(twoLines: true);
        $firstItem = OrderItem::query()->where('order_id', $order->id)->where('product_id', $first->id)->firstOrFail();
        $secondItem = OrderItem::query()->where('order_id', $order->id)->where('product_id', $second->id)->firstOrFail();
        $this->fakeRefund(fn (array $body) => $this->refundBody($body, 'pending', 'rfnd_'.$body['amount']));

        $firstRefund = $this->submit($order, 'partial', [$firstItem->id => 1]);
        $secondRefund = $this->submit($order->fresh(), 'partial', [$secondItem->id => 1], true, 'idem-second-pending');

        $this->postWebhook($this->refundEntity($firstRefund, 'processed'), true, 'refund.processed');

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame('pending', $order->refund_status);
        $this->assertSame(4, $first->fresh()->stock);
        $this->assertSame(3, $second->fresh()->stock);

        $this->postWebhook($this->refundEntity($secondRefund, 'processed'), true, 'refund.processed');
        $this->postWebhook($this->refundEntity($secondRefund->fresh(), 'processed'));

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('refunded', $order->refund_status);
        $this->assertSame(119900, (int) $order->refunded_amount_paise);
        $this->assertSame(4, $first->fresh()->stock);
        $this->assertSame(4, $second->fresh()->stock);
        $this->assertSame(1, $secondRefund->lines()->where('stock_restoration', 'restored')->count());
    }

    public function test_uncertain_retries_keep_the_same_key_and_one_post_per_request(): void
    {
        [$order] = $this->paidOrder();
        $bodies = [];
        $keys = [];
        Http::fake(function (Request $request) use (&$bodies, &$keys) {
            if (str_ends_with($request->url(), '/refund')) {
                $bodies[] = $request->body();
                $keys[] = $request->header('X-Refund-Idempotency')[0] ?? null;

                return Http::response(['message' => 'in progress'], 409);
            }

            return Http::response($this->capturedPayment(), 200);
        });

        $refund = $this->submit($order, 'full');
        $owner = $this->owner();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->actingAsAdmin($owner)
                ->post(route('admin.orders.refunds.retry', [$order, $refund]), [
                    'current_password' => 'password',
                ])
                ->assertRedirect()
                ->assertSessionHas('success', 'Outcome not yet confirmed. Retrying uses the same idempotency key and cannot change the amount.');
        }

        $fresh = $refund->fresh();
        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $fresh->status);
        $this->assertSame(5, (int) $fresh->submit_attempts);
        $this->assertCount(5, $bodies);
        $this->assertCount(1, array_unique($bodies));
        $this->assertCount(1, array_unique($keys));
        $this->assertSame($refund->idempotency_key, $fresh->idempotency_key);

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Outcome not yet confirmed. Retrying uses the same idempotency key and cannot change the amount.', false)
            ->assertDontSee('Mark failed', false);

        $posts = count($bodies);
        $this->postWebhook($this->refundEntity($fresh, 'processed'));
        RateLimiter::clear(md5('admin-refundadmin-refund:'.$owner->id));
        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.retry', [$order, $fresh]), [
                'current_password' => 'password',
            ])
            ->assertRedirect();
        $this->assertCount($posts, $bodies);
        $this->assertSame(OrderRefund::STATUS_PROCESSED, $fresh->fresh()->status);
    }

    public function test_processed_and_failed_refunds_cannot_be_submitted_again(): void
    {
        [$order] = $this->paidOrder();
        $posts = 0;
        Http::fake(function (Request $request) use (&$posts) {
            if (str_ends_with($request->url(), '/refund')) {
                $posts++;

                return Http::response(['error' => 'rejected'], 400);
            }

            return Http::response($this->capturedPayment(), 200);
        });

        $failed = $this->submit($order, 'full');
        $this->assertSame(OrderRefund::STATUS_FAILED, $failed->status);
        $owner = $this->owner();
        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.retry', [$order, $failed]), [
                'current_password' => 'password',
            ])
            ->assertRedirect();
        $this->assertSame(1, $posts);
        $this->assertSame(OrderRefund::STATUS_FAILED, $failed->fresh()->status);

        $processed = $failed->fresh();
        $processed->forceFill([
            'status' => OrderRefund::STATUS_PROCESSED,
            'failure_code' => null,
        ])->save();
        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.retry', [$order, $processed]), [
                'current_password' => 'password',
            ])
            ->assertRedirect();
        $this->assertSame(1, $posts);
    }

    public function test_recovery_resolves_an_uncertain_refund_before_another_post(): void
    {
        [$order] = $this->paidOrder();
        $posts = 0;
        Http::fake(function (Request $request) use (&$posts) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/refunds')) {
                return null;
            }

            if (str_ends_with($path, '/refund')) {
                $posts++;
                throw new ConnectionException('timed out');
            }

            return Http::response($this->capturedPayment(), 200);
        });
        $refund = $this->submit($order, 'full');

        Http::fake(function (Request $request) use (&$posts, $refund, $order) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/refund')) {
                $posts++;

                return Http::response(['message' => 'late'], 409);
            }

            if (! str_ends_with($path, '/refunds')) {
                return null;
            }

            return Http::response(['items' => [[
                'id' => 'rfnd_recovered_once',
                'payment_id' => 'pay_refund',
                'amount' => 119900,
                'currency' => 'INR',
                'status' => 'processed',
                'receipt' => $refund->receipt,
                'notes' => [
                    'order_number' => $order->order_number,
                    'refund_id' => (string) $refund->id,
                    'receipt' => $refund->receipt,
                ],
            ]]], 200);
        });

        $this->artisan('orders:reconcile-refunds')->assertOk();
        $before = $posts;
        $this->actingAsAdmin($this->owner())
            ->post(route('admin.orders.refunds.retry', [$order, $refund->fresh()]), [
                'current_password' => 'password',
            ])
            ->assertRedirect();

        $this->assertSame($before, $posts);
        $this->assertSame(OrderRefund::STATUS_PROCESSED, $refund->fresh()->status);
        $this->assertSame($refund->idempotency_key, $refund->fresh()->idempotency_key);
    }

    public function test_recovery_scan_stops_at_the_page_ceiling_and_on_repeated_pages(): void
    {
        [$order, $product] = $this->paidOrder();
        $mode = 'submit';
        $pages = 0;
        $posts = 0;
        $refund = null;
        Http::fake(function (Request $request) use (&$mode, &$pages, &$posts, &$refund, $order) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/refund')) {
                if ($mode === 'submit') {
                    throw new ConnectionException('timed out');
                }

                $posts++;
                throw new \RuntimeException('recovery must not create a refund');
            }

            if (! str_ends_with($path, '/refunds')) {
                return Http::response($this->capturedPayment(), 200);
            }

            $pages++;
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $skip = (int) ($query['skip'] ?? 0);

            if ($mode === 'ceiling' || $mode === 'repeated') {
                $width = 100;
                $items = [];
                for ($i = 0; $i < $width; $i++) {
                    $id = $mode === 'repeated' ? 'rfnd_same_'.$i : 'rfnd_'.$skip.'_'.$i;
                    $receipt = $mode === 'repeated' ? 'same'.$i : 'other'.$skip.'_'.$i;
                    $items[] = $this->pageItem($id, $receipt);
                }

                return Http::response(['items' => $items], 200);
            }

            if ($mode === 'malformed') {
                return Http::response(['items' => ['id' => 'rfnd_bad']], 200);
            }

            if ($mode === 'short') {
                return Http::response(['items' => [
                    $this->pageItem('rfnd_short', 'other-short'),
                ]], 200);
            }

            if ($skip === 0) {
                $items = [];
                for ($i = 0; $i < 100; $i++) {
                    $items[] = $this->pageItem('rfnd_early_'.$i, 'early'.$i);
                }

                return Http::response(['items' => $items], 200);
            }

            return Http::response(['items' => [[
                'id' => 'rfnd_page_two',
                'payment_id' => 'pay_refund',
                'amount' => 119900,
                'currency' => 'INR',
                'status' => 'processed',
                'receipt' => $refund->receipt,
                'notes' => [
                    'order_number' => $order->order_number,
                    'refund_id' => (string) $refund->id,
                    'receipt' => $refund->receipt,
                ],
            ]]], 200);
        });
        $refund = $this->submit($order, 'full');
        $before = OrderRefund::query()->count();

        $mode = 'ceiling';
        $pages = 0;
        $this->artisan('orders:reconcile-refunds')
            ->assertOk()
            ->expectsOutputToContain('Recovery scan limit reached.');
        $this->assertSame(100, $pages);
        $this->assertSame(0, $posts);
        $this->assertSame($before, OrderRefund::query()->count());
        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $refund->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(119900, (int) $order->fresh()->refund_pending_amount_paise);

        $mode = 'repeated';
        $pages = 0;
        $this->artisan('orders:reconcile-refunds')->assertOk();
        $this->assertSame(2, $pages);
        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $refund->fresh()->status);

        $mode = 'malformed';
        $this->artisan('orders:reconcile-refunds')->assertOk();
        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $refund->fresh()->status);
        $this->assertSame($before, OrderRefund::query()->count());
        $this->assertSame(4, $product->fresh()->stock);

        $mode = 'short';
        $this->artisan('orders:reconcile-refunds')->assertOk();
        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $refund->fresh()->status);

        $mode = 'found';
        $this->artisan('orders:reconcile-refunds')->assertOk();
        $this->assertSame(0, $posts);
        $this->assertSame(OrderRefund::STATUS_PROCESSED, $refund->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_recovery_finds_a_receipt_on_the_first_page_without_another_post(): void
    {
        [$order, $product] = $this->paidOrder();
        $posts = 0;
        Http::fake(function (Request $request) use (&$posts) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/refunds')) {
                return null;
            }

            if (str_ends_with($path, '/refund')) {
                $posts++;
                throw new ConnectionException('timed out');
            }

            return Http::response($this->capturedPayment(), 200);
        });
        $refund = $this->submit($order, 'full');

        Http::fake(function (Request $request) use (&$posts, $refund, $order) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/refund')) {
                $posts++;
                throw new \RuntimeException('recovery must not create a refund');
            }

            if (! str_ends_with($path, '/refunds')) {
                return null;
            }

            return Http::response(['items' => [[
                'id' => 'rfnd_page_one',
                'payment_id' => 'pay_refund',
                'amount' => 119900,
                'currency' => 'INR',
                'status' => 'processed',
                'receipt' => $refund->receipt,
                'notes' => [
                    'order_number' => $order->order_number,
                    'refund_id' => (string) $refund->id,
                    'receipt' => $refund->receipt,
                ],
            ]]], 200);
        });

        $before = $posts;
        $this->artisan('orders:reconcile-refunds')->assertOk();
        $this->assertSame($before, $posts);
        $this->assertSame(OrderRefund::STATUS_PROCESSED, $refund->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(1, OrderRefund::query()->count());
    }

    /**
     * @param  array<int, int>  $lines
     * @param  array<string, mixed>  $extra
     */
    private function submit(Order $order, string $kind, array $lines = [], bool $includeShipping = false, ?string $key = null, array $extra = []): OrderRefund
    {
        $owner = $this->owner();
        $this->actingAsAdmin($owner);

        if ($key !== null) {
            session(['order_refund_idempotency.'.$order->id => $key]);
            session()->save();
        }

        $page = $this->get(route('admin.orders.show', $order));
        $page->assertOk();
        preg_match('/name="idempotency_key" value="([^"]+)"/', $page->getContent(), $matches);
        $idempotency = $key ?? ($matches[1] ?? '');
        $this->assertNotSame('', $idempotency);

        $payload = $this->payload($order, $kind, $idempotency, $lines, $includeShipping, $extra);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.store', $order), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return OrderRefund::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
    }

    /**
     * @param  array<int, int>  $lines
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Order $order, string $kind, string $key, array $lines = [], bool $includeShipping = false, array $extra = []): array
    {
        return array_merge([
            'idempotency_key' => $key,
            'kind' => $kind,
            'reason_code' => 'customer_request',
            'internal_note' => 'Studio hold',
            'current_password' => 'password',
            'order_number_confirmation' => $order->order_number,
            'include_shipping' => $includeShipping ? '1' : '0',
            'lines' => $lines,
        ], $extra);
    }

    private function owner(): User
    {
        return User::query()->where('admin_role', AdminRole::OWNER)->first()
            ?? User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
    }

    /**
     * @return array{0: Order, 1: Product, 2?: Product}
     */
    private function paidOrder(bool $twoLines = false, array $overrides = []): array
    {
        $order = $this->orderRecord($overrides);
        $first = $this->addItem($order, $twoLines ? 400 : 1000, $twoLines ? 3 : 4, $twoLines ? 'line-a' : 'line');
        $second = null;
        if ($twoLines) {
            $second = $this->addItem($order, 600, 3, 'line-b');
        }

        return $second ? [$order, $first, $second] : [$order, $first];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function orderRecord(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'VA-'.strtoupper(substr(uniqid(), -8)),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'status' => 'paid',
            'payment_method' => 'razorpay',
            'payment_id' => 'pay_refund',
            'razorpay_order_id' => 'order_refund',
            'stock_deducted_at' => now(),
            'expires_at' => null,
        ], $overrides));
    }

    private function addItem(Order $order, int $price, int $stock, string $slug): Product
    {
        $category = Category::factory()->create(['slug' => $slug.'-'.strtolower(substr(uniqid(), -6))]);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => $stock,
            'price' => $price,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $price,
            'quantity' => 1,
            'total' => $price,
        ]);

        return $product;
    }

    /**
     * @param  callable(array<string, mixed>, Request): array<string, mixed>  $refund
     */
    private function fakeRefund(callable $refund): void
    {
        Http::fake(function (Request $request) use ($refund) {
            if (str_ends_with($request->url(), '/refund')) {
                $body = json_decode($request->body(), true);

                return Http::response($refund(is_array($body) ? $body : [], $request), 200);
            }

            return Http::response($this->capturedPayment(), 200);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function capturedPayment(): array
    {
        return [
            'id' => 'pay_refund',
            'order_id' => 'order_refund',
            'amount' => 119900,
            'currency' => 'INR',
            'status' => 'captured',
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function refundBody(array $body, string $status, string $id): array
    {
        return [
            'id' => $id,
            'payment_id' => 'pay_refund',
            'amount' => (int) ($body['amount'] ?? 0),
            'currency' => 'INR',
            'status' => $status,
            'receipt' => (string) ($body['receipt'] ?? ''),
            'notes' => $body['notes'] ?? [],
        ];
    }

    private function assertNoUnpaidCancellationCopy($response): void
    {
        $response->assertDontSee('payment session was cancelled or expired', false);
        $response->assertDontSee('Order was not confirmed', false);
        $response->assertDontSee('Your cart is unchanged', false);
    }

    private function assertCapturedSurfaces(Order $order, string $expected): void
    {
        $customer = User::factory()->create(['is_admin' => false]);
        $order->forceFill(['user_id' => $customer->id])->save();

        foreach ([
            route('checkout.pay', $order),
            route('checkout.success', $order),
            route('account'),
        ] as $url) {
            $response = $this->actingAs($customer)->followingRedirects()->get($url)->assertOk();
            $response->assertSee($expected, false);
            $this->assertNoUnpaidCancellationCopy($response);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function refundEntity(OrderRefund $refund, string $status): array
    {
        $order = $refund->order()->firstOrFail();

        return [
            'id' => filled($refund->gateway_refund_id) ? (string) $refund->gateway_refund_id : 'rfnd_row_'.$refund->id,
            'payment_id' => (string) $refund->payment_id,
            'amount' => (int) $refund->amount_paise,
            'currency' => 'INR',
            'status' => $status,
            'receipt' => (string) $refund->receipt,
            'notes' => [
                'order_number' => (string) $order->order_number,
                'refund_id' => (string) $refund->id,
                'receipt' => (string) $refund->receipt,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageItem(string $id, string $receipt): array
    {
        return [
            'id' => $id,
            'payment_id' => 'pay_other',
            'amount' => 100,
            'currency' => 'INR',
            'status' => 'processed',
            'receipt' => $receipt,
            'notes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function postWebhook(array $entity, bool $assertOk = true, string $event = 'refund.processed')
    {
        $payload = [
            'event' => $event,
            'payload' => [
                'refund' => [
                    'entity' => $entity,
                ],
            ],
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', (string) $body, 'whsec_test');
        $response = $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ], $body);

        if ($assertOk) {
            $response->assertOk();
        }

        return $response;
    }
}
