<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReconciliationEvidencePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'cache.stores.database.connection' => 'sqlite',
            'cache.stores.database.lock_connection' => 'sqlite',
        ]);
    }

    public function test_unknown_metadata_forces_review_and_blocks_refund_downgrade_and_deletion(): void
    {
        $secret = 'nested-review-secret';
        $meta = ['audit' => ['note' => $secret, 'inner' => ['kept' => true]]];
        $customer = User::factory()->create(['is_admin' => false]);
        $order = $this->order($customer, [
            'status' => 'paid',
            'payment_id' => 'pay_review_policy',
            'razorpay_order_id' => 'order_review_policy',
            'refund_status' => 'refunded',
            'refunded_amount_paise' => 119900,
            'captured_amount_paise' => 119900,
            'reconciliation_meta' => $meta,
        ]);

        $this->assertTrue($order->needsPaymentReview());
        $this->assertTrue($order->hasReconciliationEvidence());
        $this->assertFalse($order->canOfferRefund());

        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);

        $this->actingAs($customer)
            ->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('Payment received — order under review', false)
            ->assertDontSee('Order Confirmed', false)
            ->assertDontSee('fully refunded', false)
            ->assertDontSee($secret, false);

        $this->actingAs($customer)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('Payment received — order under review', false)
            ->assertDontSee('fully refunded', false)
            ->assertDontSee($secret, false);

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Payment received — order under review', false)
            ->assertDontSee($secret, false);

        $this->actingAsAdmin($owner)
            ->put(route('admin.orders.update', $order), [
                'status' => 'pending',
                'admin_notes' => 'Do not keep this',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertNull($fresh->admin_notes);
        $this->assertSame($meta, $fresh->reconciliation_meta);
        $this->assertStringNotContainsString($secret, (string) session('errors')->first('status'));

        $this->actingAsAdmin($owner)
            ->withSession([
                AdminAccess::SESSION_KEY => true,
                'order_refund_idempotency.'.$order->id => 'idem-review-meta',
            ])
            ->post(route('admin.orders.refunds.store', $order), [
                'idempotency_key' => 'idem-review-meta',
                'kind' => 'full',
                'reason_code' => 'customer_request',
                'current_password' => 'password',
                'order_number_confirmation' => $order->order_number,
                'include_shipping' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('refund');

        $this->assertSame(0, OrderRefund::query()->count());
        $this->assertSame(
            'This order is awaiting payment reconciliation and cannot be refunded here.',
            session('errors')->first('refund'),
        );
        $this->assertStringNotContainsString($secret, (string) session('errors')->first('refund'));
        Http::assertNothingSent();
        $this->assertSame($meta, $order->fresh()->reconciliation_meta);

        $inert = $this->order($customer, [
            'order_number' => 'VA-INERTMETA',
            'status' => 'pending',
            'payment_id' => null,
            'razorpay_order_id' => null,
            'stock_deducted_at' => null,
            'refund_status' => 'none',
            'refunded_amount_paise' => 0,
            'captured_amount_paise' => null,
            'reconciliation_meta' => $meta,
        ]);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $inert), [
                'current_password' => 'password',
                'order_number_confirmation' => $inert->order_number,
            ])
            ->assertSessionHasErrors('order');

        $this->assertSame(
            'This order contains reconciliation evidence and cannot be deleted.',
            session('errors')->first('order'),
        );
        $this->assertStringNotContainsString($secret, (string) session('errors')->first('order'));
        $this->assertNotNull($inert->fresh());
        $this->assertSame($meta, $inert->fresh()->reconciliation_meta);
        $this->assertSame(1, OrderItem::query()->where('order_id', $inert->id)->count());
    }

    public function test_reserved_payment_collections_are_evidence_when_elements_look_clean(): void
    {
        $collections = [
            ['extra_payment_ids' => [null, false, 0, '']],
            ['conflicting_payment_ids' => [false]],
        ];

        foreach ($collections as $meta) {
            $order = new Order([
                'status' => 'paid',
                'reconciliation_reason' => null,
                'reconciliation_meta' => $meta,
            ]);

            $this->assertTrue($order->needsPaymentReview());
            $this->assertTrue($order->hasReconciliationEvidence());
        }
    }

    public function test_empty_metadata_does_not_force_payment_review(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);

        foreach ([null, [], '', new \stdClass] as $meta) {
            $order = new Order([
                'status' => 'paid',
                'payment_method' => 'razorpay',
                'payment_id' => 'pay_clean',
                'reconciliation_reason' => null,
                'reconciliation_meta' => $meta,
                'refund_status' => 'none',
            ]);

            $this->assertFalse($order->needsPaymentReview());
            $this->assertFalse($order->hasReconciliationEvidence());
        }

        $stored = $this->order($customer, [
            'reconciliation_meta' => [],
            'reconciliation_reason' => null,
            'status' => 'paid',
            'payment_id' => 'pay_clean_stored',
        ]);

        $this->assertFalse($stored->fresh()->needsPaymentReview());

        $this->actingAs($customer)
            ->get(route('checkout.success', $stored))
            ->assertOk()
            ->assertSee('Order Confirmed', false)
            ->assertDontSee('order under review', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function order(User $customer, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $customer->id,
            'order_number' => 'VA-REVIEW1',
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
            'payment_id' => null,
            'razorpay_order_id' => null,
            'stock_deducted_at' => null,
            'refund_status' => 'none',
            'refunded_amount_paise' => 0,
            'refund_pending_amount_paise' => 0,
            'captured_amount_paise' => null,
            'reconciliation_reason' => null,
            'reconciliation_meta' => null,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Review sample',
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
        ]);

        return $order->fresh();
    }
}
