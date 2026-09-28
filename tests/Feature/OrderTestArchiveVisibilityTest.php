<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderTestArchive;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class OrderTestArchiveVisibilityTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::swap(new Factory);
        Http::preventStrayRequests();
        config([
            'cache.stores.database.connection' => 'sqlite',
            'cache.stores.database.lock_connection' => 'sqlite',
        ]);
    }

    public function test_archived_inert_rows_stay_off_the_default_list_and_on_the_owner_view(): void
    {
        $owner = $this->owner();
        $pending = $this->archiveInert($owner, 'pending', 'VA-INERT-P');
        $cancelled = $this->archiveInert($owner, 'cancelled', 'VA-INERT-C');

        $default = $this->actingAsAdmin($owner)->get(route('admin.orders.index'));
        $default->assertOk();
        $default->assertDontSee('VA-INERT-P', false);
        $default->assertDontSee('VA-INERT-C', false);

        $archived = $this->actingAsAdmin($owner)->get(route('admin.orders.index', ['archived' => 1]));
        $archived->assertOk();
        $archived->assertSee('VA-INERT-P', false);
        $archived->assertSee('VA-INERT-C', false);
        $archived->assertSee('Pending', false);
        $archived->assertSee('Cancelled', false);
        $archived->assertSee('Archived test order', false);
        $archived->assertDontSee('Delete test order', false);

        $this->assertFalse(
            Order::query()->visibleInDefaultAdminIndex()->whereKey([$pending->id, $cancelled->id])->exists()
        );
        $this->assertStringNotContainsString('admin_archived_at', Order::query()->toSql());
    }

    #[DataProvider('laterEvidence')]
    public function test_later_evidence_returns_the_archived_order_to_the_default_list(string $case): void
    {
        $owner = $this->owner();
        $order = $this->archiveInert($owner, 'pending', 'VA-LATER');
        $this->applyEvidence($order, $case);

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('VA-LATER', false)
            ->assertSee('Archived test order', false);

        $this->assertTrue(Order::query()->visibleInDefaultAdminIndex()->whereKey($order->id)->exists());
        $this->assertNotNull($order->fresh()->admin_archived_at);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function laterEvidence(): array
    {
        return [
            'stored payment id' => ['payment_id'],
            'stock deducted' => ['stock'],
            'paid' => ['paid'],
            'processing' => ['processing'],
            'shipped' => ['shipped'],
            'delivered' => ['delivered'],
            'reconciliation required' => ['reconciliation_required'],
            'reconciliation reason' => ['reconciliation_reason'],
            'reconciliation metadata' => ['reconciliation_meta'],
            'captured paise' => ['captured_paise'],
            'refunded paise' => ['refunded_paise'],
            'pending refund paise' => ['pending_paise'],
            'refund status pending' => ['refund_pending'],
            'refund status partial' => ['refund_partial'],
            'refund status refunded' => ['refund_refunded'],
            'unknown status' => ['unknown_status'],
            'malformed paise' => ['malformed_paise'],
            'blank payment id' => ['blank_payment_id'],
            'whitespace gateway id' => ['whitespace_gateway_id'],
            'negative paise' => ['negative_paise'],
        ];
    }

    public function test_partial_archive_metadata_fails_visible(): void
    {
        $owner = $this->owner();
        $missingActor = $this->archiveInert($owner, 'pending', 'VA-PART-A');
        DB::table('orders')->where('id', $missingActor->id)->update(['admin_archived_by_user_id' => null]);

        $missingReason = $this->archiveInert($owner, 'pending', 'VA-PART-R');
        DB::table('orders')->where('id', $missingReason->id)->update(['admin_archive_reason' => null]);

        $whitespaceReason = $this->archiveInert($owner, 'cancelled', 'VA-PART-W');
        DB::table('orders')->where('id', $whitespaceReason->id)->update(['admin_archive_reason' => '   ']);

        $onlyReason = $this->inertOrder(['order_number' => 'VA-PART-O', 'razorpay_order_id' => 'order_partial_only', 'status' => 'pending']);
        DB::table('orders')->where('id', $onlyReason->id)->update([
            'admin_archived_at' => null,
            'admin_archived_by_user_id' => null,
            'admin_archive_reason' => OrderTestArchive::REASON_ARCHIVE,
        ]);

        $index = $this->actingAsAdmin($owner)->get(route('admin.orders.index'));
        $index->assertOk();
        $index->assertSee('VA-PART-A', false);
        $index->assertSee('VA-PART-R', false);
        $index->assertSee('VA-PART-W', false);
        $index->assertSee('VA-PART-O', false);
    }

    public function test_a_refund_row_makes_the_archived_order_visible(): void
    {
        $owner = $this->owner();
        $order = $this->archiveInert($owner, 'cancelled', 'VA-REFUND-ROW');
        $this->insertRefund($order, $owner, 'row-only');

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('VA-REFUND-ROW', false)
            ->assertSee('Archived test order', false);
    }

    public function test_refund_line_evidence_on_the_order_item_makes_it_visible_without_its_own_refund_row(): void
    {
        $owner = $this->owner();
        $order = $this->archiveInert($owner, 'pending', 'VA-REFUND-LINE');
        $other = $this->inertOrder(['order_number' => 'VA-OTHER', 'status' => 'paid', 'payment_id' => 'pay_other_line']);
        $refundId = $this->insertRefund($other, $owner, 'line-host');
        $itemId = (int) OrderItem::query()->where('order_id', $order->id)->value('id');

        DB::table('order_refund_lines')->insert([
            'order_refund_id' => $refundId,
            'order_item_id' => $itemId,
            'quantity' => 1,
            'amount_paise' => 100,
            'stock_restoration' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, DB::table('order_refunds')->where('order_id', $order->id)->count());
        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('VA-REFUND-LINE', false);
    }

    public function test_refund_event_evidence_makes_the_archived_order_visible(): void
    {
        $owner = $this->owner();
        $order = $this->archiveInert($owner, 'pending', 'VA-REFUND-EVENT');
        $refundId = $this->insertRefund($order, $owner, 'event-host');
        DB::table('order_refund_events')->insert([
            'order_refund_id' => $refundId,
            'event' => 'reserved',
            'created_at' => now(),
        ]);

        $sql = Order::query()->visibleInDefaultAdminIndex()->toSql();
        $this->assertStringContainsString('order_refund_events', $sql);
        $this->assertStringContainsString('order_refund_lines', $sql);
        $this->assertStringContainsString('order_refunds', $sql);

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('VA-REFUND-EVENT', false)
            ->assertSee('Archived test order', false);
    }

    public function test_real_status_and_archive_badge_are_both_rendered_and_delete_stays_refused(): void
    {
        $owner = $this->owner();
        $order = $this->archiveInert($owner, 'pending', 'VA-BADGE');
        DB::table('orders')->where('id', $order->id)->update(['status' => 'paid', 'payment_id' => 'pay_badge_only']);

        $index = $this->actingAsAdmin($owner)->get(route('admin.orders.index'));
        $index->assertOk();
        $index->assertSee('VA-BADGE', false);
        $index->assertSee('Paid', false);
        $index->assertSee('Archived test order', false);

        $archived = $this->actingAsAdmin($owner)->get(route('admin.orders.index', ['archived' => 1]));
        $archived->assertOk();
        $archived->assertSee('VA-BADGE', false);
        $archived->assertSee('Paid', false);
        $archived->assertSee('Archived test order', false);
        $archived->assertSee('Unarchive', false);

        $show = $this->actingAsAdmin($owner)->get(route('admin.orders.show', $order));
        $show->assertOk();
        $show->assertSee('Paid', false);
        $show->assertSee('Archived test order', false);
        $show->assertSee('Unarchive', false);
        $show->assertDontSee('Delete test order', false);
        $show->assertSee('Archiving did not delete the order', false);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-deletion.destroy', $order), [
                'current_password' => 'password',
                'order_number_confirmation' => 'VA-BADGE',
            ])
            ->assertSessionHasErrors('order');

        $this->assertSame('This order is archived and cannot be deleted.', session('errors')->first('order'));
        $this->assertNotNull($order->fresh());
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->admin_archived_at);
    }

    public function test_order_manager_sees_a_later_active_archived_row_and_is_denied_the_archived_filter(): void
    {
        $owner = $this->owner();
        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
        $order = $this->archiveInert($owner, 'pending', 'VA-MANAGER');
        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'processing',
            'payment_id' => 'pay_manager_visible',
        ]);

        $index = $this->actingAsAdmin($manager)->get(route('admin.orders.index'));
        $index->assertOk();
        $index->assertSee('VA-MANAGER', false);
        $index->assertSee('Processing', false);
        $index->assertSee('Archived test order', false);
        $index->assertDontSee('Archived test orders', false);
        $index->assertDontSee('Unarchive', false);

        $this->actingAsAdmin($manager)
            ->get(route('admin.orders.index', ['archived' => 1]))
            ->assertForbidden();

        $this->actingAsAdmin($manager)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Processing', false)
            ->assertSee('Archived test order', false)
            ->assertDontSee('Unarchive', false)
            ->assertDontSee('Delete test order', false);
    }

    public function test_pagination_applies_after_the_visibility_predicate(): void
    {
        $owner = $this->owner();
        $plain = $this->inertOrder(['order_number' => 'VA-PLAIN']);
        DB::table('orders')->where('id', $plain->id)->update(['created_at' => now()->subHour()]);

        $evidence = $this->archiveInert($owner, 'pending', 'VA-PAGE-ON');
        DB::table('orders')->where('id', $evidence->id)->update([
            'payment_id' => 'pay_page_on',
            'created_at' => now()->subMinute(),
        ]);

        foreach (range(1, 15) as $index) {
            $hidden = $this->archiveInert($owner, 'pending', 'VA-HIDE'.str_pad((string) $index, 2, '0', STR_PAD_LEFT));
            DB::table('orders')->where('id', $hidden->id)->update(['created_at' => now()]);
        }

        $page = $this->actingAsAdmin($owner)->get(route('admin.orders.index'));
        $page->assertOk();
        $page->assertSee('VA-PAGE-ON', false);
        $page->assertSee('VA-PLAIN', false);
        $page->assertDontSee('VA-HIDE01', false);
        $page->assertDontSee('page=2', false);

        $this->assertSame(2, Order::query()->visibleInDefaultAdminIndex()->count());
    }

    public function test_default_index_uses_the_shared_scope_and_not_a_second_null_archive_filter(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Admin/OrderAdminController.php'));

        $this->assertStringContainsString('visibleInDefaultAdminIndex()', $source);
        $this->assertStringNotContainsString("whereNull('admin_archived_at')", $source);
        $this->assertStringContainsString("whereNotNull('admin_archived_at')", $source);
    }

    private function applyEvidence(Order $order, string $case): void
    {
        $update = match ($case) {
            'payment_id' => ['payment_id' => 'pay_later_evidence'],
            'stock' => ['stock_deducted_at' => now()],
            'paid' => ['status' => 'paid'],
            'processing' => ['status' => 'processing'],
            'shipped' => ['status' => 'shipped'],
            'delivered' => ['status' => 'delivered'],
            'reconciliation_required' => ['status' => 'reconciliation_required'],
            'reconciliation_reason' => ['reconciliation_reason' => 'captured_after_cancel'],
            'reconciliation_meta' => ['reconciliation_meta' => '{"kept":true}'],
            'captured_paise' => ['captured_amount_paise' => 100],
            'refunded_paise' => ['refunded_amount_paise' => 100],
            'pending_paise' => ['refund_pending_amount_paise' => 100],
            'refund_pending' => ['refund_status' => 'pending'],
            'refund_partial' => ['refund_status' => 'partial'],
            'refund_refunded' => ['refund_status' => 'refunded'],
            'unknown_status' => ['status' => 'not-a-real-status'],
            'malformed_paise' => ['captured_amount_paise' => 'abc'],
            'blank_payment_id' => ['payment_id' => ''],
            'whitespace_gateway_id' => ['razorpay_order_id' => '   '],
            'negative_paise' => ['captured_amount_paise' => -1],
            default => throw new \InvalidArgumentException('Unknown evidence case.'),
        };

        DB::table('orders')->where('id', $order->id)->update($update);

        if ($case === 'malformed_paise') {
            $stored = DB::table('orders')->where('id', $order->id)->value('captured_amount_paise');
            $this->assertSame('abc', (string) $stored);
        }
    }

    private function archiveInert(User $owner, string $status, string $number): Order
    {
        $order = $this->inertOrder([
            'order_number' => $number,
            'status' => $status,
            'razorpay_order_id' => 'order_'.strtolower(str_replace('-', '_', $number)),
        ]);
        $this->attachItem($order);

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.test-archive.store', $order), [
                'current_password' => 'password',
                'order_number_confirmation' => $number,
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->admin_archived_at);
        $this->assertSame(OrderTestArchive::REASON_ARCHIVE, $fresh->admin_archive_reason);

        return $fresh;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function inertOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'VA-'.strtoupper(substr(uniqid(), -8)),
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
        $category = Category::factory()->create(['slug' => 'archive-vis-'.uniqid()]);
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
}
