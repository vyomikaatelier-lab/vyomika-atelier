<?php

namespace Tests\Feature;

use App\Models\AdminRolePermissionOverride;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderTestDeletion;
use App\Support\AdminMfa;
use App\Support\AdminPermissionResolver;
use App\Support\AdminRole;
use App\Support\PaymentAtomicLock;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderTestDeletionTest extends TestCase
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

    public function test_cache_lock_timeout_leaves_the_order_graph_unchanged(): void
    {
        config(['checkout.razorpay_lock_wait' => 0]);
        [$order, $product, $customer] = $this->inertGraph();
        $owner = $this->owner();
        $before = $this->snapshot();
        $lock = PaymentAtomicLock::forRazorpayOrder((int) $order->id);
        $this->assertTrue($lock->get());

        try {
            $this->actingAsAdmin($owner)
                ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
                ->assertSessionHasErrors('order');

            $message = (string) session('errors')->first('order');
            $this->assertSame('This order is busy; try again.', $message);
            $this->assertStringNotContainsString((string) $order->order_number, $message);
            $this->assertStringNotContainsString((string) $order->customer_email, $message);
            $this->assertStringNotContainsString((string) $customer->email, $message);
            $this->assertSame($before, $this->snapshot());
            $this->assertNotNull($product->fresh());
        } finally {
            $lock->release();
        }
    }

    public function test_guest_is_denied(): void
    {
        $order = $this->inertOrder();

        $this->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertRedirect(route('admin.login'));

        $this->assertNotNull($order->fresh());
        Http::assertNothingSent();
    }

    public function test_inactive_admin_is_denied(): void
    {
        $order = $this->inertOrder();
        $owner = User::factory()->admin()->disabled()->create(['admin_role' => AdminRole::OWNER]);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertRedirect(route('admin.login'));

        $this->assertNotNull($order->fresh());
    }

    public function test_admin_without_completed_mfa_is_denied(): void
    {
        $order = $this->inertOrder();
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);

        $this->actingAs($owner)
            ->withSession([AdminMfa::SESSION_PENDING => $owner->id])
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertRedirect(route('admin.mfa.enroll'));

        $this->assertNotNull($order->fresh());
    }

    public function test_administrator_is_denied_by_default(): void
    {
        $order = $this->inertOrder();
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);

        $this->assertFalse($administrator->hasAdminPermission(AdminRole::ORDERS_DELETE_TEST));
        $this->assertTrue($administrator->hasAdminPermission(AdminRole::ORDERS_MANAGE));

        $this->actingAsAdmin($administrator)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertForbidden();

        $this->assertNotNull($order->fresh());
    }

    public function test_order_manager_is_denied_by_default_and_cannot_be_delegated(): void
    {
        $order = $this->inertOrder();
        $owner = $this->owner();
        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);

        $this->assertFalse($manager->hasAdminPermission(AdminRole::ORDERS_DELETE_TEST));
        $this->assertTrue($manager->hasAdminPermission(AdminRole::ORDERS_MANAGE));

        AdminRolePermissionOverride::query()->create([
            'admin_role' => AdminRole::ORDER_MANAGER,
            'permission' => AdminRole::ORDERS_DELETE_TEST,
            'enabled' => true,
            'updated_by' => $owner->id,
        ]);
        app(AdminPermissionResolver::class)->flush();

        $this->assertFalse($manager->fresh()->hasAdminPermission(AdminRole::ORDERS_DELETE_TEST));

        $this->actingAsAdmin($manager)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertForbidden();

        try {
            app(OrderTestDeletion::class)->delete($manager, (int) $order->id, (string) $order->order_number, 'password');
            $this->fail('Order manager reached deletion.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertNotNull($order->fresh());
    }

    public function test_other_fixed_roles_are_denied_by_default(): void
    {
        foreach (array_keys(AdminRole::labels()) as $role) {
            if ($role === AdminRole::OWNER) {
                continue;
            }

            $user = User::factory()->admin()->create(['admin_role' => $role]);
            $this->assertFalse($user->hasAdminPermission(AdminRole::ORDERS_DELETE_TEST), $role);
        }
    }

    public function test_legacy_null_role_admin_is_denied(): void
    {
        $order = $this->inertOrder();
        $legacy = User::factory()->admin()->create(['admin_role' => null]);

        $this->assertFalse($legacy->hasAdminPermission(AdminRole::ORDERS_DELETE_TEST));
        $this->assertTrue($legacy->hasAdminPermission(AdminRole::ORDERS_MANAGE));

        $this->actingAsAdmin($legacy)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertForbidden();

        $this->assertNotNull($order->fresh());
    }

    public function test_owner_can_delete_only_with_password_and_exact_order_number(): void
    {
        $owner = $this->owner();
        [$order, $product, $customer] = $this->inertGraph();
        $sibling = $this->inertOrder();
        $cancelled = $this->inertOrder(['status' => 'cancelled']);
        $cancelledProduct = $this->attachItem($cancelled);

        $this->assertTrue($owner->hasAdminPermission(AdminRole::ORDERS_DELETE_TEST));

        $page = $this->actingAsAdmin($owner)->get(route('admin.orders.show', $order));
        $page->assertOk();
        $page->assertSee('Delete test order', false);
        $page->assertSee('Only an order without payment, gateway, refund, reconciliation, or stock evidence may be deleted.', false);

        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
        $this->actingAsAdmin($manager)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Delete test order', false);

        $route = app('router')->getRoutes()->getByName('admin.orders.test-deletion.destroy');
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('web', $route->middleware());
        $this->assertContains('admin', $route->middleware());
        $this->assertContains('admin.permission:orders.delete_test', $route->middleware());
        $this->assertNotContains('admin.permission:orders.manage', $route->middleware());

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.test-deletion.destroy', $order))
            ->assertStatus(405);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHas('success', 'Test order deleted.');

        $this->assertNull(Order::query()->find($order->id));
        $this->assertSame(0, OrderItem::query()->where('order_id', $order->id)->count());
        $this->assertNotNull($customer->fresh());
        $this->assertNotNull($product->fresh());
        $this->assertSame(4, (int) $product->fresh()->stock);
        $this->assertNotNull($sibling->fresh());

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $cancelled), $this->payload($cancelled))
            ->assertRedirect(route('admin.orders.index'));

        $this->assertNull(Order::query()->find($cancelled->id));
        $this->assertNotNull($cancelledProduct->fresh());
        Http::assertNothingSent();
    }

    public function test_csrf_is_enforced(): void
    {
        $order = $this->inertOrder();
        $owner = $this->owner();

        $this->app->bind(ValidateCsrfToken::class, function ($app) {
            return new class($app, $app->make('encrypter')) extends ValidateCsrfToken
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertStatus(419);

        $this->assertNotNull($order->fresh());
    }

    public function test_wrong_password_changes_nothing(): void
    {
        [$order, $product, $customer] = $this->inertGraph();
        $owner = $this->owner();
        $before = $this->snapshot();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order, [
                'current_password' => 'wrong-password',
            ]))
            ->assertSessionHasErrors('current_password');

        $this->assertSame($before, $this->snapshot());
        $this->assertSafeFailure('current_password', $order, $customer, $product);
    }

    public function test_wrong_order_number_changes_nothing(): void
    {
        [$order, $product, $customer] = $this->inertGraph();
        $owner = $this->owner();
        $before = $this->snapshot();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order, [
                'order_number_confirmation' => 'VA-WRONG',
            ]))
            ->assertSessionHasErrors('order_number_confirmation');

        $this->assertSame($before, $this->snapshot());
        $this->assertSafeFailure('order_number_confirmation', $order, $customer, $product);
    }

    public function test_payment_id_blocks_deletion_for_current_and_legacy_methods(): void
    {
        foreach (['razorpay', 'cod', 'bank_transfer'] as $method) {
            [$order, $product, $customer] = $this->inertGraph([
                'payment_method' => $method,
                'payment_id' => 'pay_block_'.$method,
            ]);
            $owner = $this->owner();
            $before = $this->snapshot();

            $this->actingAsAdmin($owner)
                ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
                ->assertSessionHasErrors('order');

            $this->assertSame(
                'This order contains payment evidence and cannot be deleted.',
                session('errors')->first('order'),
            );
            $this->assertSame($before, $this->snapshot());
            $this->assertSafeFailure('order', $order, $customer, $product);
        }
    }

    public function test_razorpay_order_id_blocks_deletion_even_without_a_captured_payment(): void
    {
        foreach (['razorpay', 'cod', 'bank_transfer'] as $method) {
            [$order, $product, $customer] = $this->inertGraph([
                'payment_method' => $method,
                'payment_id' => null,
                'razorpay_order_id' => 'order_gateway_'.$method,
            ]);
            $owner = $this->owner();
            $before = $this->snapshot();

            $this->actingAsAdmin($owner)
                ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
                ->assertSessionHasErrors('order');

            $this->assertSame(
                'This order contains gateway evidence and cannot be deleted.',
                session('errors')->first('order'),
            );
            $this->assertSame($before, $this->snapshot());
            $this->assertSafeFailure('order', $order, $customer, $product);
        }
    }

    public function test_stock_deduction_blocks_deletion(): void
    {
        [$order, $product, $customer] = $this->inertGraph([
            'stock_deducted_at' => now(),
        ]);
        $owner = $this->owner();
        $before = $this->snapshot();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertSessionHasErrors('order');

        $this->assertSame(
            'This order contains stock evidence and cannot be deleted.',
            session('errors')->first('order'),
        );
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(4, (int) $product->fresh()->stock);
        $this->assertSafeFailure('order', $order, $customer, $product);
    }

    public function test_reconciliation_status_reason_or_metadata_blocks_deletion(): void
    {
        $cases = [
            ['status' => Order::STATUS_RECONCILIATION_REQUIRED],
            ['reconciliation_reason' => 'duplicate_capture'],
            ['reconciliation_meta' => ['extra_payment_ids' => ['pay_hidden_meta']]],
            ['reconciliation_meta' => ['conflicting_payment_ids' => ['pay_hidden_conflict']]],
        ];

        foreach ($cases as $overrides) {
            [$order, $product, $customer] = $this->inertGraph($overrides);
            $owner = $this->owner();
            $before = $this->snapshot();

            $this->actingAsAdmin($owner)
                ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
                ->assertSessionHasErrors('order');

            $this->assertSame(
                'This order contains reconciliation evidence and cannot be deleted.',
                session('errors')->first('order'),
            );
            $this->assertSame($before, $this->snapshot());
            $this->assertSafeFailure('order', $order, $customer, $product);
        }
    }

    public function test_captured_refunded_or_pending_refund_paise_block_deletion(): void
    {
        foreach (['captured_amount_paise', 'refunded_amount_paise', 'refund_pending_amount_paise'] as $column) {
            [$order, $product, $customer] = $this->inertGraph([
                $column => 1,
            ]);
            $owner = $this->owner();
            $before = $this->snapshot();

            $this->actingAsAdmin($owner)
                ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
                ->assertSessionHasErrors('order');

            $this->assertSame(
                'This order contains payment evidence and cannot be deleted.',
                session('errors')->first('order'),
            );
            $this->assertSame($before, $this->snapshot());
            $this->assertSafeFailure('order', $order, $customer, $product);
        }
    }

    public function test_refund_ledger_evidence_blocks_deletion(): void
    {
        $owner = $this->owner();

        [$refundOrder, $refundProduct, $refundCustomer] = $this->inertGraph();
        $this->insertRefund($refundOrder, $owner, 'idem-refund-row');
        $this->assertDeletionBlockedByRefund($refundOrder, $refundProduct, $refundCustomer);

        [$lineOrder, $lineProduct, $lineCustomer] = $this->inertGraph();
        $lineItemId = (int) OrderItem::query()->where('order_id', $lineOrder->id)->value('id');
        $sibling = $this->inertOrder();
        $refundId = $this->insertRefund($sibling, $owner, 'idem-line-sibling');
        DB::table('order_refund_lines')->insert([
            'order_refund_id' => $refundId,
            'order_item_id' => $lineItemId,
            'quantity' => 1,
            'amount_paise' => 100,
            'stock_restoration' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertDeletionBlockedByRefund($lineOrder, $lineProduct, $lineCustomer);
        $this->assertSame(1, DB::table('order_refund_lines')->where('order_item_id', $lineItemId)->count());

        [$eventOrder, $eventProduct, $eventCustomer] = $this->inertGraph(['refund_status' => 'processed']);
        $eventRefundId = $this->insertRefund($eventOrder, $owner, 'idem-event-row');
        DB::table('order_refund_events')->insert([
            'order_refund_id' => $eventRefundId,
            'event' => 'processed',
            'created_at' => now(),
        ]);
        $this->assertDeletionBlockedByRefund($eventOrder, $eventProduct, $eventCustomer);
        $this->assertSame(1, DB::table('order_refund_events')->where('order_refund_id', $eventRefundId)->count());
        $this->assertNotNull($sibling->fresh());
    }

    public function test_fulfilled_or_shipped_orders_cannot_be_deleted(): void
    {
        foreach (['paid', 'processing', 'shipped', 'delivered'] as $status) {
            [$order, $product, $customer] = $this->inertGraph([
                'status' => $status,
                'payment_id' => null,
                'razorpay_order_id' => null,
                'stock_deducted_at' => null,
            ]);
            $owner = $this->owner();
            $before = $this->snapshot();

            $this->actingAsAdmin($owner)
                ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
                ->assertSessionHasErrors('order');

            $this->assertSame(
                'Only a pending or cancelled order without payment evidence can be deleted.',
                session('errors')->first('order'),
            );
            $this->assertSame($before, $this->snapshot());
            $this->assertSafeFailure('order', $order, $customer, $product);
        }
    }

    public function test_reconciliation_metadata_uses_the_fail_closed_evidence_rule(): void
    {
        $owner = $this->owner();
        $evidence = [
            ['extra_payment_ids' => ['pay_hidden_meta']],
            ['nested' => ['inner' => ['note' => 'held']]],
            ['note' => 'manual'],
            ['count' => 2],
            ['flag' => true],
        ];

        foreach ($evidence as $meta) {
            [$order, $product, $customer] = $this->inertGraph([
                'reconciliation_meta' => $meta,
            ]);
            $before = $this->snapshot();

            $this->actingAsAdmin($owner)
                ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
                ->assertSessionHasErrors('order');

            $this->assertSame(
                'This order contains reconciliation evidence and cannot be deleted.',
                session('errors')->first('order'),
            );
            $this->assertSame($before, $this->snapshot());
            $this->assertSafeFailure('order', $order, $customer, $product);
        }

        [$cleanOrder] = $this->inertGraph([
            'reconciliation_meta' => [],
        ]);
        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $cleanOrder), $this->payload($cleanOrder))
            ->assertRedirect(route('admin.orders.index'));
        $this->assertNull(Order::query()->find($cleanOrder->id));

        [$strictOrder, $strictProduct, $strictCustomer] = $this->inertGraph([
            'reconciliation_meta' => ['count' => 0, 'flag' => false, 'note' => ''],
        ]);
        $this->assertFalse($strictOrder->hasReconciliationEvidence());

        $result = app(OrderTestDeletion::class)->delete(
            $owner,
            (int) $strictOrder->id,
            (string) $strictOrder->order_number,
            'password',
        );

        $this->assertSame(OrderTestDeletion::BLOCKED_STATUS, $result);
        $this->assertNotNull($strictOrder->fresh());
        $this->assertNotNull($strictProduct->fresh());
        $this->assertNotNull($strictCustomer->fresh());
        $this->assertSame(1, OrderItem::query()->where('order_id', $strictOrder->id)->count());
    }

    public function test_empty_string_payment_reference_blocks_deletion(): void
    {
        [$order] = $this->inertGraph([
            'payment_id' => '',
        ]);
        $owner = $this->owner();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertSessionHasErrors('order');

        $this->assertNotNull($order->fresh());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Order, 1: Product, 2: User}
     */
    private function inertGraph(array $overrides = []): array
    {
        $customer = User::factory()->create(['is_admin' => false]);
        $order = $this->inertOrder(array_merge(['user_id' => $customer->id], $overrides));
        $product = $this->attachItem($order);

        return [$order, $product, $customer];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function inertOrder(array $overrides = []): Order
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
            'shipping_cost' => 0,
            'total' => 1000,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'payment_id' => null,
            'razorpay_order_id' => null,
            'stock_deducted_at' => null,
            'captured_amount_paise' => null,
            'refunded_amount_paise' => 0,
            'refund_pending_amount_paise' => 0,
            'refund_status' => 'none',
            'reconciliation_reason' => null,
            'reconciliation_meta' => null,
        ], $overrides));
    }

    private function attachItem(Order $order): Product
    {
        $category = Category::factory()->create(['slug' => 'delete-'.uniqid()]);
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'current_password' => 'password',
            'order_number_confirmation' => $order->order_number,
        ], $overrides);
    }

    private function insertRefund(Order $order, User $actor, string $key): int
    {
        return (int) DB::table('order_refunds')->insertGetId([
            'order_id' => $order->id,
            'payment_id' => 'pay_ledger_'.$key,
            'idempotency_key' => $key,
            'receipt' => 'rcpt_'.$key,
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
    }

    private function assertDeletionBlockedByRefund(Order $order, Product $product, User $customer): void
    {
        $owner = $this->owner();
        $before = $this->snapshot();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertSessionHasErrors('order');

        $this->assertSame(
            'This order contains refund evidence and cannot be deleted.',
            session('errors')->first('order'),
        );
        $this->assertSame($before, $this->snapshot());
        $this->assertSafeFailure('order', $order, $customer, $product);
    }

    private function assertSafeFailure(string $field, Order $order, User $customer, Product $product): void
    {
        $message = (string) session('errors')->first($field);
        $this->assertStringNotContainsString((string) $order->order_number, $message);
        $this->assertStringNotContainsString((string) $order->customer_email, $message);
        $this->assertStringNotContainsString((string) $order->customer_name, $message);
        $this->assertStringNotContainsString((string) $customer->email, $message);
        $this->assertStringNotContainsString((string) ($order->payment_id ?? 'pay_unused_marker'), $message);
        $this->assertStringNotContainsString((string) ($order->razorpay_order_id ?? 'order_unused_marker'), $message);
        $this->assertNotNull($order->fresh());
        $this->assertNotNull($customer->fresh());
        $this->assertNotNull($product->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'orders' => DB::table('orders')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'items' => DB::table('order_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'refunds' => DB::table('order_refunds')->orderBy('id')->pluck('id')->all(),
            'lines' => DB::table('order_refund_lines')->orderBy('id')->pluck('id')->all(),
            'events' => DB::table('order_refund_events')->orderBy('id')->pluck('id')->all(),
            'user_ids' => DB::table('users')->orderBy('id')->pluck('id')->all(),
            'product_stock' => DB::table('products')->orderBy('id')->pluck('stock', 'id')->all(),
        ];
    }
}
