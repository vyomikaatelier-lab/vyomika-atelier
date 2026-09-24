<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentReconciliationMigrationTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'vyomika_reconcile_mig_').'.sqlite';
        touch($this->databasePath);

        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE='.$this->databasePath);
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $this->databasePath;
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = $this->databasePath;

        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            @unlink($this->databasePath);
        }

        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_DATABASE'] = ':memory:';

        parent::tearDown();
    }

    public function test_migration_round_trip_preserves_paid_orders_and_refuses_unsafe_down(): void
    {
        $paid = $this->order('paid', 'pay_keep', 'VA-PAID-KEEP');

        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2026_09_24_140000_add_payment_reconciliation_state_to_orders.php',
            '--force' => true,
        ]);

        $this->assertFalse(Schema::hasColumn('orders', 'reconciliation_reason'));
        $this->assertSame('paid', DB::table('orders')->where('id', $paid->id)->value('status'));
        $this->assertSame('pay_keep', DB::table('orders')->where('id', $paid->id)->value('payment_id'));

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_09_24_140000_add_payment_reconciliation_state_to_orders.php',
            '--force' => true,
        ]);

        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_reason'));
        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_meta'));
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame('pay_keep', $paid->fresh()->payment_id);
        $this->assertNull($paid->fresh()->reconciliation_reason);

        $review = $this->order('reconciliation_required', 'pay_review', 'VA-REVIEW');

        $failed = false;
        try {
            Artisan::call('migrate:rollback', [
                '--path' => 'database/migrations/2026_09_24_140000_add_payment_reconciliation_state_to_orders.php',
                '--force' => true,
            ]);
        } catch (\Throwable $e) {
            $failed = true;
            $this->assertStringContainsString('unresolved reconciliation records', $e->getMessage());
            $this->assertStringNotContainsString('pay_review', $e->getMessage());
        }

        $this->assertTrue($failed);
        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_reason'));
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame('reconciliation_required', $review->fresh()->status);
        $this->assertSame('pay_review', $review->fresh()->payment_id);
    }

    public function test_down_refuses_paid_duplicate_capture_and_meta_only_evidence(): void
    {
        $duplicate = $this->order('paid', 'pay_original_keep', 'VA-DUP');
        $duplicate->update([
            'reconciliation_reason' => 'duplicate_capture',
            'reconciliation_meta' => ['extra_payment_ids' => ['pay_extra_hidden']],
        ]);

        $this->assertRollbackRefused(['pay_original_keep', 'pay_extra_hidden', 'jane@example.com']);
        $this->assertSame('paid', $duplicate->fresh()->status);
        $this->assertSame(['pay_extra_hidden'], $duplicate->fresh()->reconciliation_meta['extra_payment_ids']);

        $duplicate->update([
            'reconciliation_reason' => null,
            'reconciliation_meta' => ['extra_payment_ids' => ['pay_meta_only']],
        ]);
        $this->assertRollbackRefused(['pay_meta_only']);
        $this->assertSame(['pay_meta_only'], $duplicate->fresh()->reconciliation_meta['extra_payment_ids']);

        $duplicate->update([
            'reconciliation_meta' => ['conflicting_payment_ids' => ['pay_conflict_only']],
        ]);
        $this->assertRollbackRefused(['pay_conflict_only']);
        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_meta'));
        $this->assertSame(['pay_conflict_only'], $duplicate->fresh()->reconciliation_meta['conflicting_payment_ids']);
    }

    public function test_down_succeeds_when_pending_paid_and_cancelled_rows_have_no_evidence(): void
    {
        $pending = $this->order('pending', null, 'VA-PENDING');
        $paid = $this->order('paid', 'pay_plain', 'VA-PAID');
        $cancelled = $this->order('cancelled', null, 'VA-CANCELLED');

        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2026_09_24_140000_add_payment_reconciliation_state_to_orders.php',
            '--force' => true,
        ]);

        $this->assertFalse(Schema::hasColumn('orders', 'reconciliation_reason'));
        $this->assertFalse(Schema::hasColumn('orders', 'reconciliation_meta'));
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame('pay_plain', $paid->fresh()->payment_id);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_up_can_be_retried_after_a_partial_column_add(): void
    {
        $paid = $this->order('paid', 'pay_retry', 'VA-RETRY');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reconciliation_meta');
        });
        $this->assertFalse(Schema::hasColumn('orders', 'reconciliation_meta'));

        $migration = require database_path('migrations/2026_09_24_140000_add_payment_reconciliation_state_to_orders.php');
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_reason'));
        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_meta'));
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame('pay_retry', $paid->fresh()->payment_id);
        $this->assertNull($paid->fresh()->reconciliation_reason);
        $this->assertNull($paid->fresh()->reconciliation_meta);
    }

    /**
     * @param  list<string>  $secrets
     */
    private function assertRollbackRefused(array $secrets): void
    {
        $failed = false;

        try {
            Artisan::call('migrate:rollback', [
                '--path' => 'database/migrations/2026_09_24_140000_add_payment_reconciliation_state_to_orders.php',
                '--force' => true,
            ]);
        } catch (\Throwable $e) {
            $failed = true;
            $this->assertStringContainsString('unresolved reconciliation records', $e->getMessage());

            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $e->getMessage());
            }
        }

        $this->assertTrue($failed);
        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_reason'));
        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_meta'));
    }

    private function order(string $status, ?string $paymentId, string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'total' => 1000,
            'status' => $status,
            'payment_method' => 'razorpay',
            'payment_id' => $paymentId,
        ]);
    }
}
