<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Tests\Support\MysqlInvitationHarnessGuard;
use Tests\TestCase;
use Throwable;

/**
 * Opt-in MySQL/MariaDB proof for the payment reconciliation migration.
 * The default suite skips this test. It was not executed unless
 * MYSQL_PAYMENT_RECONCILIATION_TEST=1 and MYSQL_TEST_* point at a local
 * disposable database whose name ends in _test.
 *
 * Never point these variables at production. Never use DB_* credentials.
 */
class MysqlPaymentReconciliationMigrationTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_09_24_140000_add_payment_reconciliation_state_to_orders.php';

    private const CONNECTION = 'mysql_payment_reconciliation_test';

    private bool $harnessConfigured = false;

    protected function setUp(): void
    {
        $flag = getenv('MYSQL_PAYMENT_RECONCILIATION_TEST');
        if ($flag === false) {
            $flag = $_ENV['MYSQL_PAYMENT_RECONCILIATION_TEST'] ?? null;
        }

        if (! MysqlInvitationHarnessGuard::isEnabled($flag === false ? null : (string) $flag)) {
            $this->markTestSkipped('MySQL/MariaDB payment reconciliation migration proof is opt-in and was NOT EXECUTED.');
        }

        parent::setUp();

        $reasons = MysqlInvitationHarnessGuard::refusalReasons(
            $this->optionalEnv('MYSQL_TEST_HOST') ?? '127.0.0.1',
            $this->optionalEnv('MYSQL_TEST_DATABASE'),
            (string) config('database.connections.mysql.database'),
        );

        if ($reasons !== []) {
            $this->fail('Refusing to run MySQL payment reconciliation proof: '.implode('; ', $reasons));
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->dropHarnessOrders();
        } finally {
            parent::tearDown();
        }
    }

    public function test_mysql_reconciliation_migration_enum_rollback_guard_and_retry(): void
    {
        $this->configureMysql();
        $this->createOrdersTable();
        $this->insertOrder(1, 'pending', 'VA-MY-PENDING', null);
        $this->insertOrder(2, 'paid', 'VA-MY-PAID', 'pay_mysql_paid');
        $this->insertOrder(3, 'cancelled', 'VA-MY-CANCELLED', null);

        Artisan::call('migrate', [
            '--path' => self::MIGRATION,
            '--force' => true,
        ]);

        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_reason'));
        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_meta'));
        $this->assertStatusEnum(includesReconciliation: true);
        $this->insertOrder(4, 'reconciliation_required', 'VA-MY-REVIEW', 'pay_mysql_review');
        $this->assertSame('pending', $this->statusOf(1));
        $this->assertSame('paid', $this->statusOf(2));
        $this->assertSame('cancelled', $this->statusOf(3));

        $this->assertRollbackRefused(['pay_mysql_review', 'buyer@example.com']);

        DB::table('orders')->where('id', 4)->delete();
        DB::table('orders')->where('id', 2)->update([
            'reconciliation_reason' => 'duplicate_capture',
            'reconciliation_meta' => json_encode(['extra_payment_ids' => ['pay_mysql_extra']]),
        ]);
        $this->assertRollbackRefused(['pay_mysql_extra', 'pay_mysql_paid']);

        DB::table('orders')->where('id', 2)->update([
            'reconciliation_reason' => null,
            'reconciliation_meta' => json_encode(['conflicting_payment_ids' => ['pay_mysql_conflict']]),
        ]);
        $this->assertRollbackRefused(['pay_mysql_conflict']);

        DB::table('orders')->where('id', 2)->update([
            'reconciliation_reason' => null,
            'reconciliation_meta' => null,
        ]);

        Artisan::call('migrate:rollback', [
            '--path' => self::MIGRATION,
            '--force' => true,
        ]);

        $this->assertFalse(Schema::hasColumn('orders', 'reconciliation_reason'));
        $this->assertFalse(Schema::hasColumn('orders', 'reconciliation_meta'));
        $this->assertStatusEnum(includesReconciliation: false);
        $this->assertSame('pending', $this->statusOf(1));
        $this->assertSame('paid', $this->statusOf(2));
        $this->assertSame('pay_mysql_paid', DB::table('orders')->where('id', 2)->value('payment_id'));
        $this->assertSame('cancelled', $this->statusOf(3));

        Schema::table('orders', function (Blueprint $table) {
            $table->string('reconciliation_reason', 64)->nullable()->after('payment_id');
        });

        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_reason'));
        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_meta'));
        $this->assertStatusEnum(includesReconciliation: true);
        $this->assertSame('paid', $this->statusOf(2));
        $this->assertNull(DB::table('orders')->where('id', 2)->value('reconciliation_reason'));
    }

    private function configureMysql(): void
    {
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
            $this->fail('Opted-in MySQL payment reconciliation proof could not connect to the local disposable database.');
        }

        $this->harnessConfigured = true;
    }

    private function createOrdersTable(): void
    {
        $this->dropHarnessOrders();

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->text('shipping_address');
            $table->string('city');
            $table->string('pincode');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('status', ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->string('payment_method')->default('razorpay');
            $table->string('payment_id')->nullable();
            $table->timestamps();
        });
    }

    private function insertOrder(int $id, string $status, string $number, ?string $paymentId): void
    {
        DB::table('orders')->insert([
            'id' => $id,
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertStatusEnum(bool $includesReconciliation): void
    {
        $column = DB::select("SHOW COLUMNS FROM orders WHERE Field = 'status'");
        $this->assertCount(1, $column);
        $type = (string) $column[0]->Type;

        foreach (['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'] as $status) {
            $this->assertStringContainsString("'".$status."'", $type);
        }

        $this->assertSame($includesReconciliation, str_contains($type, "'reconciliation_required'"));
        $this->assertSame('NO', (string) $column[0]->Null);
        $this->assertSame('pending', (string) $column[0]->Default);
    }

    /**
     * @param  list<string>  $secrets
     */
    private function assertRollbackRefused(array $secrets): void
    {
        try {
            Artisan::call('migrate:rollback', [
                '--path' => self::MIGRATION,
                '--force' => true,
            ]);
            $this->fail('Rollback should refuse while reconciliation evidence exists.');
        } catch (Throwable $e) {
            $this->assertStringContainsString('unresolved reconciliation records', $e->getMessage());

            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $e->getMessage());
            }
        }

        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_reason'));
        $this->assertTrue(Schema::hasColumn('orders', 'reconciliation_meta'));
    }

    private function statusOf(int $id): string
    {
        return (string) DB::table('orders')->where('id', $id)->value('status');
    }

    private function dropHarnessOrders(): void
    {
        if (! $this->harnessConfigured) {
            return;
        }

        $connection = DB::connection(self::CONNECTION);
        $database = strtolower((string) $connection->getDatabaseName());

        if ($database === '' || ! str_ends_with($database, '_test')) {
            return;
        }

        try {
            Schema::dropIfExists('orders');
        } catch (Throwable) {
            // Cleanup must stay on the disposable test database.
        }
    }

    private function requiredEnv(string $key): string
    {
        $value = $this->optionalEnv($key);

        if ($value === null) {
            $this->fail($key.' must be provided explicitly for the MySQL payment reconciliation harness.');
        }

        return $value;
    }

    private function optionalEnv(string $key): ?string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
