<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Support\IndiaDelivery;
use App\Support\PaymentAtomicLock;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class StaleShippingLockRaceTest extends TestCase
{
    private string $sharedDbPath;

    protected function setUp(): void
    {
        $this->sharedDbPath = tempnam(sys_get_temp_dir(), 'vyomika_shipping_race_').'.sqlite';
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
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
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

    public function test_cancellation_waits_for_gateway_creation_and_does_not_cancel(): void
    {
        $order = $this->order([
            'country' => 'India',
            'shipping_cost' => 0,
            'total' => 1000,
            'razorpay_order_id' => null,
        ]);
        $holding = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-ship-hold-'.uniqid();
        $release = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-ship-release-'.uniqid();
        $started = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-ship-started-'.uniqid();
        $gateway = null;
        $guard = null;

        try {
            DB::disconnect('sqlite');
            $gateway = $this->startGateway([
                'order_id' => $order->id,
                'holding_file' => $holding,
                'release_file' => $release,
            ]);
            $this->waitForFile($holding);

            $guard = $this->startGuard([
                'order_id' => $order->id,
                'started_file' => $started,
            ]);
            $this->waitForFile($started);
            $this->reconnect();
            $this->assertNull($order->fresh()->razorpay_order_id);
            $this->assertSame('pending', $order->fresh()->status);

            file_put_contents($release, '1');
            $gatewayResult = $this->decode($gateway->wait());
            $guardResult = $this->decode($guard->wait());
            $this->reconnect();

            $fresh = $order->fresh();
            $this->assertTrue($gatewayResult['ok'] ?? false);
            $this->assertSame(1, $gatewayResult['posts'] ?? null);
            $this->assertFalse($guardResult['blocked'] ?? true);
            $this->assertSame('pending', $fresh->status);
            $this->assertSame('order_gate_hold', $fresh->razorpay_order_id);
            $this->assertEquals(0, (float) $fresh->shipping_cost);
            $this->assertEquals(1000, (float) $fresh->total);
            Http::assertNothingSent();
        } finally {
            if (! is_file($release)) {
                file_put_contents($release, '1');
            }

            foreach ([$holding, $release, $started] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    public function test_cancellation_waits_for_settlement_and_preserves_financial_evidence(): void
    {
        $order = $this->order([
            'country' => 'India',
            'shipping_cost' => 199,
            'subtotal' => 1000,
            'total' => 1199,
            'razorpay_order_id' => 'order_stale_settled',
        ]);
        $started = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vyomika-settle-started-'.uniqid();
        $guard = null;
        $lock = PaymentAtomicLock::forRazorpayOrder((int) $order->id);
        $this->assertTrue($lock->get());

        try {
            $guard = $this->startGuard([
                'order_id' => $order->id,
                'started_file' => $started,
            ]);
            $this->waitForFile($started);
            usleep(300000);

            DB::table('orders')->where('id', $order->id)->update([
                'status' => 'paid',
                'payment_id' => 'pay_settled_race',
                'stock_deducted_at' => now(),
            ]);
            $lock->release();

            $guardResult = $this->decode($guard->wait());
            $fresh = $order->fresh();

            $this->assertTrue($guardResult['blocked'] ?? false);
            $this->assertSame(IndiaDelivery::STALE_SHIPPING_SUPPORT, $guardResult['message'] ?? null);
            $this->assertSame('paid', $fresh->status);
            $this->assertSame('pay_settled_race', $fresh->payment_id);
            $this->assertSame('order_stale_settled', $fresh->razorpay_order_id);
            $this->assertEquals(199, (float) $fresh->shipping_cost);
            $this->assertEquals(1199, (float) $fresh->total);
            $this->assertNotNull($fresh->stock_deducted_at);
            Http::assertNothingSent();
        } finally {
            $lock->release();

            if (is_file($started)) {
                @unlink($started);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'VA-'.strtoupper(substr(uniqid(), -8)),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'country' => 'India',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'total' => 1000,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'payment_id' => null,
            'razorpay_order_id' => null,
            'stock_deducted_at' => null,
            'expires_at' => now()->addDay(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function startGateway(array $payload): InvokedProcess
    {
        return $this->start('tests/Support/concurrent_gateway_order_worker.php', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function startGuard(array $payload): InvokedProcess
    {
        return $this->start('tests/Support/concurrent_initiation_guard_worker.php', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function start(string $script, array $payload): InvokedProcess
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
                $script,
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
}
