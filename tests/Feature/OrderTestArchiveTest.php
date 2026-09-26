<?php

namespace Tests\Feature;

use App\Models\AdminRolePermissionOverride;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderTestArchive;
use App\Services\PendingOrderExpiry;
use App\Support\AdminAccess;
use App\Support\AdminMfa;
use App\Support\AdminPermissionResolver;
use App\Support\AdminRole;
use App\Support\PaymentAtomicLock;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderTestArchiveTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{message: string, context: array<string, mixed>}> */
    private array $capturedLogs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'cache.stores.database.connection' => 'sqlite',
            'cache.stores.database.lock_connection' => 'sqlite',
        ]);
    }

    public function test_guest_is_denied(): void
    {
        $order = $this->inertOrder();

        $this->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.login'));

        $this->assertNull($order->fresh()->admin_archived_at);
        Http::assertNothingSent();
    }

    public function test_inactive_admin_is_denied(): void
    {
        $order = $this->inertOrder();
        $owner = User::factory()->admin()->disabled()->create(['admin_role' => AdminRole::OWNER]);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.login'));

        $this->assertNull($order->fresh()->admin_archived_at);
    }

    public function test_admin_without_completed_mfa_is_denied(): void
    {
        $order = $this->inertOrder();
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);

        $this->actingAs($owner)
            ->withSession([AdminMfa::SESSION_PENDING => $owner->id])
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.mfa.enroll'));

        $this->assertNull($order->fresh()->admin_archived_at);
    }

    public function test_stale_admin_session_is_denied(): void
    {
        $order = $this->inertOrder();
        $owner = User::factory()->admin()->create([
            'admin_role' => AdminRole::OWNER,
            'admin_session_version' => 4,
        ]);

        $this->actingAs($owner)
            ->withSession([
                AdminAccess::SESSION_KEY => true,
                AdminAccess::SESSION_VERSION_KEY => 1,
                AdminAccess::SESSION_USER_KEY => $owner->getKey(),
            ])
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.login'));

        $this->assertNull($order->fresh()->admin_archived_at);
    }

    public function test_administrator_is_denied_by_default(): void
    {
        $order = $this->inertOrder();
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);

        $this->assertFalse($administrator->hasAdminPermission(AdminRole::ORDERS_ARCHIVE_TEST));
        $this->assertTrue($administrator->hasAdminPermission(AdminRole::ORDERS_MANAGE));
        $this->assertFalse($administrator->hasAdminPermission(AdminRole::ORDERS_DELETE_TEST));
        $this->assertFalse($administrator->hasAdminPermission(AdminRole::ORDERS_REFUND));

        $this->actingAsAdmin($administrator)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertForbidden();

        $this->assertNull($order->fresh()->admin_archived_at);
    }

    public function test_order_manager_is_denied_by_default_and_cannot_be_delegated(): void
    {
        $order = $this->inertOrder();
        $owner = $this->owner();
        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);

        $this->assertFalse($manager->hasAdminPermission(AdminRole::ORDERS_ARCHIVE_TEST));
        $this->assertTrue($manager->hasAdminPermission(AdminRole::ORDERS_MANAGE));

        AdminRolePermissionOverride::query()->create([
            'admin_role' => AdminRole::ORDER_MANAGER,
            'permission' => AdminRole::ORDERS_ARCHIVE_TEST,
            'enabled' => true,
            'updated_by' => $owner->id,
        ]);
        app(AdminPermissionResolver::class)->flush();

        $this->assertFalse($manager->fresh()->hasAdminPermission(AdminRole::ORDERS_ARCHIVE_TEST));
        $this->assertTrue(app(AdminPermissionResolver::class)->isForcedOff(
            AdminRole::ORDER_MANAGER,
            AdminRole::ORDERS_ARCHIVE_TEST,
        ));

        $this->actingAsAdmin($manager)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertForbidden();

        try {
            app(OrderTestArchive::class)->archive($manager, (int) $order->id, (string) $order->order_number, 'password');
            $this->fail('Order manager reached archiving.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertNull($order->fresh()->admin_archived_at);
    }

    public function test_other_fixed_roles_are_denied_by_default(): void
    {
        foreach (array_keys(AdminRole::labels()) as $role) {
            if ($role === AdminRole::OWNER) {
                continue;
            }

            $user = User::factory()->admin()->create(['admin_role' => $role]);
            $this->assertFalse($user->hasAdminPermission(AdminRole::ORDERS_ARCHIVE_TEST), $role);
        }
    }

    public function test_legacy_null_role_admin_is_denied(): void
    {
        $order = $this->inertOrder();
        $legacy = User::factory()->admin()->create(['admin_role' => null]);

        $this->assertFalse($legacy->hasAdminPermission(AdminRole::ORDERS_ARCHIVE_TEST));
        $this->assertTrue($legacy->hasAdminPermission(AdminRole::ORDERS_MANAGE));

        $this->actingAsAdmin($legacy)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertForbidden();

        $this->actingAsAdmin($legacy)
            ->get(route('admin.orders.index', ['archived' => 1]))
            ->assertForbidden();

        $this->assertNull($order->fresh()->admin_archived_at);
    }

    public function test_owner_requires_current_password_and_exact_order_number(): void
    {
        [$order, $product, $customer] = $this->inertGraph();
        $owner = $this->owner();
        $before = $this->snapshot();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order, [
                'current_password' => 'wrong-password',
            ]))
            ->assertSessionHasErrors('current_password');

        $this->assertSame('The password is incorrect.', session('errors')->first('current_password'));
        $this->assertSame($before, $this->snapshot());

        $this->assertSame(
            OrderTestArchive::BLOCKED_PASSWORD,
            app(OrderTestArchive::class)->archive($owner, (int) $order->id, (string) $order->order_number, 'wrong-password'),
        );
        $this->assertSame($before, $this->snapshot());

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order, [
                'order_number_confirmation' => 'VA-WRONG',
            ]))
            ->assertSessionHasErrors('order_number_confirmation');

        $message = (string) session('errors')->first('order_number_confirmation');
        $this->assertSame('Enter the order number exactly to confirm archiving.', $message);
        $this->assertStringNotContainsString((string) $order->order_number, $message);
        $this->assertStringNotContainsString((string) $order->customer_email, $message);
        $this->assertSame($before, $this->snapshot());
        $this->assertNotNull($product->fresh());
        $this->assertNotNull($customer->fresh());
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
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertStatus(419);

        $this->assertNull($order->fresh()->admin_archived_at);
    }

    public function test_archive_routes_are_post_only_and_use_archive_permission(): void
    {
        $order = $this->inertOrder();
        $owner = $this->owner();

        foreach (['admin.orders.test-archive.store', 'admin.orders.test-archive.destroy'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame(['POST'], $route->methods());
            $this->assertContains('web', $route->middleware());
            $this->assertContains('admin', $route->middleware());
            $this->assertContains('admin.permission:orders.archive_test', $route->middleware());
            $this->assertNotContains('admin.permission:orders.manage', $route->middleware());
            $this->assertNotContains('admin.permission:orders.refund', $route->middleware());
            $this->assertNotContains('admin.permission:orders.delete_test', $route->middleware());
        }

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.test-archive.store', $order))
            ->assertStatus(405);

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.test-archive.destroy', $order))
            ->assertStatus(405);
    }

    public function test_eligible_pending_and_cancelled_orders_archive_only_metadata(): void
    {
        $owner = $this->owner();
        [$pending, $pendingProduct, $pendingCustomer] = $this->inertGraph();
        [$cancelled, $cancelledProduct, $cancelledCustomer] = $this->inertGraph(['status' => 'cancelled']);
        $sibling = $this->inertOrder();

        $page = $this->actingAsAdmin($owner)->get(route('admin.orders.show', $pending));
        $page->assertOk();
        $page->assertSee('Archive test order', false);
        $page->assertSee('does not make the order safe to delete', false);
        $page->assertDontSee('order_unused_marker', false);

        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
        $this->actingAsAdmin($manager)
            ->get(route('admin.orders.show', $pending))
            ->assertOk()
            ->assertDontSee('Archive test order', false);

        $this->captureLogs();
        $before = $this->row($pending);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $pending), $this->payload($pending))
            ->assertRedirect(route('admin.orders.show', $pending))
            ->assertSessionHas('success', 'Test order archived.');

        $fresh = $pending->fresh();
        $this->assertNotNull($fresh->admin_archived_at);
        $this->assertSame($owner->id, $fresh->admin_archived_by_user_id);
        $this->assertSame(OrderTestArchive::REASON_ARCHIVE, $fresh->admin_archive_reason);
        $this->assertSame('pending', $fresh->status);
        $this->assertMetadataOnly($before, $this->row($pending));
        $this->assertSame(1, OrderItem::query()->where('order_id', $pending->id)->count());
        $this->assertNotNull($pendingCustomer->fresh());
        $this->assertNotNull($pendingProduct->fresh());
        $this->assertSame(4, (int) $pendingProduct->fresh()->stock);
        $this->assertNull($sibling->fresh()->admin_archived_at);
        $this->assertAuditLog($this->capturedLogs, (int) $pending->id, (int) $owner->id, 'archive', OrderTestArchive::REASON_ARCHIVE);
        $this->assertLogsHideSecrets($this->capturedLogs, $pending, $pendingCustomer, null, null);

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.show', $fresh))
            ->assertOk()
            ->assertSee('Archived', false)
            ->assertSee('Unarchive', false)
            ->assertDontSee('Delete test order', false)
            ->assertDontSee('>Archive test order<', false);

        $cancelledBefore = $this->row($cancelled);
        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $cancelled), $this->payload($cancelled))
            ->assertRedirect(route('admin.orders.show', $cancelled));

        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertNotNull($cancelled->fresh()->admin_archived_at);
        $this->assertMetadataOnly($cancelledBefore, $this->row($cancelled));
        $this->assertNotNull($cancelledCustomer->fresh());
        $this->assertNotNull($cancelledProduct->fresh());
        $this->assertSame(4, (int) $cancelledProduct->fresh()->stock);
        Http::assertNothingSent();
    }

    public function test_cancelled_order_with_only_gateway_order_id_archives_and_preserves_it(): void
    {
        $owner = $this->owner();
        [$order, $product, $customer] = $this->inertGraph([
            'status' => 'cancelled',
            'razorpay_order_id' => 'order_archive_keep',
        ]);
        $before = $this->row($order);
        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('does not make the order safe to delete', false)
            ->assertDontSee('order_archive_keep', false);
        $this->captureLogs();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('success', 'Test order archived.');

        $fresh = $order->fresh();
        $this->assertSame('order_archive_keep', $fresh->razorpay_order_id);
        $this->assertNull($fresh->payment_id);
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNotNull($fresh->admin_archived_at);
        $this->assertMetadataOnly($before, $this->row($order));
        $this->assertSame(4, (int) $product->fresh()->stock);
        $this->assertNotNull($customer->fresh());

        $show = $this->actingAsAdmin($owner)->get(route('admin.orders.show', $fresh));
        $show->assertOk();
        $show->assertDontSee('order_archive_keep', false);
        $show->assertDontSee('Archive test order', false);
        $show->assertSee('Archiving did not delete the order', false);

        $index = $this->actingAsAdmin($owner)->get(route('admin.orders.index', ['archived' => 1]));
        $index->assertOk();
        $index->assertDontSee('order_archive_keep', false);
        $index->assertDontSee((string) $order->customer_email, false);
        $index->assertDontSee((string) $order->customer_phone, false);
        $index->assertDontSee((string) $order->shipping_address, false);
        $this->assertLogsHideSecrets($this->capturedLogs, $order, $customer, null, 'order_archive_keep');
        Http::assertNothingSent();
    }

    public function test_payment_stock_reconciliation_amounts_and_refund_state_refuse(): void
    {
        $owner = $this->owner();

        [$paidId] = $this->inertGraph(['payment_id' => 'pay_archive_block']);
        $this->assertRefusal($owner, $paidId, 'This order contains payment evidence and cannot be archived.', 'pay_archive_block', null);

        foreach (['paid', 'processing', 'shipped', 'delivered'] as $status) {
            [$order] = $this->inertGraph(['status' => $status]);
            $this->assertRefusal($owner, $order, 'Only a pending or cancelled order without captured payment evidence can be archived.');
        }

        [$stock] = $this->inertGraph(['stock_deducted_at' => now()]);
        $this->assertRefusal($owner, $stock, 'This order contains stock evidence and cannot be archived.');

        [$review] = $this->inertGraph([
            'reconciliation_reason' => 'captured_after_cancel',
            'reconciliation_meta' => ['note' => 'keep'],
        ]);
        $this->assertRefusal($owner, $review, 'This order contains reconciliation evidence and cannot be archived.');

        foreach (['captured_amount_paise', 'refunded_amount_paise', 'refund_pending_amount_paise'] as $column) {
            [$order] = $this->inertGraph([$column => 1]);
            $this->assertRefusal($owner, $order, 'This order contains payment evidence and cannot be archived.');
        }

        [$refundStatus] = $this->inertGraph(['refund_status' => 'pending']);
        $this->assertRefusal($owner, $refundStatus, 'This order contains refund evidence and cannot be archived.');

        [$refundRow, $refundProduct, $refundCustomer] = $this->inertGraph();
        $this->insertRefund($refundRow, $owner, 'idem-archive-refund');
        $this->assertRefusal($owner, $refundRow, 'This order contains refund evidence and cannot be archived.');
        $this->assertSame(1, DB::table('order_refunds')->where('order_id', $refundRow->id)->count());
        $this->assertNotNull($refundProduct->fresh());
        $this->assertNotNull($refundCustomer->fresh());

        [$lineOrder] = $this->inertGraph();
        $lineItemId = (int) OrderItem::query()->where('order_id', $lineOrder->id)->value('id');
        $sibling = $this->inertOrder();
        $refundId = $this->insertRefund($sibling, $owner, 'idem-archive-line');
        DB::table('order_refund_lines')->insert([
            'order_refund_id' => $refundId,
            'order_item_id' => $lineItemId,
            'quantity' => 1,
            'amount_paise' => 100,
            'stock_restoration' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertRefusal($owner, $lineOrder, 'This order contains refund evidence and cannot be archived.');
        $this->assertSame(1, DB::table('order_refund_lines')->where('order_item_id', $lineItemId)->count());

        [$eventOrder] = $this->inertGraph();
        $eventRefundId = $this->insertRefund($eventOrder, $owner, 'idem-archive-event');
        DB::table('order_refund_events')->insert([
            'order_refund_id' => $eventRefundId,
            'event' => 'processed',
            'created_at' => now(),
        ]);
        $this->assertRefusal($owner, $eventOrder, 'This order contains refund evidence and cannot be archived.');
        $this->assertSame(1, DB::table('order_refund_events')->where('order_refund_id', $eventRefundId)->count());
        Http::assertNothingSent();
    }

    public function test_already_archived_order_refuses_another_archive_and_hides_deletion(): void
    {
        $owner = $this->owner();
        [$order, $product, $customer] = $this->inertGraph(['razorpay_order_id' => 'order_keep_archived']);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.orders.show', $order));

        $archivedAt = $order->fresh()->admin_archived_at;
        $before = $this->snapshot();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertSessionHasErrors('order');

        $this->assertSame('This order is already archived.', session('errors')->first('order'));
        $this->assertSame($before, $this->snapshot());
        $this->assertTrue($archivedAt->eq($order->fresh()->admin_archived_at));

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $order), $this->payload($order))
            ->assertSessionHasErrors('order');

        $this->assertSame('This order is archived and cannot be deleted.', session('errors')->first('order'));
        $this->assertNotNull($order->fresh());
        $this->assertSame('order_keep_archived', $order->fresh()->razorpay_order_id);
        $this->assertSame(1, OrderItem::query()->where('order_id', $order->id)->count());
        $this->assertNotNull($product->fresh());
        $this->assertNotNull($customer->fresh());
        $this->assertSame(4, (int) $product->fresh()->stock);
    }

    public function test_default_index_hides_archived_orders_and_owner_filter_shows_them(): void
    {
        $owner = $this->owner();
        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
        $visible = $this->inertOrder(['order_number' => 'VA-VISIBLE']);
        $archived = $this->inertOrder(['order_number' => 'VA-ARCHIVED', 'customer_email' => 'hidden@example.com']);
        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $archived), $this->payload($archived))
            ->assertRedirect(route('admin.orders.show', $archived));

        foreach (range(1, 15) as $index) {
            $this->inertOrder(['order_number' => 'VA-PAGE'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);
        }

        $default = $this->actingAsAdmin($owner)->get(route('admin.orders.index'));
        $default->assertOk();
        $default->assertSee('Archived test orders', false);
        $default->assertDontSee('VA-ARCHIVED', false);
        $default->assertDontSee('hidden@example.com', false);
        $default->assertSee('page=2', false);

        $pageTwo = $this->actingAsAdmin($owner)->get(route('admin.orders.index', ['page' => 2]));
        $pageTwo->assertOk();
        $pageTwo->assertDontSee('VA-ARCHIVED', false);
        $this->assertStringContainsString('VA-VISIBLE', $default->getContent().$pageTwo->getContent());

        $filtered = $this->actingAsAdmin($owner)->get(route('admin.orders.index', ['archived' => 1]));
        $filtered->assertOk();
        $filtered->assertSee('Archived test orders', false);
        $filtered->assertSee('Archived', false);
        $filtered->assertSee('VA-ARCHIVED', false);
        $filtered->assertSee('View', false);
        $filtered->assertSee('Unarchive', false);
        $filtered->assertDontSee('VA-VISIBLE', false);
        $filtered->assertDontSee('Delete test order', false);
        $filtered->assertDontSee('hidden@example.com', false);

        $this->actingAsAdmin($manager)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertDontSee('Archived test orders', false)
            ->assertDontSee('VA-ARCHIVED', false);

        $this->actingAsAdmin($manager)
            ->get(route('admin.orders.index', ['archived' => 1]))
            ->assertForbidden();

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.show', $archived))
            ->assertOk()
            ->assertSee('VA-ARCHIVED', false)
            ->assertSee('Archived', false);
    }

    public function test_customer_and_financial_lookups_still_find_archived_orders(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);
        $owner = $this->owner();
        $order = $this->inertOrder([
            'user_id' => $customer->id,
            'order_number' => 'VA-LOOKUP',
            'status' => 'cancelled',
            'razorpay_order_id' => 'order_lookup_keep',
            'expires_at' => now()->subHour(),
        ]);
        $this->attachItem($order);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertNotNull(Order::query()->find($order->id));
        $this->assertSame($order->id, Order::query()->where('razorpay_order_id', 'order_lookup_keep')->value('id'));
        $method = new \ReflectionMethod(Order::class, 'getGlobalScopes');
        $method->setAccessible(true);
        $this->assertSame([], $method->invoke(new Order));
        $this->assertStringNotContainsString('addGlobalScope', (string) file_get_contents(app_path('Models/Order.php')));
        $this->assertNotNull($order->fresh()->admin_archived_at);

        $this->actingAs($customer)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('VA-LOOKUP', false)
            ->assertDontSee('order_lookup_keep', false);

        $this->actingAs($customer)
            ->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('VA-LOOKUP', false)
            ->assertDontSee('order_lookup_keep', false);

        $candidateIds = Order::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id');
        $pending = $this->inertOrder([
            'user_id' => $customer->id,
            'order_number' => 'VA-EXPIRE',
            'status' => 'pending',
            'razorpay_order_id' => 'order_expire_keep',
            'expires_at' => now()->subMinute(),
        ]);
        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $pending), $this->payload($pending))
            ->assertRedirect(route('admin.orders.show', $pending));

        $expiredCandidates = Order::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id');
        $this->assertTrue($expiredCandidates->contains($pending->id));
        $this->assertFalse($candidateIds->contains($pending->id));

        $this->assertTrue(PendingOrderExpiry::expireIfStillPending($pending->fresh()));
        $expired = $pending->fresh();
        $this->assertSame('cancelled', $expired->status);
        $this->assertNotNull($expired->admin_archived_at);
        $this->assertSame('order_expire_keep', $expired->razorpay_order_id);
        $this->assertSame($owner->id, $expired->admin_archived_by_user_id);

        foreach ([
            app_path('Http/Controllers/PaymentController.php'),
            app_path('Http/Controllers/Api/RazorpayWebhookController.php'),
            app_path('Http/Controllers/Api/RazorpayCheckoutController.php'),
            app_path('Http/Controllers/CheckoutController.php'),
            app_path('Http/Controllers/AccountDashboardController.php'),
            app_path('Services/OrderPaymentService.php'),
            app_path('Services/OrderRefundService.php'),
            app_path('Services/PendingOrderExpiry.php'),
            app_path('Services/OrderNotificationService.php'),
            app_path('Console/Commands/ExpirePendingOrders.php'),
        ] as $path) {
            $this->assertStringNotContainsString('admin_archived_at', (string) file_get_contents($path), $path);
        }
    }

    public function test_owner_can_unarchive_without_changing_financial_state(): void
    {
        $owner = $this->owner();
        [$order, $product, $customer] = $this->inertGraph([
            'status' => 'cancelled',
            'razorpay_order_id' => 'order_unarchive_keep',
        ]);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.orders.show', $order));

        $before = $this->row($order);
        $this->captureLogs();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.destroy', $order), $this->payload($order, [
                'current_password' => 'wrong-password',
            ]))
            ->assertSessionHasErrors('current_password');
        $this->assertNotNull($order->fresh()->admin_archived_at);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.destroy', $order), $this->payload($order, [
                'order_number_confirmation' => 'VA-WRONG',
            ]))
            ->assertSessionHasErrors('order_number_confirmation');
        $this->assertSame(
            'Enter the order number exactly to confirm unarchiving.',
            session('errors')->first('order_number_confirmation'),
        );
        $this->assertNotNull($order->fresh()->admin_archived_at);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.destroy', $order), $this->payload($order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('success', 'Test order unarchived.');

        $fresh = $order->fresh();
        $this->assertNull($fresh->admin_archived_at);
        $this->assertNull($fresh->admin_archived_by_user_id);
        $this->assertNull($fresh->admin_archive_reason);
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('order_unarchive_keep', $fresh->razorpay_order_id);
        $this->assertNull($fresh->payment_id);
        $after = $this->row($order);
        $beforeComparable = $before;
        $afterComparable = $after;
        foreach (['admin_archived_at', 'admin_archived_by_user_id', 'admin_archive_reason'] as $column) {
            unset($beforeComparable[$column], $afterComparable[$column]);
        }
        $this->assertSame($beforeComparable, $afterComparable);
        $this->assertSame(4, (int) $product->fresh()->stock);
        $this->assertNotNull($customer->fresh());
        $this->assertSame(1, OrderItem::query()->where('order_id', $order->id)->count());
        $this->assertAuditLog($this->capturedLogs, (int) $order->id, (int) $owner->id, 'unarchive', OrderTestArchive::REASON_UNARCHIVE);
        $this->assertLogsHideSecrets($this->capturedLogs, $order, $customer, null, 'order_unarchive_keep');

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee((string) $order->order_number, false);

        Http::assertNothingSent();
    }

    public function test_lock_timeout_leaves_the_order_graph_unchanged(): void
    {
        config(['checkout.razorpay_lock_wait' => 0]);
        [$order, $product, $customer] = $this->inertGraph(['razorpay_order_id' => 'order_busy_keep']);
        $owner = $this->owner();
        $before = $this->snapshot();
        $lock = PaymentAtomicLock::forRazorpayOrder((int) $order->id);
        $this->assertTrue($lock->get());

        try {
            $this->actingAsAdmin($owner)
                ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
                ->assertSessionHasErrors('order');

            $message = (string) session('errors')->first('order');
            $this->assertSame('This order is busy; try again.', $message);
            $this->assertStringNotContainsString('order_busy_keep', $message);
            $this->assertStringNotContainsString((string) $order->order_number, $message);
            $this->assertStringNotContainsString((string) $customer->email, $message);
            $this->assertSame($before, $this->snapshot());
            $this->assertSame(4, (int) $product->fresh()->stock);
        } finally {
            $lock->release();
        }

        Http::assertNothingSent();
    }

    public function test_demoted_owner_is_rechecked_inside_the_lock(): void
    {
        [$order] = $this->inertGraph();
        $owner = $this->owner();
        $owner->forceFill(['admin_role' => AdminRole::ADMINISTRATOR])->save();

        try {
            app(OrderTestArchive::class)->archive($owner, (int) $order->id, (string) $order->order_number, 'password');
            $this->fail('Demoted owner archived an order.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertNull($order->fresh()->admin_archived_at);
    }

    public function test_checkout_still_offers_razorpay_only_and_defaults_to_disabled(): void
    {
        $source = (string) file_get_contents(config_path('checkout.php'));

        $this->assertMatchesRegularExpression(
            "/'payments_enabled'\\s*=>\\s*filter_var\\(env\\('CHECKOUT_PAYMENTS_ENABLED',\\s*false\\),\\s*FILTER_VALIDATE_BOOLEAN\\)/",
            $source,
        );
        $this->assertStringContainsString('Checkout offers Razorpay only.', $source);

        $checkout = (string) file_get_contents(resource_path('views/checkout/index.blade.php'));
        $this->assertStringContainsString('value="razorpay"', $checkout);
        $this->assertDoesNotMatchRegularExpression('/name="payment_method"[^>]*value="cod"/', $checkout);
        $this->assertStringNotContainsString('bank_transfer', $checkout);
    }

    private function assertRefusal(User $owner, Order $order, string $message, ?string $paymentId = null, ?string $gatewayId = null): void
    {
        $before = $this->snapshot();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), $this->payload($order))
            ->assertSessionHasErrors('order');

        $actual = (string) session('errors')->first('order');
        $this->assertSame($message, $actual);
        $this->assertStringNotContainsString((string) $order->order_number, $actual);
        $this->assertStringNotContainsString((string) $order->customer_email, $actual);
        $this->assertStringNotContainsString((string) ($paymentId ?? $order->payment_id ?? 'pay_unused_marker'), $actual);
        $this->assertStringNotContainsString((string) ($gatewayId ?? $order->razorpay_order_id ?? 'order_unused_marker'), $actual);
        $this->assertSame($before, $this->snapshot());
        $this->assertNull($order->fresh()->admin_archived_at);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function assertMetadataOnly(array $before, array $after): void
    {
        $this->assertNotNull($after['admin_archived_at']);
        $this->assertNotNull($after['admin_archived_by_user_id']);
        $this->assertSame(OrderTestArchive::REASON_ARCHIVE, $after['admin_archive_reason']);

        foreach (['admin_archived_at', 'admin_archived_by_user_id', 'admin_archive_reason'] as $column) {
            unset($before[$column], $after[$column]);
        }

        $this->assertSame($before, $after);
    }

    /**
     * @param  list<array{message: string, context: array<string, mixed>}>  $logs
     */
    private function assertAuditLog(array $logs, int $orderId, int $actorId, string $action, string $reason): void
    {
        $matches = array_values(array_filter(
            $logs,
            fn (array $log) => ($log['context']['order_id'] ?? null) === $orderId
                && ($log['context']['action'] ?? null) === $action,
        ));

        $this->assertCount(1, $matches);
        $this->assertSame([
            'order_id' => $orderId,
            'actor_admin_id' => $actorId,
            'action' => $action,
            'reason_code' => $reason,
        ], $matches[0]['context']);
    }

    /**
     * @param  list<array{message: string, context: array<string, mixed>}>  $logs
     */
    private function assertLogsHideSecrets(array $logs, Order $order, User $customer, ?string $paymentId, ?string $gatewayId): void
    {
        $encoded = json_encode($logs);
        $this->assertIsString($encoded);
        foreach ([
            (string) $order->order_number,
            (string) $order->customer_email,
            (string) $order->customer_phone,
            (string) $order->shipping_address,
            (string) $customer->email,
            'password',
            $paymentId,
            $gatewayId,
        ] as $secret) {
            if ($secret === null || $secret === '') {
                continue;
            }

            $this->assertStringNotContainsString($secret, $encoded);
        }
    }

    private function captureLogs(): void
    {
        $this->capturedLogs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->capturedLogs[] = [
                'message' => $event->message,
                'context' => $event->context,
            ];
        });
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
        $category = Category::factory()->create(['slug' => 'archive-'.uniqid()]);
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

    /**
     * @return array<string, mixed>
     */
    private function row(Order $order): array
    {
        return (array) DB::table('orders')->where('id', $order->id)->first();
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
