<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Tests\Support\MysqlInvitationHarnessGuard;
use Tests\TestCase;
use Throwable;

/**
 * Opt-in MySQL and MariaDB proof for the refund ledger.
 * The default suite skips this test. Point MYSQL_TEST_* at a disposable
 * database whose name ends in _test. Never use production DB_* credentials.
 * MariaDB is exercised by the same assertions through the mysql protocol driver.
 */
class MysqlOrderRefundMigrationTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_09_25_100000_create_order_refunds.php';

    private const CONNECTION = 'mysql_order_refund_test';

    protected function setUp(): void
    {
        $flag = getenv('MYSQL_ORDER_REFUND_TEST');
        if ($flag === false) {
            $flag = $_ENV['MYSQL_ORDER_REFUND_TEST'] ?? null;
        }

        if (! MysqlInvitationHarnessGuard::isEnabled($flag === false ? null : (string) $flag)) {
            $this->markTestSkipped('MySQL/MariaDB refund ledger proof is opt-in and was NOT EXECUTED.');
        }

        parent::setUp();
    }

    public function test_engine_refund_migration_constraints_retry_and_rollback_refusal(): void
    {
        $reasons = MysqlInvitationHarnessGuard::refusalReasons(
            $this->optionalEnv('MYSQL_TEST_HOST') ?? '127.0.0.1',
            $this->optionalEnv('MYSQL_TEST_DATABASE'),
            (string) config('database.connections.mysql.database'),
        );

        if ($reasons !== []) {
            $this->fail('Refusing to run the refund ledger proof: '.implode('; ', $reasons));
        }

        config([
            'database.default' => self::CONNECTION,
            'database.connections.'.self::CONNECTION => [
                'driver' => 'mysql',
                'host' => $this->optionalEnv('MYSQL_TEST_HOST') ?? '127.0.0.1',
                'port' => $this->optionalEnv('MYSQL_TEST_PORT') ?? '3306',
                'database' => $this->requiredEnv('MYSQL_TEST_DATABASE'),
                'username' => $this->requiredEnv('MYSQL_TEST_USERNAME'),
                'password' => $this->optionalEnv('MYSQL_TEST_PASSWORD') ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],
        ]);

        try {
            DB::purge(self::CONNECTION);
            DB::reconnect(self::CONNECTION);
            DB::connection(self::CONNECTION)->getPdo();
        } catch (PDOException|QueryException) {
            $this->fail('Opted-in refund ledger proof could not connect to the local disposable database.');
        }

        $database = strtolower((string) DB::connection(self::CONNECTION)->getDatabaseName());
        if (! str_ends_with($database, '_test')) {
            $this->fail('Refusing to migrate a database whose name does not end with _test.');
        }

        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertTrue(Schema::hasTable('order_refunds'));
        $this->assertTrue(Schema::hasIndex('order_refunds', 'order_refunds_idempotency_uq', 'unique'));
        $this->assertTrue(Schema::hasIndex('order_refunds', 'order_refunds_gateway_uq', 'unique'));
        $this->assertTrue(Schema::hasIndex('order_refunds', 'order_refunds_receipt_uq', 'unique'));
        $this->assertNotSame([], Schema::getForeignKeys('order_refunds'));

        $userId = DB::table('users')->insertGetId([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret',
            'is_admin' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'VA-ENGINE',
            'customer_name' => 'Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'total' => 1000,
            'status' => 'paid',
            'payment_method' => 'razorpay',
            'payment_id' => 'pay_engine',
            'refund_status' => 'none',
            'refunded_amount_paise' => 0,
            'refund_pending_amount_paise' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertRefund($orderId, $userId, 'rf-engine-a', 'idem-engine-a', null);
        $this->insertRefund($orderId, $userId, 'rf-engine-b', 'idem-engine-b', null);

        try {
            $this->insertRefund($orderId, $userId, 'rf-engine-a', 'idem-engine-c', 'rfnd_engine');
            $this->fail('Duplicate receipts must be rejected.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        try {
            $this->insertRefund(999999, $userId, 'rf-missing-order', 'idem-missing-order', null);
            $this->fail('A refund must reference an order.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        try {
            Artisan::call('migrate:rollback', [
                '--path' => self::MIGRATION,
                '--force' => true,
            ]);
            $this->fail('Rollback should refuse while refund rows exist.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('refund evidence', $exception->getMessage());
        }

        DB::table('order_refunds')->delete();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('order_refund_events');
        Schema::dropIfExists('order_refund_lines');
        Schema::dropIfExists('order_refunds');
        Schema::enableForeignKeyConstraints();

        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $migration->up();
        $this->assertTrue(Schema::hasTable('order_refunds'));
        $this->assertSame('paid', DB::table('orders')->where('id', $orderId)->value('status'));
        $this->assertSame('pay_engine', DB::table('orders')->where('id', $orderId)->value('payment_id'));
    }

    private function insertRefund(int $orderId, int $userId, string $receipt, string $key, ?string $gatewayId): void
    {
        DB::table('order_refunds')->insert([
            'order_id' => $orderId,
            'payment_id' => 'pay_engine',
            'idempotency_key' => $key,
            'gateway_refund_id' => $gatewayId,
            'receipt' => $receipt,
            'amount_paise' => 1000,
            'currency' => 'INR',
            'kind' => 'full',
            'reason_code' => 'customer_request',
            'status' => 'processed',
            'includes_shipping' => false,
            'shipping_amount_paise' => 0,
            'actor_user_id' => $userId,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function requiredEnv(string $key): string
    {
        $value = $this->optionalEnv($key);

        if ($value === null) {
            $this->fail($key.' must be provided explicitly for the refund ledger harness.');
        }

        return $value;
    }

    private function optionalEnv(string $key): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
