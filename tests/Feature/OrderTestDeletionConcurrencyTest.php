<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderTestDeletion;
use App\Support\AdminRole;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class OrderTestDeletionConcurrencyTest extends TestCase
{
    private string $sharedDbPath;

    protected function setUp(): void
    {
        $this->sharedDbPath = tempnam(sys_get_temp_dir(), 'vyomika_order_delete_').'.sqlite';
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
            'database.connections.sqlite.busy_timeout' => 15000,
            'database.connections.sqlite.journal_mode' => 'wal',
            'cache.default' => 'database',
            'cache.stores.database.connection' => 'sqlite',
            'cache.stores.database.lock_connection' => 'sqlite',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::statement('PRAGMA busy_timeout = 8000');
        Artisan::call('migrate:fresh', ['--force' => true]);
        DB::statement('PRAGMA journal_mode = WAL');
        DB::statement('PRAGMA busy_timeout = 8000');
        Http::preventStrayRequests();
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

    public function test_concurrent_deletes_do_not_leave_a_partial_order_graph(): void
    {
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $customer = User::factory()->create(['is_admin' => false]);
        $order = $this->inertOrder(['user_id' => $customer->id, 'order_number' => 'VA-DELETE1']);
        $product = $this->attachItem($order, 'delete-a');
        $sibling = $this->inertOrder(['order_number' => 'VA-KEEP1']);
        $siblingProduct = $this->attachItem($sibling, 'delete-b');

        DB::disconnect('sqlite');
        $left = $this->start([
            'user_id' => $owner->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'password' => 'password',
        ]);
        $right = $this->start([
            'user_id' => $owner->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'password' => 'password',
        ]);
        $leftResult = $this->decode($left->wait());
        $rightResult = $this->decode($right->wait());
        $this->reconnect();

        $results = [$leftResult['result'] ?? null, $rightResult['result'] ?? null];
        $deleted = array_values(array_filter(
            $results,
            fn ($result) => $result === OrderTestDeletion::DELETED,
        ));
        $this->assertCount(1, $deleted, json_encode($results));
        $loser = array_values(array_filter(
            $results,
            fn ($result) => $result !== OrderTestDeletion::DELETED,
        ));
        $this->assertContains($loser[0] ?? null, [
            OrderTestDeletion::MISSING,
            OrderTestDeletion::BLOCKED_STATUS,
        ]);

        $this->assertFalse(DB::table('orders')->where('id', $order->id)->exists());
        $this->assertSame(0, DB::table('order_items')->where('order_id', $order->id)->count());
        $this->assertNotNull($sibling->fresh());
        $this->assertSame(1, OrderItem::query()->where('order_id', $sibling->id)->count());
        $this->assertNotNull($customer->fresh());
        $this->assertNotNull($product->fresh());
        $this->assertSame(4, (int) $product->fresh()->stock);
        $this->assertNotNull($siblingProduct->fresh());
        $this->assertSame(0, DB::table('order_refunds')->count());
        Http::assertNothingSent();
    }

    public function test_deletion_waits_for_gateway_order_creation_and_then_refuses_the_persisted_id(): void
    {
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $customer = User::factory()->create(['is_admin' => false]);
        $order = $this->inertOrder([
            'user_id' => $customer->id,
            'order_number' => 'VA-GATE1',
            'total' => 1000,
        ]);
        $product = $this->attachItem($order, 'gate-a');
        $holding = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-hold-'.uniqid();
        $release = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-release-'.uniqid();
        $entered = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-entered-'.uniqid();
        $finished = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-finished-'.uniqid();
        $gateway = null;
        $delete = null;

        try {
            DB::disconnect('sqlite');
            $gateway = $this->startGateway([
                'order_id' => $order->id,
                'holding_file' => $holding,
                'release_file' => $release,
            ]);
            $this->waitForFile($holding);
            $this->reconnect();
            $this->assertNull($order->fresh()->razorpay_order_id);
            $this->assertSame(1, OrderItem::query()->where('order_id', $order->id)->count());

            DB::disconnect('sqlite');
            $delete = $this->start([
                'user_id' => $owner->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'password' => 'password',
                'entered_file' => $entered,
                'finished_file' => $finished,
            ]);
            $this->waitForFile($entered);

            $sawHold = false;
            $deadline = microtime(true) + 3;

            while (microtime(true) < $deadline) {
                $this->assertFileDoesNotExist($finished);
                $this->assertTrue($delete->running());
                $this->reconnect();
                $fresh = DB::table('orders')->where('id', $order->id)->first();
                $this->assertNotNull($fresh);
                $this->assertNull($fresh->razorpay_order_id);
                $this->assertSame(1, DB::table('order_items')->where('order_id', $order->id)->count());
                $this->assertTrue(
                    DB::table('cache_locks')->where('key', 'like', '%razorpay:order:'.$order->id)->exists()
                );
                $sawHold = true;
                usleep(40000);
            }

            $this->assertTrue($sawHold);
            file_put_contents($release, '1');

            $gatewayResult = $this->decode($gateway->wait());
            $deleteResult = $this->decode($delete->wait());
            $this->reconnect();

            $this->assertSame(1, $gatewayResult['posts'] ?? null);
            $this->assertTrue($gatewayResult['ok'] ?? false);
            $this->assertSame(OrderTestDeletion::BLOCKED_GATEWAY, $deleteResult['result'] ?? null);
            $storedId = (string) $order->fresh()->razorpay_order_id;
            $this->assertSame('order_gate_hold', $storedId);
            $this->assertSame(1, OrderItem::query()->where('order_id', $order->id)->count());
            $this->assertNotNull($customer->fresh());
            $this->assertNotNull($product->fresh());
            $this->assertSame(4, (int) $product->fresh()->stock);
            $encoded = json_encode($deleteResult, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString($storedId, $encoded);
            $this->assertStringNotContainsString((string) $customer->email, $encoded);
            $this->assertStringNotContainsString((string) $order->customer_email, $encoded);
            Http::assertNothingSent();
        } finally {
            if (! is_file($release)) {
                file_put_contents($release, '1');
            }

            foreach ([$holding, $release, $entered, $finished] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function startGateway(array $payload): InvokedProcess
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
                'tests/Support/concurrent_gateway_order_worker.php',
                $this->sharedDbPath,
                json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
    }

    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 15;

        while (! is_file($path)) {
            if (microtime(true) > $deadline) {
                $this->fail('Timed out waiting for a worker signal.');
            }

            usleep(20000);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function start(array $payload): InvokedProcess
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
                'tests/Support/concurrent_order_delete_worker.php',
                $this->sharedDbPath,
                json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ProcessResult $result): array
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
     * @param  array<string, mixed>  $overrides
     */
    private function inertOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'VA-'.strtoupper(substr(uniqid(), -8)),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
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

    private function attachItem(Order $order, string $slug): Product
    {
        $category = Category::factory()->create(['slug' => $slug]);
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
}
