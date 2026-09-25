<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\Product;
use App\Models\User;
use App\Support\AdminRole;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class OrderRefundConcurrencyTest extends TestCase
{
    private string $sharedDbPath;

    protected function setUp(): void
    {
        $this->sharedDbPath = tempnam(sys_get_temp_dir(), 'vyomika_refund_lock_').'.sqlite';
        touch($this->sharedDbPath);

        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE='.$this->sharedDbPath);
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $this->sharedDbPath;
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = $this->sharedDbPath;

        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->sharedDbPath,
            'cache.default' => 'database',
            'cache.stores.database.connection' => 'sqlite',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::statement('PRAGMA busy_timeout = 8000');
        Artisan::call('migrate:fresh', ['--force' => true]);
        DB::statement('PRAGMA journal_mode = WAL');
        DB::statement('PRAGMA busy_timeout = 8000');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->sharedDbPath) && is_file($this->sharedDbPath)) {
            @unlink($this->sharedDbPath);
            @unlink($this->sharedDbPath.'-wal');
            @unlink($this->sharedDbPath.'-shm');
        }

        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_DATABASE'] = ':memory:';

        parent::tearDown();
    }

    public function test_duplicate_clicks_create_one_refund_and_partials_do_not_over_refund(): void
    {
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        [$order, $first, $second] = $this->paidOrder();
        $firstItemId = (int) DB::table('order_items')->where('order_id', $order->id)->where('product_id', $first->id)->value('id');
        $secondItemId = (int) DB::table('order_items')->where('order_id', $order->id)->where('product_id', $second->id)->value('id');

        DB::disconnect('sqlite');
        $firstClick = $this->start('refund', [
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'key' => 'idem-duplicate-click',
            'kind' => 'partial',
            'confirmation' => $order->order_number,
            'include_shipping' => false,
            'lines' => [$firstItemId => 1],
        ]);
        $secondClick = $this->start('refund', [
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'key' => 'idem-duplicate-click',
            'kind' => 'partial',
            'confirmation' => $order->order_number,
            'include_shipping' => false,
            'lines' => [$firstItemId => 1],
        ]);
        $left = $this->decode($firstClick->wait());
        $right = $this->decode($secondClick->wait());
        $this->reconnect();

        $this->assertSame(1, ($left['posts'] ?? 0) + ($right['posts'] ?? 0));
        $this->assertSame(1, OrderRefund::query()->count());
        $this->assertSame(40000, (int) OrderRefund::query()->sum('amount_paise'));
        $this->assertSame(4, $first->fresh()->stock);

        DB::disconnect('sqlite');
        $other = $this->start('refund', [
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'key' => 'idem-second-line',
            'kind' => 'partial',
            'confirmation' => $order->order_number,
            'include_shipping' => true,
            'lines' => [$secondItemId => 1],
        ]);
        $this->decode($other->wait());
        $this->reconnect();

        $this->assertLessThanOrEqual(119900, (int) OrderRefund::query()->where('status', 'processed')->sum('amount_paise'));
        $this->assertSame(4, $second->fresh()->stock);
    }

    public function test_refund_does_not_deadlock_with_expiry(): void
    {
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        [$order, $product] = $this->paidOrder(false);
        $stockBefore = (int) $product->stock;

        DB::disconnect('sqlite');
        $refund = $this->start('refund', [
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'key' => 'idem-versus-expiry',
            'kind' => 'full',
            'confirmation' => $order->order_number,
            'include_shipping' => true,
            'lines' => [],
        ]);
        $expiry = $this->start('expire', ['order_id' => $order->id]);

        $refundResult = $this->decode($refund->wait());
        $expiryResult = $this->decode($expiry->wait());
        $this->reconnect();

        $this->assertSame('processed', $refundResult['status'] ?? null);
        $this->assertFalse($expiryResult['expired'] ?? true);
        $this->assertSame('pay_refund', $order->fresh()->payment_id);
        $this->assertSame($stockBefore + 1, (int) $product->fresh()->stock);
    }

    public function test_refund_does_not_replace_payment_id_when_settlement_races(): void
    {
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        [$order, $product] = $this->paidOrder(false);
        $stockBefore = (int) $product->stock;

        DB::disconnect('sqlite');
        $refund = $this->start('refund', [
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'key' => 'idem-versus-settle',
            'kind' => 'full',
            'confirmation' => $order->order_number,
            'include_shipping' => true,
            'lines' => [],
        ]);
        $settle = $this->start('settle', [
            'order_id' => $order->id,
            'payment_id' => 'pay_second_capture',
        ]);

        $refundResult = json_decode(trim($refund->wait()->output()), true) ?: [];
        $this->decode($settle->wait());
        $this->reconnect();

        $fresh = $order->fresh();
        $this->assertSame('pay_refund', $fresh->payment_id);
        $this->assertLessThanOrEqual($stockBefore + 1, (int) $product->fresh()->stock);
        $this->assertGreaterThanOrEqual($stockBefore, (int) $product->fresh()->stock);

        if (($refundResult['ok'] ?? false) === true) {
            $this->assertSame('processed', $refundResult['status'] ?? null);
        } else {
            $this->assertStringContainsString('reconciliation', strtolower((string) ($refundResult['message'] ?? '')));
            $this->assertSame($stockBefore, (int) $product->fresh()->stock);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function start(string $action, array $payload): \Illuminate\Process\InvokedProcess
    {
        return Process::path(base_path())
            ->timeout(120)
            ->env([
                'APP_ENV' => 'testing',
                'APP_KEY' => (string) config('app.key'),
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->sharedDbPath,
                'CACHE_STORE' => 'database',
            ])
            ->start([
                PHP_BINARY,
                'tests/Support/concurrent_refund_worker.php',
                $action,
                $this->sharedDbPath,
                json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\Illuminate\Contracts\Process\ProcessResult $result): array
    {
        $this->assertTrue($result->successful(), trim($result->errorOutput()."\n".$result->output()));

        return json_decode(trim($result->output()), true, 512, JSON_THROW_ON_ERROR);
    }

    private function reconnect(): void
    {
        config(['database.connections.sqlite.database' => $this->sharedDbPath]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    /**
     * @return array{0: Order, 1: Product, 2?: Product}
     */
    private function paidOrder(bool $twoLines = true): array
    {
        $order = Order::create([
            'order_number' => 'VA-CONCUR1',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'status' => 'paid',
            'payment_method' => 'razorpay',
            'payment_id' => 'pay_refund',
            'razorpay_order_id' => 'order_refund',
            'stock_deducted_at' => now(),
            'expires_at' => null,
        ]);

        $first = $this->item($order, 400, 3, 'concur-a');
        if (! $twoLines) {
            $only = $this->item($order, 1000, 4, 'concur-one');
            OrderItem::query()->whereKey($first->id)->delete();
            $first->delete();

            return [$order, $only];
        }

        $second = $this->item($order, 600, 3, 'concur-b');

        return [$order, $first, $second];
    }

    private function item(Order $order, int $price, int $stock, string $slug): Product
    {
        $category = Category::factory()->create(['slug' => $slug]);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => $stock,
            'price' => $price,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $price,
            'quantity' => 1,
            'total' => $price,
        ]);

        return $product;
    }
}
