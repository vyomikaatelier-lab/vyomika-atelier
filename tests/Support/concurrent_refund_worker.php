<?php

declare(strict_types=1);

/**
 * CLI worker for multi-process refund lock tests.
 *
 * Usage:
 *   php tests/Support/concurrent_refund_worker.php <dbPath> <json-payload>
 */

use App\Models\Order;
use App\Models\User;
use App\Services\OrderPaymentService;
use App\Services\OrderRefundService;
use App\Services\PendingOrderExpiry;
use App\Services\RefundIntent;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$action = $argv[1] ?? '';
$dbPath = $argv[2] ?? '';
$payload = json_decode((string) ($argv[3] ?? ''), true);

if ($action === '' || $dbPath === '' || ! is_array($payload)) {
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
    'cache.default' => 'database',
    'cache.stores.database.connection' => 'sqlite',
    'cache.stores.database.table' => 'cache',
    'cache.stores.database.lock_table' => 'cache_locks',
    'services.razorpay.key' => 'rzp_test_key',
    'services.razorpay.secret' => 'rzp_test_secret',
    'services.razorpay.webhook_secret' => 'whsec_test',
    'checkout.payments_enabled' => false,
    'mail.default' => 'array',
    'mail.from.address' => 'shop@example.com',
    'queue.default' => 'sync',
]);

DB::purge('sqlite');
DB::reconnect('sqlite');
DB::statement('PRAGMA journal_mode = WAL');
DB::statement('PRAGMA busy_timeout = 8000');

try {
    $result = match ($action) {
        'refund' => refundWorker($payload),
        'expire' => expireWorker($payload),
        'settle' => settleWorker($payload),
        default => throw new InvalidArgumentException('Unknown worker action.'),
    };
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR));
} catch (Throwable $throwable) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'message' => $throwable->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function refundWorker(array $payload): array
{
    $order = Order::query()->findOrFail((int) $payload['order_id']);
    $actor = User::query()->findOrFail((int) $payload['user_id']);
    $posts = 0;

    Http::fake(function (Request $request) use ($order, &$posts) {
        if (str_ends_with($request->url(), '/refund')) {
            $posts++;
            $body = json_decode($request->body(), true);

            return Http::response([
                'id' => 'rfnd_'.substr(hash('sha256', (string) ($body['receipt'] ?? 'x')), 0, 12),
                'payment_id' => (string) $order->payment_id,
                'amount' => (int) ($body['amount'] ?? 0),
                'currency' => 'INR',
                'status' => 'processed',
                'receipt' => (string) ($body['receipt'] ?? ''),
                'notes' => is_array($body['notes'] ?? null) ? $body['notes'] : [],
            ], 200);
        }

        return Http::response([
            'id' => (string) $order->payment_id,
            'order_id' => (string) $order->razorpay_order_id,
            'amount' => payloadAmount($order),
            'currency' => 'INR',
            'status' => 'captured',
        ], 200);
    });

    $intent = new RefundIntent(
        (string) $payload['key'],
        (string) $payload['kind'],
        'customer_request',
        null,
        $payload['confirmation'] ?? null,
        (bool) ($payload['include_shipping'] ?? false),
        (array) ($payload['lines'] ?? []),
    );

    $refund = app(OrderRefundService::class)->start((int) $order->id, $actor, $intent);

    return [
        'ok' => true,
        'status' => $refund->status,
        'posts' => $posts,
        'refund_id' => $refund->id,
        'amount' => (int) $refund->amount_paise,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function expireWorker(array $payload): array
{
    $order = Order::query()->findOrFail((int) $payload['order_id']);

    return [
        'ok' => true,
        'expired' => PendingOrderExpiry::expireIfStillPending($order),
    ];
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function settleWorker(array $payload): array
{
    $order = Order::query()->findOrFail((int) $payload['order_id']);
    $paymentId = (string) $payload['payment_id'];

    Http::fake([
        'api.razorpay.com/v1/payments/*' => Http::response([
            'id' => $paymentId,
            'order_id' => (string) $order->razorpay_order_id,
            'amount' => (int) payloadAmount($order),
            'currency' => 'INR',
            'status' => 'captured',
        ], 200),
    ]);

    $result = app(OrderPaymentService::class)->completeFromGateway(
        $order,
        $paymentId,
        (string) $order->razorpay_order_id,
    );

    return [
        'ok' => true,
        'result' => $result,
    ];
}

function payloadAmount(Order $order): int
{
    return (int) round(((float) $order->total) * 100);
}
