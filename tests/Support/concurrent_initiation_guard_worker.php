<?php

declare(strict_types=1);

/**
 * Calls payment initiation while another process may hold the order lock.
 *
 * Usage:
 *   php tests/Support/concurrent_initiation_guard_worker.php <dbPath> <json-payload>
 */

use App\Models\Order;
use App\Services\OrderPaymentService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Console\Kernel;
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
    'checkout.payments_enabled' => true,
    'checkout.payments_unrestricted' => true,
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

$started = (string) ($payload['started_file'] ?? '');

if ($started !== '') {
    file_put_contents($started, '1');
}

try {
    $order = Order::query()->findOrFail((int) $payload['order_id']);
    app(OrderPaymentService::class)->assertInitiationAllowed($order);

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'blocked' => false,
    ], JSON_THROW_ON_ERROR));
} catch (LockTimeoutException) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'blocked' => false,
        'message' => 'lock timeout',
    ], JSON_THROW_ON_ERROR));
    exit(1);
} catch (RuntimeException $exception) {
    fwrite(STDOUT, json_encode([
        'ok' => true,
        'blocked' => true,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $throwable) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'blocked' => false,
        'message' => 'guard worker failed',
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
