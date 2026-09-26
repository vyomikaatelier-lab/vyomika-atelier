<?php

declare(strict_types=1);

/**
 * Pauses inside faked Razorpay order creation while holding the order cache lock.
 *
 * Usage:
 *   php tests/Support/concurrent_gateway_order_worker.php <dbPath> <json-payload>
 */

use App\Models\Order;
use App\Services\OrderPaymentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$dbPath = $argv[1] ?? '';
$payload = json_decode((string) ($argv[2] ?? ''), true);

if ($dbPath === '' || ! is_array($payload)) {
    fwrite(STDERR, "Missing worker arguments.\n");
    exit(2);
}

putenv('APP_ENV=testing');
putenv('APP_KEY=base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/r9BpzVLDGF7WA=');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$dbPath);
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $dbPath;
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = $dbPath;

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $dbPath,
    'database.connections.sqlite.busy_timeout' => 15000,
    'database.connections.sqlite.journal_mode' => 'wal',
    'cache.default' => 'database',
    'cache.stores.database.connection' => 'sqlite',
    'cache.stores.database.lock_connection' => 'sqlite',
    'checkout.payments_enabled' => false,
    'services.razorpay.key' => 'rzp_test_key',
    'services.razorpay.secret' => 'rzp_test_secret',
    'mail.default' => 'array',
    'queue.default' => 'sync',
]);

DB::purge('sqlite');
DB::reconnect('sqlite');
DB::statement('PRAGMA journal_mode = WAL');
DB::statement('PRAGMA busy_timeout = 8000');
Http::preventStrayRequests();

$holding = (string) ($payload['holding_file'] ?? '');
$release = (string) ($payload['release_file'] ?? '');
$posts = 0;

try {
    Http::fake(function (Request $request) use (&$posts, $holding, $release) {
        $posts++;

        if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/orders')) {
            throw new RuntimeException('Unexpected outbound request.');
        }

        if ($holding !== '') {
            file_put_contents($holding, '1');
        }

        $deadline = microtime(true) + 25;

        while ($release === '' || ! is_file($release)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Gateway pause was not released.');
            }

            usleep(20000);
        }

        return Http::response([
            'id' => 'order_gate_hold',
            'amount' => 100000,
            'currency' => 'INR',
        ], 200);
    });

    $order = Order::query()->findOrFail((int) $payload['order_id']);
    app(OrderPaymentService::class)->ensureRazorpayOrderId($order);

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'posts' => $posts,
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $throwable) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'posts' => $posts,
        'message' => 'gateway worker failed',
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
