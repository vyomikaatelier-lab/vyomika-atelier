<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\CheckoutSnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PaymentLockConcurrencyTest extends TestCase
{
    private string $sharedDbPath;

    protected function setUp(): void
    {
        $this->sharedDbPath = tempnam(sys_get_temp_dir(), 'vyomika_pay_lock_').'.sqlite';
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
    }

    protected function tearDown(): void
    {
        if (isset($this->sharedDbPath) && is_file($this->sharedDbPath)) {
            @unlink($this->sharedDbPath);
        }

        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_DATABASE'] = ':memory:';
        putenv('DB_CONNECTION=sqlite');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_CONNECTION'] = 'sqlite';

        parent::tearDown();
    }

    private function workerEnv(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->sharedDbPath,
            'CACHE_STORE' => 'database',
        ];
    }

    private function startWorker(string ...$arguments): \Illuminate\Process\InvokedProcess
    {
        return Process::path(base_path())
            ->timeout(120)
            ->env($this->workerEnv())
            ->start(array_merge([PHP_BINARY, 'tests/Support/concurrent_payment_worker.php'], $arguments));
    }

    private function disconnectSharedDatabase(): void
    {
        DB::disconnect('sqlite');
    }

    private function reconnectSharedDatabase(): void
    {
        config(['database.connections.sqlite.database' => $this->sharedDbPath]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    /** @return array<string, mixed> */
    private function decodeWorkerOutput(\Illuminate\Contracts\Process\ProcessResult $result): array
    {
        $this->assertTrue(
            $result->successful(),
            trim($result->errorOutput()."\n".$result->output())
        );

        return json_decode(trim($result->output()), true, 512, JSON_THROW_ON_ERROR);
    }

    private function seedPendingOrder(User $user, Product $product): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9876543210',
            'shipping_address' => '123 Test Building, Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'country' => 'India',
            'subtotal' => $product->price,
            'shipping_cost' => 199,
            'total' => $product->price + 199,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'razorpay_order_id' => null,
            'shipping_snapshot' => CheckoutSnapshot::withSource([
                'full_name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '9876543210',
                'formatted_line' => '123 Test Building, Test Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
                'country' => 'India',
            ], CheckoutSnapshot::SOURCE_CART),
            'expires_at' => now()->addDay(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
            'total' => $product->price,
        ]);

        return $order;
    }

    private function shopProduct(): Product
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);

        return Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 10,
            'price' => 1000,
        ]);
    }

    public function test_two_processes_share_one_razorpay_order_for_one_local_order(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'phone_verified_at' => now()]);
        $product = $this->shopProduct();
        $order = $this->seedPendingOrder($user, $product);
        $counterFile = tempnam(sys_get_temp_dir(), 'vyomika_rzp_count_');
        file_put_contents($counterFile, '0');

        $this->disconnectSharedDatabase();

        $first = $this->startWorker('razorpay', $this->sharedDbPath, (string) $order->id, $counterFile);
        $second = $this->startWorker('razorpay', $this->sharedDbPath, (string) $order->id, $counterFile);

        $firstResult = $this->decodeWorkerOutput($first->wait());
        $secondResult = $this->decodeWorkerOutput($second->wait());

        $this->reconnectSharedDatabase();

        $this->assertSame('order_process_shared', $firstResult['razorpay_order_id']);
        $this->assertSame('order_process_shared', $secondResult['razorpay_order_id']);
        $this->assertSame('order_process_shared', $order->fresh()->razorpay_order_id);
        $this->assertSame('1', trim((string) file_get_contents($counterFile)));

        @unlink($counterFile);
    }

    public function test_two_processes_cannot_create_two_active_local_orders_for_one_customer(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'phone_verified_at' => now(),
            'email' => 'jane@example.com',
            'mobile' => '9876543210',
        ]);
        $product = $this->shopProduct();
        $counterFile = tempnam(sys_get_temp_dir(), 'vyomika_checkout_count_');
        file_put_contents($counterFile, '0');

        $this->disconnectSharedDatabase();

        $first = $this->startWorker('checkout', $this->sharedDbPath, (string) $user->id, (string) $product->id, $counterFile);
        $second = $this->startWorker('checkout', $this->sharedDbPath, (string) $user->id, (string) $product->id, $counterFile);

        $this->decodeWorkerOutput($first->wait());
        $this->decodeWorkerOutput($second->wait());

        $this->reconnectSharedDatabase();

        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
        $this->assertSame('order_checkout_shared', Order::query()->value('razorpay_order_id'));
        $this->assertSame('1', trim((string) file_get_contents($counterFile)));

        @unlink($counterFile);
    }

    public function test_callback_and_webhook_workers_deduct_stock_once(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $product = $this->shopProduct();
        $product->update(['stock' => 4]);
        $order = $this->seedPendingOrder($user, $product);
        $order->update(['razorpay_order_id' => 'order_parallel_capture']);

        $this->disconnectSharedDatabase();

        $first = $this->startWorker('settle', $this->sharedDbPath, (string) $order->id, 'pay_parallel_once');
        $second = $this->startWorker('settle', $this->sharedDbPath, (string) $order->id, 'pay_parallel_once');
        $firstResult = $this->decodeWorkerOutput($first->wait());
        $secondResult = $this->decodeWorkerOutput($second->wait());

        $this->reconnectSharedDatabase();

        $this->assertEqualsCanonicalizing(
            ['paid', 'already_processed'],
            [$firstResult['result'], $secondResult['result']]
        );
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('pay_parallel_once', $order->fresh()->payment_id);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_expiry_racing_capture_does_not_drop_the_captured_payment(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $product = $this->shopProduct();
        $product->update(['stock' => 5]);
        $order = $this->seedPendingOrder($user, $product);
        $order->update([
            'razorpay_order_id' => 'order_parallel_expire',
            'expires_at' => now()->subMinute(),
        ]);

        $this->disconnectSharedDatabase();

        $settle = $this->startWorker('settle', $this->sharedDbPath, (string) $order->id, 'pay_parallel_expire');
        $expire = $this->startWorker('expire', $this->sharedDbPath, (string) $order->id);
        $this->decodeWorkerOutput($settle->wait());
        $this->decodeWorkerOutput($expire->wait());

        $this->reconnectSharedDatabase();

        $fresh = $order->fresh();
        $this->assertSame('reconciliation_required', $fresh->status);
        $this->assertSame('pay_parallel_expire', $fresh->payment_id);
        $this->assertNull($fresh->stock_deducted_at);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_stale_admin_status_write_loses_to_reconciliation(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $product = $this->shopProduct();
        $product->update(['stock' => 0]);
        $order = $this->seedPendingOrder($user, $product);
        $order->update([
            'razorpay_order_id' => 'order_admin_race',
            'admin_notes' => 'original-note',
        ]);
        $gate = tempnam(sys_get_temp_dir(), 'vyomika_admin_gate_');
        file_put_contents($gate, 'wait');

        $this->disconnectSharedDatabase();

        $admin = $this->startWorker(
            'admin-update',
            $this->sharedDbPath,
            (string) $order->id,
            'processing',
            'stale-note',
            $gate,
        );
        $settle = $this->startWorker('settle', $this->sharedDbPath, (string) $order->id, 'pay_admin_race');
        $this->decodeWorkerOutput($settle->wait());
        file_put_contents($gate, 'go');
        $adminResult = $this->decodeWorkerOutput($admin->wait());

        $this->reconnectSharedDatabase();

        $fresh = $order->fresh();
        $this->assertSame('status_locked', $adminResult['outcome']);
        $this->assertSame('reconciliation_required', $fresh->status);
        $this->assertSame('pay_admin_race', $fresh->payment_id);
        $this->assertSame('insufficient_stock', $fresh->reconciliation_reason);
        $this->assertSame('original-note', $fresh->admin_notes);
        $this->assertNull($fresh->stock_deducted_at);
        $this->assertSame(0, $product->fresh()->stock);

        @unlink($gate);
    }
}
