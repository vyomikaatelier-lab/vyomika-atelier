<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminRole;
use App\Support\StorefrontRoutes;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderStatusAuthorizationSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_update_route_keeps_admin_middleware_and_requires_orders_manage(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.orders.update');
        $middleware = app('router')->gatherRouteMiddleware($route);

        $this->assertContains('admin', $middleware);
        $this->assertContains('admin.permission:orders.manage', $middleware);
        $this->assertNotContains('admin.permission:orders.refund', $middleware);
        $this->assertContains(
            ValidateCsrfToken::class,
            app(Kernel::class)->getMiddlewareGroups()['web'],
        );

        $indexRoute = app('router')->getRoutes()->getByName('admin.orders.index');
        $index = $indexRoute->middleware();
        $this->assertContains('web', $index);
        $this->assertContains('admin', $index);
        $this->assertNotContains('admin.permission:orders.manage', $index);
    }

    public function test_admin_without_orders_manage_cannot_change_status_or_notes(): void
    {
        $order = $this->order([
            'status' => 'pending',
            'admin_notes' => 'Keep this note',
        ]);

        foreach ([AdminRole::SALES_MANAGER, AdminRole::VIEWER, AdminRole::CATALOG_MANAGER] as $role) {
            $admin = User::factory()->admin()->create(['admin_role' => $role]);
            $this->assertFalse($admin->hasAdminPermission(AdminRole::ORDERS_MANAGE));

            $this->actingAsAdmin($admin)
                ->put(route('admin.orders.update', $order), [
                    'status' => 'paid',
                    'admin_notes' => 'Should not save',
                ])
                ->assertForbidden();
        }

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame('Keep this note', $fresh->admin_notes);
        $this->assertNull($fresh->payment_id);
    }

    public function test_stale_admin_session_cannot_update_an_order(): void
    {
        $order = $this->order([
            'status' => 'pending',
            'admin_notes' => 'Keep this note',
        ]);
        $manager = User::factory()->admin()->create([
            'admin_role' => AdminRole::ORDER_MANAGER,
            'admin_session_version' => 4,
        ]);

        $this->actingAs($manager)
            ->withSession([
                AdminAccess::SESSION_KEY => true,
                AdminAccess::SESSION_VERSION_KEY => 1,
                AdminAccess::SESSION_USER_KEY => $manager->getKey(),
            ])
            ->put(route('admin.orders.update', $order), [
                'status' => 'cancelled',
                'admin_notes' => 'Should not save',
            ])
            ->assertRedirect(route('admin.login'));

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame('Keep this note', $fresh->admin_notes);
    }

    public function test_owner_and_order_manager_can_update_a_captured_order(): void
    {
        foreach ([AdminRole::OWNER, AdminRole::ORDER_MANAGER] as $role) {
            $order = $this->order([
                'status' => 'paid',
                'payment_id' => 'pay_captured_'.$role,
                'admin_notes' => null,
            ]);
            $admin = User::factory()->admin()->create(['admin_role' => $role]);
            $this->assertTrue($admin->hasAdminPermission(AdminRole::ORDERS_MANAGE));
            $this->assertSame(
                $role === AdminRole::OWNER,
                $admin->hasAdminPermission(AdminRole::ORDERS_REFUND),
            );

            $this->actingAsAdmin($admin)
                ->put(route('admin.orders.update', $order), [
                    'status' => 'processing',
                    'admin_notes' => 'Pack for '.$role,
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $fresh = $order->fresh();
            $this->assertSame('processing', $fresh->status);
            $this->assertSame('Pack for '.$role, $fresh->admin_notes);
            $this->assertSame('pay_captured_'.$role, $fresh->payment_id);
        }
    }

    public function test_null_payment_razorpay_order_cannot_enter_fulfilment_and_notes_stay_unchanged(): void
    {
        foreach (['pending', 'paid', 'processing', 'shipped', 'delivered'] as $current) {
            foreach (['paid', 'processing', 'shipped', 'delivered'] as $status) {
                if ($status === $current) {
                    continue;
                }

                $order = $this->order([
                    'status' => $current,
                    'payment_id' => null,
                    'razorpay_order_id' => null,
                    'stock_deducted_at' => null,
                    'admin_notes' => null,
                    'refund_status' => 'none',
                    'captured_amount_paise' => null,
                    'refunded_amount_paise' => 0,
                    'refund_pending_amount_paise' => 0,
                ]);

                $this->actingAsAdmin($this->manager())
                    ->put(route('admin.orders.update', $order), [
                        'status' => $status,
                        'admin_notes' => 'Do not keep this',
                    ])
                    ->assertRedirect()
                    ->assertSessionHasErrors('status');

                $fresh = $order->fresh();
                $this->assertSame($current, $fresh->status);
                $this->assertNull($fresh->admin_notes);
                $this->assertNull($fresh->payment_id);
                $this->assertNull($fresh->stock_deducted_at);
                $this->assertNull($fresh->captured_amount_paise);
            }
        }
    }

    public function test_null_payment_razorpay_dropdown_omits_fulfilment_statuses(): void
    {
        foreach (['pending', 'paid', 'shipped'] as $current) {
            $order = $this->order([
                'status' => $current,
                'payment_id' => null,
                'payment_method' => 'razorpay',
            ]);

            $page = $this->actingAsAdmin($this->manager())
                ->get(route('admin.orders.show', $order))
                ->assertOk();

            $page->assertSee('value="pending"', false);
            $page->assertSee('value="cancelled"', false);
            foreach (['paid', 'processing', 'shipped', 'delivered'] as $status) {
                $page->assertDontSee('value="'.$status.'"', false);
            }
        }
    }

    public function test_inconsistent_razorpay_row_without_evidence_can_return_to_pending_or_cancelled(): void
    {
        foreach (['paid', 'processing', 'shipped', 'delivered'] as $current) {
            foreach (['pending', 'cancelled'] as $target) {
                $order = $this->order([
                    'status' => $current,
                    'payment_id' => null,
                    'razorpay_order_id' => null,
                    'stock_deducted_at' => null,
                    'reconciliation_reason' => null,
                    'reconciliation_meta' => null,
                    'captured_amount_paise' => null,
                    'refunded_amount_paise' => 0,
                    'refund_pending_amount_paise' => 0,
                    'refund_status' => 'none',
                    'admin_notes' => null,
                ]);

                $this->actingAsAdmin($this->manager())
                    ->put(route('admin.orders.update', $order), [
                        'status' => $target,
                        'admin_notes' => 'Correct inconsistent row',
                    ])
                    ->assertRedirect()
                    ->assertSessionHasNoErrors();

                $fresh = $order->fresh();
                $this->assertSame($target, $fresh->status);
                $this->assertSame('Correct inconsistent row', $fresh->admin_notes);
                $this->assertNull($fresh->payment_id);
                $this->assertNull($fresh->stock_deducted_at);
                $this->assertNull($fresh->razorpay_order_id);
            }
        }
    }

    public function test_stock_deduction_blocks_inconsistent_razorpay_recovery(): void
    {
        $deductedAt = now()->subHour();

        foreach (['pending', 'cancelled'] as $target) {
            $order = $this->order([
                'status' => 'shipped',
                'payment_id' => null,
                'stock_deducted_at' => $deductedAt,
                'admin_notes' => null,
                'refund_status' => 'none',
            ]);

            $this->actingAsAdmin($this->manager())
                ->put(route('admin.orders.update', $order), [
                    'status' => $target,
                    'admin_notes' => 'Do not keep this',
                ])
                ->assertRedirect()
                ->assertSessionHasErrors('status');

            $fresh = $order->fresh();
            $this->assertSame('shipped', $fresh->status);
            $this->assertNull($fresh->admin_notes);
            $this->assertNull($fresh->payment_id);
            $this->assertNotNull($fresh->stock_deducted_at);
        }
    }

    public function test_reconciliation_or_refund_evidence_blocks_inconsistent_razorpay_recovery(): void
    {
        $review = $this->order([
            'status' => 'paid',
            'payment_id' => null,
            'reconciliation_reason' => 'duplicate_capture',
            'admin_notes' => null,
        ]);

        $this->actingAsAdmin($this->manager())
            ->put(route('admin.orders.update', $review), [
                'status' => 'pending',
                'admin_notes' => 'Do not keep this',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame('paid', $review->fresh()->status);
        $this->assertNull($review->fresh()->admin_notes);
        $this->assertSame('duplicate_capture', $review->fresh()->reconciliation_reason);

        $refunded = $this->order([
            'status' => 'delivered',
            'payment_id' => null,
            'refunded_amount_paise' => 1,
            'refund_status' => 'none',
            'admin_notes' => null,
        ]);

        $this->actingAsAdmin($this->manager())
            ->put(route('admin.orders.update', $refunded), [
                'status' => 'cancelled',
                'admin_notes' => 'Do not keep this',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame('delivered', $refunded->fresh()->status);
        $this->assertNull($refunded->fresh()->admin_notes);
        $this->assertSame(1, (int) $refunded->fresh()->refunded_amount_paise);

        $ledger = $this->order([
            'status' => 'processing',
            'payment_id' => null,
            'refund_status' => 'none',
            'admin_notes' => null,
        ]);
        $actor = $this->manager();
        DB::table('order_refunds')->insert([
            'order_id' => $ledger->id,
            'payment_id' => 'pay_ledger_hidden',
            'idempotency_key' => 'idem-status-recovery',
            'receipt' => 'rcpt_status_recovery',
            'amount_paise' => 100,
            'currency' => 'INR',
            'kind' => 'full',
            'reason_code' => 'customer_request',
            'status' => 'processed',
            'includes_shipping' => false,
            'shipping_amount_paise' => 0,
            'actor_user_id' => $actor->id,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin($actor)
            ->put(route('admin.orders.update', $ledger), [
                'status' => 'cancelled',
                'admin_notes' => 'Do not keep this',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $responseMessage = (string) session('errors')->first('status');
        $this->assertStringNotContainsString('pay_ledger_hidden', $responseMessage);
        $this->assertSame('processing', $ledger->fresh()->status);
        $this->assertNull($ledger->fresh()->admin_notes);
        $this->assertSame(1, DB::table('order_refunds')->where('order_id', $ledger->id)->count());
    }

    public function test_unknown_reconciliation_metadata_blocks_legacy_recovery_and_clean_values_do_not(): void
    {
        $evidence = [
            ['extra_payment_ids' => ['pay_hidden_meta']],
            ['conflicting_payment_ids' => ['pay_hidden_conflict']],
            ['nested' => ['inner' => ['note' => 'held']]],
            ['note' => 'manual'],
            ['count' => 2],
            ['flag' => true],
        ];

        foreach ($evidence as $meta) {
            $order = $this->order([
                'status' => 'paid',
                'payment_id' => null,
                'razorpay_order_id' => null,
                'stock_deducted_at' => null,
                'reconciliation_reason' => null,
                'reconciliation_meta' => $meta,
                'refund_status' => 'none',
                'admin_notes' => null,
            ]);

            $this->actingAsAdmin($this->manager())
                ->put(route('admin.orders.update', $order), [
                    'status' => 'pending',
                    'admin_notes' => 'Do not keep this',
                ])
                ->assertRedirect()
                ->assertSessionHasErrors('status');

            $fresh = $order->fresh();
            $this->assertSame('paid', $fresh->status);
            $this->assertNull($fresh->admin_notes);
            $this->assertNull($fresh->payment_id);
            $message = (string) session('errors')->first('status');
            $this->assertStringNotContainsString('pay_hidden_meta', $message);
            $this->assertStringNotContainsString('pay_hidden_conflict', $message);
        }

        $clean = [
            null,
            [],
            ['extra_payment_ids' => [], 'conflicting_payment_ids' => []],
            ['flag' => false, 'count' => 0, 'amount' => 0.0, 'note' => ''],
        ];

        foreach ($clean as $meta) {
            $order = $this->order([
                'status' => 'shipped',
                'payment_id' => null,
                'razorpay_order_id' => null,
                'stock_deducted_at' => null,
                'reconciliation_reason' => null,
                'reconciliation_meta' => $meta,
                'refund_status' => 'none',
                'captured_amount_paise' => null,
                'refunded_amount_paise' => 0,
                'refund_pending_amount_paise' => 0,
                'admin_notes' => null,
            ]);

            $this->actingAsAdmin($this->manager())
                ->put(route('admin.orders.update', $order), [
                    'status' => 'cancelled',
                    'admin_notes' => 'Safe correction',
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $fresh = $order->fresh();
            $this->assertSame('cancelled', $fresh->status);
            $this->assertSame('Safe correction', $fresh->admin_notes);
            $this->assertNull($fresh->payment_id);
            $this->assertNull($fresh->stock_deducted_at);
        }
    }

    public function test_unpaid_pending_razorpay_order_can_still_be_cancelled(): void
    {
        $order = $this->order([
            'status' => 'pending',
            'payment_id' => null,
            'stock_deducted_at' => null,
        ]);

        $this->actingAsAdmin($this->manager())
            ->put(route('admin.orders.update', $order), [
                'status' => 'cancelled',
                'admin_notes' => 'Unpaid cancel',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('Unpaid cancel', $fresh->admin_notes);
        $this->assertNull($fresh->payment_id);
    }

    public function test_captured_razorpay_order_cannot_return_to_pending_or_be_cancelled_directly(): void
    {
        foreach (['pending', 'cancelled'] as $status) {
            $order = $this->order([
                'status' => 'paid',
                'payment_id' => 'pay_keep_'.$status,
                'admin_notes' => null,
            ]);

            $this->actingAsAdmin($this->manager())
                ->put(route('admin.orders.update', $order), [
                    'status' => $status,
                    'admin_notes' => 'Do not keep this',
                ])
                ->assertRedirect()
                ->assertSessionHasErrors('status');

            $fresh = $order->fresh();
            $this->assertSame('paid', $fresh->status);
            $this->assertNull($fresh->admin_notes);
            $this->assertSame('pay_keep_'.$status, $fresh->payment_id);
        }
    }

    public function test_reconciliation_required_order_cannot_be_marked_paid_or_processing(): void
    {
        foreach (['paid', 'processing'] as $status) {
            $order = $this->order([
                'status' => 'reconciliation_required',
                'payment_id' => 'pay_review_'.$status,
                'reconciliation_reason' => 'insufficient_stock',
                'admin_notes' => null,
            ]);

            $this->actingAsAdmin($this->manager())
                ->put(route('admin.orders.update', $order), [
                    'status' => $status,
                    'admin_notes' => 'Do not keep this',
                ])
                ->assertRedirect()
                ->assertSessionHasErrors('status');

            $fresh = $order->fresh();
            $this->assertSame('reconciliation_required', $fresh->status);
            $this->assertNull($fresh->admin_notes);
            $this->assertSame('pay_review_'.$status, $fresh->payment_id);
        }
    }

    public function test_captured_razorpay_fulfilment_transitions_remain_available(): void
    {
        $order = $this->order([
            'status' => 'paid',
            'payment_id' => 'pay_fulfil',
        ]);
        $manager = $this->manager();

        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->actingAsAdmin($manager)
                ->put(route('admin.orders.update', $order), [
                    'status' => $status,
                    'admin_notes' => 'Stage '.$status,
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $fresh = $order->fresh();
            $this->assertSame($status, $fresh->status);
            $this->assertSame('Stage '.$status, $fresh->admin_notes);
            $this->assertSame('pay_fulfil', $fresh->payment_id);
        }
    }

    public function test_legacy_payment_methods_cannot_bypass_captured_status_safeguards(): void
    {
        foreach (['cod', 'bank_transfer'] as $method) {
            $order = $this->order([
                'payment_method' => $method,
                'status' => 'paid',
                'payment_id' => 'pay_legacy_'.$method,
                'razorpay_order_id' => null,
                'admin_notes' => null,
            ]);

            foreach (['pending', 'cancelled'] as $status) {
                $this->actingAsAdmin($this->manager())
                    ->put(route('admin.orders.update', $order), [
                        'status' => $status,
                        'admin_notes' => 'Do not keep this',
                    ])
                    ->assertRedirect()
                    ->assertSessionHasErrors('status');

                $fresh = $order->fresh();
                $this->assertSame('paid', $fresh->status);
                $this->assertNull($fresh->admin_notes);
                $this->assertSame('pay_legacy_'.$method, $fresh->payment_id);
            }
        }
    }

    public function test_inconsistent_razorpay_rows_do_not_claim_payment_was_received(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);

        foreach (['paid', 'processing', 'shipped', 'delivered'] as $status) {
            $order = $this->order([
                'user_id' => $customer->id,
                'status' => $status,
                'payment_id' => null,
                'razorpay_order_id' => 'order_hidden_'.$status,
                'payment_method' => 'razorpay',
            ]);

            foreach ([route('checkout.pay', $order), route('checkout.success', $order)] as $url) {
                $direct = $this->actingAs($customer)->get($url);
                $direct->assertRedirect(StorefrontRoutes::primaryShopUrl());
                $direct->assertSessionHas('error', 'This order is not awaiting payment.');
                $direct->assertDontSee('Order Confirmed', false);
                $direct->assertDontSee('Payment received', false);
                $direct->assertDontSee('order_hidden_'.$status, false);

                $followed = $this->actingAs($customer)->followingRedirects()->get($url);
                $followed->assertOk();
                $followed->assertDontSee('Order Confirmed', false);
                $followed->assertDontSee('Payment received', false);
                $followed->assertDontSee('placed successfully', false);
                $followed->assertDontSee('order_hidden_'.$status, false);
            }

            $account = $this->actingAs($customer)->get(route('account'));
            $account->assertOk();
            $account->assertSee('Payment not confirmed', false);
            $account->assertSee($order->order_number, false);
            $account->assertDontSee(' · Paid · ', false);
            $account->assertDontSee(' · Processing · ', false);
            $account->assertDontSee('Order Confirmed', false);
            $account->assertDontSee('Payment received', false);
            $account->assertDontSee('order_hidden_'.$status, false);
        }
    }

    public function test_reconciliation_and_refund_wording_still_win(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);
        $review = $this->order([
            'user_id' => $customer->id,
            'status' => 'reconciliation_required',
            'payment_id' => 'pay_review_wording',
            'razorpay_order_id' => 'order_review_wording',
            'reconciliation_reason' => 'insufficient_stock',
        ]);

        foreach ([route('checkout.pay', $review), route('checkout.success', $review), route('account')] as $url) {
            $page = $this->actingAs($customer)->get($url)->assertOk();
            $page->assertSee(Order::REVIEW_HEADING, false);
            $page->assertDontSee('Order Confirmed', false);
            $page->assertDontSee('pay_review_wording', false);
            $page->assertDontSee('order_review_wording', false);
        }

        $refunded = $this->order([
            'user_id' => $customer->id,
            'status' => 'cancelled',
            'payment_id' => 'pay_refunded_wording',
            'razorpay_order_id' => 'order_refunded_wording',
            'refund_status' => 'refunded',
            'refunded_amount_paise' => 119900,
            'expires_at' => null,
        ]);

        $page = $this->actingAs($customer)->get(route('checkout.success', $refunded))->assertOk();
        $page->assertSee('Your payment was received and fully refunded (₹1199.00).', false);
        $page->assertSee('Refund completed', false);
        $page->assertDontSee('Order Confirmed', false);
        $page->assertDontSee('pay_refunded_wording', false);
        $page->assertDontSee('order_refunded_wording', false);
        $page->assertDontSee('payment session was cancelled or expired', false);

        $this->actingAs($customer)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('Your payment was received and fully refunded (₹1199.00).', false)
            ->assertDontSee('pay_refunded_wording', false);
    }

    public function test_checkout_payments_default_to_disabled(): void
    {
        $source = (string) file_get_contents(config_path('checkout.php'));

        $this->assertMatchesRegularExpression(
            "/'payments_enabled'\\s*=>\\s*filter_var\\(env\\('CHECKOUT_PAYMENTS_ENABLED',\\s*false\\),\\s*FILTER_VALIDATE_BOOLEAN\\)/",
            $source,
        );
    }

    public function test_refund_reconciliation_is_not_scheduled(): void
    {
        $events = app(Schedule::class)->events();
        $commands = array_map(
            fn ($event) => (string) $event->command,
            $events,
        );

        $joined = implode("\n", $commands);
        $this->assertStringContainsString('orders:expire-pending', $joined);
        $this->assertStringNotContainsString('orders:reconcile-refunds', $joined);

        $expire = collect($events)->first(
            fn ($event) => str_contains((string) $event->command, 'orders:expire-pending')
        );
        $this->assertNotNull($expire);
        $this->assertSame('*/15 * * * *', $expire->expression);
    }

    private function manager(): User
    {
        return User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function order(array $overrides = []): Order
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
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'payment_id' => null,
            'razorpay_order_id' => 'order_'.strtolower(substr(uniqid(), -8)),
            'expires_at' => now()->addDay(),
        ], $overrides));
    }
}
