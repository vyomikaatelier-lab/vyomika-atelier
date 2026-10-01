<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductFulfilmentOriginal;
use App\Services\ProductFulfilmentBackfill;
use App\Support\ProductFulfilment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Tests\Support\MysqlInvitationHarnessGuard;
use Tests\TestCase;
use Throwable;

/**
 * Opt-in MySQL and MariaDB proof for fulfilment columns, snapshots, and backfill.
 * The default suite skips this test. It uses only MYSQL_TEST_* on a disposable
 * database whose name ends in _test.
 */
class MysqlProductFulfilmentMigrationTest extends TestCase
{
    private const CONNECTION = 'mysql_product_fulfilment_test';

    protected function setUp(): void
    {
        $flag = getenv('MYSQL_ORDER_REFUND_TEST');
        if ($flag === false) {
            $flag = $_ENV['MYSQL_ORDER_REFUND_TEST'] ?? null;
        }

        if (! MysqlInvitationHarnessGuard::isEnabled($flag === false ? null : (string) $flag)) {
            $this->markTestSkipped('MySQL/MariaDB fulfilment migration proof is opt-in and was NOT EXECUTED.');
        }

        parent::setUp();
    }

    public function test_engine_fulfilment_schema_snapshot_and_backfill(): void
    {
        $reasons = MysqlInvitationHarnessGuard::refusalReasons(
            $this->optionalEnv('MYSQL_TEST_HOST') ?? '127.0.0.1',
            $this->optionalEnv('MYSQL_TEST_DATABASE'),
            (string) config('database.connections.mysql.database'),
        );
        if ($reasons !== []) {
            $this->fail('Refusing to run the fulfilment migration proof: '.implode('; ', $reasons));
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
        } catch (PDOException $exception) {
            $this->fail('Opted-in fulfilment proof could not connect to the disposable database.');
        }

        $database = strtolower((string) DB::connection(self::CONNECTION)->getDatabaseName());
        if (! str_ends_with($database, '_test')) {
            $this->fail('Refusing to migrate a database whose name does not end with _test.');
        }

        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertTrue(Schema::hasColumn('products', 'availability_mode'));
        $this->assertTrue(Schema::hasColumn('products', 'needs_fulfilment_review'));
        $this->assertTrue(Schema::hasColumn('orders', 'packing_cost'));
        $this->assertTrue(Schema::hasColumn('orders', 'fulfilment_snapshot'));
        $this->assertTrue(Schema::hasColumn('order_items', 'fulfilment_snapshot'));
        $this->assertTrue(Schema::hasColumn('order_refunds', 'packing_amount_paise'));
        $this->assertTrue(Schema::hasTable('product_fulfilment_originals'));

        $historicalId = DB::table('orders')->insertGetId([
            'order_number' => 'VA-HISTORICAL-FF',
            'customer_name' => 'Historical',
            'customer_email' => 'historical-ff@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'status' => 'paid',
            'payment_method' => 'razorpay',
            'payment_id' => 'pay_historical_ff',
            'refund_status' => 'none',
            'refunded_amount_paise' => 0,
            'refund_pending_amount_paise' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = require base_path('database/migrations/2026_10_01_160000_add_product_fulfilment_and_order_charge_snapshots.php');
        $migration->up();
        $this->assertSame('199.00', number_format((float) DB::table('orders')->where('id', $historicalId)->value('shipping_cost'), 2, '.', ''));
        $this->assertNull(DB::table('orders')->where('id', $historicalId)->value('packing_cost'));
        $this->assertNull(DB::table('orders')->where('id', $historicalId)->value('fulfilment_snapshot'));

        $shop = Product::factory()->create([
            'section' => Product::SECTION_SHOP,
            'stock' => 7,
            'hide_when_out_of_stock' => false,
            'availability_mode' => ProductFulfilment::AVAILABILITY_CONFIRM,
            'shipping_india_mode' => ProductFulfilment::CHARGE_QUOTED,
            'packing_india_mode' => ProductFulfilment::CHARGE_QUOTED,
            'tab_shipping' => "Estimated production time: 3–4 weeks.\nThe estimated production lead time will be confirmed after your order is placed.",
        ]);
        $unknown = Product::factory()->create([
            'section' => 'archive',
            'stock' => 0,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
            'tab_shipping' => 'Delivery is usually 5 to 12 business days.',
        ]);

        $counts = app(ProductFulfilmentBackfill::class)->run();
        $this->assertSame(1, $counts['shop']);
        $this->assertSame(1, $counts['unknown']);
        $shop->refresh();
        $unknown->refresh();
        $this->assertSame(ProductFulfilment::AVAILABILITY_READY, $shop->availability_mode);
        $this->assertSame(7, $shop->stock);
        $this->assertFalse($shop->hide_when_out_of_stock);
        $this->assertFalse($shop->needs_fulfilment_review);
        $this->assertStringContainsString('3–4 weeks', (string) $shop->tab_shipping);
        $this->assertSame('archive', $unknown->section);
        $this->assertSame(ProductFulfilment::AVAILABILITY_CONFIRM, $unknown->availability_mode);
        $this->assertTrue($unknown->needs_fulfilment_review);
        $original = ProductFulfilmentOriginal::query()->where('product_id', $shop->id)->firstOrFail();
        $this->assertSame(7, $original->original['stock']);

        $shop->forceFill(['availability_mode' => ProductFulfilment::AVAILABILITY_CONFIRM])->save();
        $again = app(ProductFulfilmentBackfill::class)->run();
        $this->assertSame(2, $again['skipped']);
        $this->assertSame(ProductFulfilment::AVAILABILITY_CONFIRM, $shop->fresh()->availability_mode);
        $this->assertSame(7, $original->fresh()->original['stock']);

        DB::table('orders')->where('id', $historicalId)->update([
            'fulfilment_snapshot' => json_encode(['version' => 1]),
        ]);
        try {
            Artisan::call('migrate:rollback', [
                '--path' => 'database/migrations/2026_10_01_160000_add_product_fulfilment_and_order_charge_snapshots.php',
                '--force' => true,
            ]);
            $this->fail('Rollback should refuse while a fulfilment snapshot exists.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('Cannot roll back fulfilment settings', $exception->getMessage());
        }
        $this->assertSame('pay_historical_ff', DB::table('orders')->where('id', $historicalId)->value('payment_id'));
        $this->assertSame('199.00', number_format((float) DB::table('orders')->where('id', $historicalId)->value('shipping_cost'), 2, '.', ''));
        $this->assertNotNull(DB::table('orders')->where('id', $historicalId)->value('fulfilment_snapshot'));
    }

    private function requiredEnv(string $key): string
    {
        $value = $this->optionalEnv($key);
        if ($value === null) {
            $this->fail($key.' must be provided explicitly for the fulfilment harness.');
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
