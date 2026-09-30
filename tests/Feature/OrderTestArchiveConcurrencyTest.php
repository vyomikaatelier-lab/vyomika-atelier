<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderTestArchive;
use App\Support\AdminRole;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class OrderTestArchiveConcurrencyTest extends TestCase
{
    private string $sharedDbPath;

    protected function setUp(): void
    {
        $this->sharedDbPath = tempnam(sys_get_temp_dir(), 'vyomika_order_archive_').'.sqlite';
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

    public function test_concurrent_archives_result_in_one_transition(): void
    {
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $customer = User::factory()->create(['is_admin' => false]);
        $order = $this->inertOrder([
            'user_id' => $customer->id,
            'order_number' => 'VA-ARCH1',
            'razorpay_order_id' => 'order_concurrent_keep',
        ]);
        $product = $this->attachItem($order, 'archive-a');
        $sibling = $this->inertOrder(['order_number' => 'VA-KEEP1']);
        $beforeSibling = (array) DB::table('orders')->where('id', $sibling->id)->first();
        $before = (array) DB::table('orders')->where('id', $order->id)->first();

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
        $archived = array_values(array_filter(
            $results,
            fn ($result) => $result === OrderTestArchive::ARCHIVED,
        ));
        $this->assertCount(1, $archived, json_encode($results));
        $this->assertContains(OrderTestArchive::ALREADY_ARCHIVED, $results);

        $fresh = (array) DB::table('orders')->where('id', $order->id)->first();
        $this->assertNotNull($fresh['admin_archived_at']);
        $this->assertSame($owner->id, (int) $fresh['admin_archived_by_user_id']);
        $this->assertSame(OrderTestArchive::REASON_ARCHIVE, $fresh['admin_archive_reason']);
        $this->assertSame('order_concurrent_keep', $fresh['razorpay_order_id']);
        $this->assertSame('pending', $fresh['status']);
        foreach (['admin_archived_at', 'admin_archived_by_user_id', 'admin_archive_reason'] as $column) {
            unset($before[$column], $fresh[$column]);
        }
        $this->assertSame($before, $fresh);
        $this->assertSame(1, DB::table('order_items')->where('order_id', $order->id)->count());
        $this->assertSame($beforeSibling, (array) DB::table('orders')->where('id', $sibling->id)->first());
        $this->assertNotNull($customer->fresh());
        $this->assertNotNull($product->fresh());
        $this->assertSame(4, (int) $product->fresh()->stock);
        $encoded = json_encode([$leftResult, $rightResult]);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('order_concurrent_keep', $encoded);
        $this->assertStringNotContainsString((string) $customer->email, $encoded);
        $this->assertStringNotContainsString('VA-ARCH1', $encoded);
        Http::assertNothingSent();
    }

    public function test_archive_waits_for_gateway_order_creation_and_keeps_the_id(): void
    {
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $customer = User::factory()->create(['is_admin' => false]);
        $order = $this->inertOrder([
            'user_id' => $customer->id,
            'order_number' => 'VA-GATE1',
            'total' => 1000,
            'country' => 'India',
        ]);
        $product = $this->attachItem($order, 'gate-archive');
        $holding = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-archive-hold-'.uniqid();
        $release = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-archive-release-'.uniqid();
        $entered = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-archive-entered-'.uniqid();
        $finished = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-archive-finished-'.uniqid();
        $gateway = null;
        $archive = null;

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
            $this->assertNull($order->fresh()->admin_archived_at);

            DB::disconnect('sqlite');
            $archive = $this->start([
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
                $this->assertTrue($archive->running());
                $this->reconnect();
                $fresh = DB::table('orders')->where('id', $order->id)->first();
                $this->assertNotNull($fresh);
                $this->assertNull($fresh->razorpay_order_id);
                $this->assertNull($fresh->admin_archived_at);
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
            $archiveResult = $this->decode($archive->wait());
            $this->reconnect();

            $this->assertSame(1, $gatewayResult['posts'] ?? null);
            $this->assertTrue($gatewayResult['ok'] ?? false);
            $this->assertSame(OrderTestArchive::ARCHIVED, $archiveResult['result'] ?? null);
            $stored = $order->fresh();
            $this->assertSame('order_gate_hold', $stored->razorpay_order_id);
            $this->assertNotNull($stored->admin_archived_at);
            $this->assertSame('pending', $stored->status);
            $this->assertNull($stored->payment_id);
            $this->assertSame(1, OrderItem::query()->where('order_id', $order->id)->count());
            $this->assertNotNull($customer->fresh());
            $this->assertNotNull($product->fresh());
            $this->assertSame(4, (int) $product->fresh()->stock);
            $encoded = json_encode($archiveResult, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('order_gate_hold', $encoded);
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
                'tests/Support/concurrent_order_archive_worker.php',
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
