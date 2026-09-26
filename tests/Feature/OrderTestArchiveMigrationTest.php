<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderTestArchiveMigrationTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_09_26_201200_add_admin_archive_columns_to_orders.php';

    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'vyomika_archive_mig_').'.sqlite';
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

    public function test_round_trip_refuses_rollback_while_archive_evidence_exists_and_can_resume(): void
    {
        $this->assertTrue(Schema::hasColumn('orders', 'admin_archived_at'));
        $this->assertTrue(Schema::hasColumn('orders', 'admin_archived_by_user_id'));
        $this->assertTrue(Schema::hasColumn('orders', 'admin_archive_reason'));
        $this->assertTrue(Schema::hasIndex('orders', 'orders_admin_archived_at_idx'));
        $this->assertTrue(Schema::hasTable('order_refunds'));

        $foreign = $this->archiveForeignKey();
        $this->assertNotNull($foreign);
        $this->assertSame('users', $foreign['foreign_table'] ?? null);
        $this->assertSame('restrict', strtolower((string) ($foreign['on_delete'] ?? '')));

        $owner = User::factory()->admin()->create();
        $order = Order::create($this->orderAttributes('VA-KEEP1', 'pending', 'pay_keep', 'order_keep'));
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Sample',
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
        ]);
        $before = $this->orderRow($order->id);

        DB::table('orders')->where('id', $order->id)->update([
            'admin_archived_at' => now(),
            'admin_archived_by_user_id' => $owner->id,
            'admin_archive_reason' => 'protected_test_order',
        ]);

        try {
            $owner->delete();
            $this->fail('Deleting the archiving user must be restricted.');
        } catch (QueryException) {
            $this->assertNotNull($owner->fresh());
            $this->assertNotNull($order->fresh());
        }

        try {
            Artisan::call('migrate:rollback', [
                '--path' => self::MIGRATION,
                '--force' => true,
            ]);
            $this->fail('Rollback should refuse while archive evidence exists.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('archived order', $exception->getMessage());
            $this->assertStringNotContainsString('pay_keep', $exception->getMessage());
            $this->assertStringNotContainsString('order_keep', $exception->getMessage());
            $this->assertStringNotContainsString('buyer@example.com', $exception->getMessage());
            $this->assertStringNotContainsString('VA-KEEP1', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('orders', 'admin_archived_at'));
        $this->assertSame('pay_keep', $order->fresh()->payment_id);
        $this->assertSame('order_keep', $order->fresh()->razorpay_order_id);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(1, OrderItem::query()->where('order_id', $order->id)->count());

        DB::table('orders')->where('id', $order->id)->update([
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
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('pay_keep', $order->fresh()->payment_id);
        $this->assertSame('order_keep', $order->fresh()->razorpay_order_id);
        $this->assertSame(1, OrderItem::query()->where('order_id', $order->id)->count());
        $this->assertTrue(Schema::hasTable('order_refunds'));

        Artisan::call('migrate', [
            '--path' => self::MIGRATION,
            '--force' => true,
        ]);

        $this->assertTrue(Schema::hasColumn('orders', 'admin_archive_reason'));
        $this->assertNotNull($this->archiveForeignKey());

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['admin_archived_by_user_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('admin_archive_reason');
        });

        $this->assertFalse($this->foreignKeyExists());
        $this->assertFalse(Schema::hasColumn('orders', 'admin_archive_reason'));

        $migration = require database_path('migrations/2026_09_26_201200_add_admin_archive_columns_to_orders.php');
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('orders', 'admin_archived_at'));
        $this->assertTrue(Schema::hasColumn('orders', 'admin_archived_by_user_id'));
        $this->assertTrue(Schema::hasColumn('orders', 'admin_archive_reason'));
        $this->assertTrue($this->foreignKeyExists());
        $this->assertSame('orders_admin_archived_at_idx', $this->archiveIndexName());

        $after = $this->orderRow($order->id);
        $this->assertSame($before['status'], $after['status']);
        $this->assertSame($before['payment_id'], $after['payment_id']);
        $this->assertSame($before['razorpay_order_id'], $after['razorpay_order_id']);
        $this->assertSame($before['customer_email'], $after['customer_email']);
        $this->assertSame($before['stock_deducted_at'], $after['stock_deducted_at']);
        $this->assertNull($after['admin_archived_at']);
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(1, DB::table('order_items')->count());
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

    private function foreignKeyExists(): bool
    {
        return $this->archiveForeignKey() !== null;
    }

    private function archiveIndexName(): ?string
    {
        foreach (Schema::getIndexes('orders') as $index) {
            if (($index['name'] ?? null) === 'orders_admin_archived_at_idx') {
                return $index['name'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(int $id): array
    {
        return (array) DB::table('orders')->where('id', $id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderAttributes(string $number, string $status, ?string $paymentId, ?string $gatewayId): array
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
            'razorpay_order_id' => $gatewayId,
        ];
    }
}
