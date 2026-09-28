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
 * Opt-in MySQL 8 and MariaDB 10.11 proof for test-order archive columns.
 * The default suite skips this test. Set MYSQL_ORDER_ARCHIVE_TEST=1 and point
 * MYSQL_TEST_* at a disposable local database whose name ends in _test.
 */
class MysqlOrderArchiveMigrationTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_09_26_201200_add_admin_archive_columns_to_orders.php';

    private const CONNECTION = 'mysql_order_archive_test';

    protected function setUp(): void
    {
        $flag = getenv('MYSQL_ORDER_ARCHIVE_TEST');
        if ($flag === false) {
            $flag = $_ENV['MYSQL_ORDER_ARCHIVE_TEST'] ?? null;
        }

        if (! MysqlInvitationHarnessGuard::isEnabled($flag === false ? null : (string) $flag)) {
            $this->markTestSkipped('MySQL/MariaDB archive migration proof is opt-in and was NOT EXECUTED.');
        }

        parent::setUp();
    }

    public function test_engine_archive_migration_columns_retry_and_rollback_refusal(): void
    {
        $reasons = MysqlInvitationHarnessGuard::refusalReasons(
            $this->optionalEnv('MYSQL_TEST_HOST') ?? '127.0.0.1',
            $this->optionalEnv('MYSQL_TEST_DATABASE'),
            (string) config('database.connections.mysql.database'),
        );

        if ($reasons !== []) {
            $this->fail('Refusing to run the archive migration proof: '.implode('; ', $reasons));
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
            $this->fail('Opted-in archive migration proof could not connect to the local disposable database.');
        }

        $database = strtolower((string) DB::connection(self::CONNECTION)->getDatabaseName());
        if (! str_ends_with($database, '_test')) {
            $this->fail('Refusing to migrate a database whose name does not end with _test.');
        }

        Artisan::call('migrate:fresh', ['--force' => true]);

        $refundColumns = Schema::getColumnListing('order_refunds');
        $refundIndexes = array_column(Schema::getIndexes('order_refunds'), 'name');
        sort($refundIndexes);

        $this->assertArchiveShape();

        $userId = DB::table('users')->insertGetId([
            'name' => 'Owner',
            'email' => 'owner-archive@example.com',
            'password' => 'secret',
            'is_admin' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'VA-ENGINE-ARCHIVE',
            'customer_name' => 'Buyer',
            'customer_email' => 'buyer-archive@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'total' => 1000,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'payment_id' => 'pay_engine_archive',
            'razorpay_order_id' => 'order_engine_archive',
            'refund_status' => 'none',
            'refunded_amount_paise' => 0,
            'refund_pending_amount_paise' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_name' => 'Sample',
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $this->assertPreserved($orderId);

        DB::table('orders')->where('id', $orderId)->update([
            'admin_archived_at' => now(),
            'admin_archived_by_user_id' => $userId,
            'admin_archive_reason' => 'protected_test_order',
        ]);

        try {
            DB::table('users')->where('id', $userId)->delete();
            $this->fail('Deleting the archiving user must be restricted.');
        } catch (QueryException) {
            $this->assertNotNull(DB::table('users')->where('id', $userId)->first());
        }

        try {
            Artisan::call('migrate:rollback', [
                '--path' => self::MIGRATION,
                '--force' => true,
            ]);
            $this->fail('Rollback should refuse while archive evidence exists.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('archived order', $exception->getMessage());
            $this->assertStringNotContainsString('pay_engine_archive', $exception->getMessage());
            $this->assertStringNotContainsString('order_engine_archive', $exception->getMessage());
            $this->assertStringNotContainsString('buyer-archive@example.com', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('orders', 'admin_archived_at'));
        $this->assertPreserved($orderId);

        DB::table('orders')->where('id', $orderId)->update([
            'admin_archived_at' => null,
            'admin_archived_by_user_id' => null,
            'admin_archive_reason' => null,
        ]);

        Artisan::call('migrate:rollback', [
            '--path' => self::MIGRATION,
            '--force' => true,
        ]);

        $this->assertFalse(Schema::hasColumn('orders', 'admin_archived_at'));
        $this->assertFalse(Schema::hasColumn('orders', 'admin_archived_by_user_id'));
        $this->assertFalse(Schema::hasColumn('orders', 'admin_archive_reason'));
        $this->assertPreserved($orderId);
        $this->assertTrue(Schema::hasColumn('orders', 'captured_amount_paise'));
        $this->assertTrue(Schema::hasTable('order_refunds'));

        $migration->up();
        $migration->up();
        $this->assertArchiveShape();
        $this->assertPreserved($orderId);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_admin_archived_at_idx');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['admin_archived_by_user_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('admin_archive_reason');
        });

        $this->assertFalse(Schema::hasIndex('orders', 'orders_admin_archived_at_idx'));
        $this->assertFalse(Schema::hasColumn('orders', 'admin_archive_reason'));
        $this->assertNull($this->archiveForeignKey());

        $migration->up();
        $migration->up();

        $this->assertArchiveShape();
        $this->assertPreserved($orderId);
        $this->assertSame($refundColumns, Schema::getColumnListing('order_refunds'));
        $indexes = array_column(Schema::getIndexes('order_refunds'), 'name');
        sort($indexes);
        $this->assertSame($refundIndexes, $indexes);
        $this->assertNotSame([], $refundIndexes);
    }

    private function assertArchiveShape(): void
    {
        $columns = collect(Schema::getColumns('orders'))->keyBy('name');

        foreach ([
            'admin_archived_at' => 'time',
            'admin_archived_by_user_id' => 'int',
            'admin_archive_reason' => 'char',
        ] as $name => $typeFragment) {
            $this->assertTrue($columns->has($name), $name);
            $column = $columns->get($name);
            $this->assertTrue((bool) ($column['nullable'] ?? false), $name);
            $type = strtolower((string) (($column['type_name'] ?? '').' '.($column['type'] ?? '')));
            $this->assertStringContainsString($typeFragment, $type, $name);
        }

        $reason = strtolower((string) ($columns->get('admin_archive_reason')['type'] ?? ''));
        $this->assertStringContainsString('64', $reason);

        $this->assertTrue(Schema::hasIndex('orders', 'orders_admin_archived_at_idx'));
        $foreign = $this->archiveForeignKey();
        $this->assertNotNull($foreign);
        $this->assertSame('users', $foreign['foreign_table'] ?? null);
        $this->assertSame(['id'], $foreign['foreign_columns'] ?? null);
        $this->assertSame('restrict', strtolower((string) ($foreign['on_delete'] ?? '')));
    }

    private function assertPreserved(int $orderId): void
    {
        $row = (array) DB::table('orders')->where('id', $orderId)->first();

        $this->assertSame('pending', $row['status']);
        $this->assertSame('pay_engine_archive', $row['payment_id']);
        $this->assertSame('order_engine_archive', $row['razorpay_order_id']);
        $this->assertSame('buyer-archive@example.com', $row['customer_email']);
        $this->assertSame(1, DB::table('order_items')->where('order_id', $orderId)->count());
        $this->assertSame('Sample', DB::table('order_items')->where('order_id', $orderId)->value('product_name'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function archiveForeignKey(): ?array
    {
        foreach (Schema::getForeignKeys('orders') as $foreign) {
            if (in_array('admin_archived_by_user_id', $foreign['columns'] ?? [], true)) {
                return $foreign;
            }
        }

        return null;
    }

    private function requiredEnv(string $key): string
    {
        $value = $this->optionalEnv($key);

        if ($value === null) {
            $this->fail($key.' must be provided explicitly for the archive migration harness.');
        }

        return $value;
    }

    private function optionalEnv(string $key): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? null;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
