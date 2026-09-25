<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderRefundMigrationTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'vyomika_refund_mig_').'.sqlite';
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

    public function test_round_trip_refuses_down_while_refund_evidence_exists_and_can_resume(): void
    {
        $this->assertTrue(Schema::hasTable('order_refunds'));
        $this->assertTrue(Schema::hasColumn('orders', 'captured_amount_paise'));
        $this->assertTrue(Schema::hasColumn('orders', 'refund_status'));
        $this->assertTrue(Schema::hasIndex('order_refunds', 'order_refunds_idempotency_uq', 'unique'));
        $this->assertTrue(Schema::hasIndex('order_refunds', 'order_refunds_gateway_uq', 'unique'));
        $this->assertTrue(Schema::hasIndex('order_refunds', 'order_refunds_receipt_uq', 'unique'));

        $paid = Order::create($this->orderAttributes('VA-PAID', 'paid', 'pay_keep'));
        $this->assertSame('none', $paid->fresh()->refund_status);
        $this->assertSame(0, (int) $paid->fresh()->refunded_amount_paise);

        $owner = User::factory()->admin()->create();
        $refundId = $this->insertRefund($paid->id, $owner->id, 'rf-keep-receipt', 'idem-keep-key');

        try {
            Artisan::call('migrate:rollback', [
                '--path' => 'database/migrations/2026_09_25_100000_create_order_refunds.php',
                '--force' => true,
            ]);
            $this->fail('Rollback should refuse while a refund ledger row exists.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('refund evidence', $exception->getMessage());
            $this->assertStringNotContainsString('pay_keep', $exception->getMessage());
            $this->assertStringNotContainsString('buyer@example.com', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('order_refunds'));
        $this->assertSame('pay_keep', $paid->fresh()->payment_id);

        DB::table('order_refunds')->where('id', $refundId)->delete();
        $paid->forceFill([
            'captured_amount_paise' => null,
            'refunded_amount_paise' => 0,
            'refund_pending_amount_paise' => 0,
            'refund_status' => 'none',
        ])->save();

        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2026_09_25_100000_create_order_refunds.php',
            '--force' => true,
        ]);

        $this->assertFalse(Schema::hasTable('order_refunds'));
        $this->assertFalse(Schema::hasColumn('orders', 'refund_status'));
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame('pay_keep', $paid->fresh()->payment_id);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_09_25_100000_create_order_refunds.php',
            '--force' => true,
        ]);

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('order_refund_events');
        Schema::dropIfExists('order_refund_lines');
        Schema::dropIfExists('order_refunds');
        Schema::enableForeignKeyConstraints();

        $migration = require database_path('migrations/2026_09_25_100000_create_order_refunds.php');
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasTable('order_refunds'));
        $this->assertTrue(Schema::hasColumn('orders', 'captured_amount_paise'));
        $this->assertSame('paid', DB::table('orders')->where('id', $paid->id)->value('status'));

        $first = $this->insertRefund($paid->fresh()->id, $owner->id, 'rf-null-a', 'idem-null-a', null);
        $second = $this->insertRefund($paid->fresh()->id, $owner->id, 'rf-null-b', 'idem-null-b', null);
        $this->assertNotSame($first, $second);
        $this->assertNull(DB::table('order_refunds')->where('id', $first)->value('gateway_refund_id'));

        try {
            $this->insertRefund($paid->fresh()->id, $owner->id, 'rf-null-a', 'idem-dup-receipt', 'rfnd_dup');
            $this->fail('Duplicate receipts must be rejected.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function orderAttributes(string $number, string $status, ?string $paymentId): array
    {
        return [
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
            'status' => $status,
            'payment_method' => 'razorpay',
            'payment_id' => $paymentId,
        ];
    }

    private function insertRefund(int $orderId, int $userId, string $receipt, string $key, ?string $gatewayId = 'rfnd_one'): int
    {
        return (int) DB::table('order_refunds')->insertGetId([
            'order_id' => $orderId,
            'payment_id' => 'pay_keep',
            'idempotency_key' => $key,
            'gateway_refund_id' => $gatewayId,
            'receipt' => $receipt,
            'amount_paise' => 1000,
            'currency' => 'INR',
            'kind' => 'full',
            'reason_code' => 'customer_request',
            'status' => OrderRefund::STATUS_PROCESSED,
            'includes_shipping' => false,
            'shipping_amount_paise' => 0,
            'actor_user_id' => $userId,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
