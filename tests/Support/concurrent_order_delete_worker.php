<?php

declare(strict_types=1);

/**
 * CLI worker for multi-process inert-order deletion tests.
 *
 * Usage:
 *   php tests/Support/concurrent_order_delete_worker.php <dbPath> <json-payload>
 */

use App\Models\User;
use App\Services\OrderTestDeletion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
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
    'mail.default' => 'array',
    'queue.default' => 'sync',
]);

DB::purge('sqlite');
DB::reconnect('sqlite');
Http::preventStrayRequests();

try {
    $actor = User::query()->findOrFail((int) $payload['user_id']);

    if (is_string($payload['entered_file'] ?? null) && $payload['entered_file'] !== '') {
        file_put_contents($payload['entered_file'], '1');
    }

    $attempts = 0;

    do {
        try {
            $result = app(OrderTestDeletion::class)->delete(
                $actor,
                (int) $payload['order_id'],
                (string) $payload['order_number'],
                (string) $payload['password'],
            );
            break;
        } catch (QueryException $exception) {
            $attempts++;

            if ($attempts >= 5 || ! str_contains($exception->getMessage(), 'database is locked')) {
                throw $exception;
            }

            usleep(50000 * $attempts);
        }
    } while (true);

    if (is_string($payload['finished_file'] ?? null) && $payload['finished_file'] !== '') {
        file_put_contents($payload['finished_file'], (string) $result);
    }

    $orderExists = DB::table('orders')->where('id', (int) $payload['order_id'])->exists();
    $itemCount = DB::table('order_items')->where('order_id', (int) $payload['order_id'])->count();

    fwrite(STDOUT, json_encode([
        'result' => $result,
        'order_exists' => $orderExists,
        'item_count' => $itemCount,
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $throwable) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'message' => $throwable->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
