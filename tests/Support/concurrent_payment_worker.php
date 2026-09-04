<?php

declare(strict_types=1);

/**
 * CLI worker for multi-process payment lock tests.
 *
 * Usage:
 *   php tests/Support/concurrent_payment_worker.php razorpay <dbPath> <orderId> <counterFile>
 *   php tests/Support/concurrent_payment_worker.php checkout <dbPath> <userId> <productId> <counterFile>
 */

use App\Http\Controllers\CheckoutController;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPaymentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$action = $argv[1] ?? '';
$dbPath = $argv[2] ?? '';

if ($action === '' || $dbPath === '') {
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

configureSharedRuntime($dbPath);

try {
    match ($action) {
        'razorpay' => runRazorpayWorker(
            (int) ($argv[3] ?? 0),
            (string) ($argv[4] ?? ''),
        ),
        'checkout' => runCheckoutWorker(
            (int) ($argv[3] ?? 0),
            (int) ($argv[4] ?? 0),
            (string) ($argv[5] ?? ''),
        ),
        default => throw new InvalidArgumentException('Unknown worker action: '.$action),
    };
} catch (Throwable $throwable) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'message' => $throwable->getMessage(),
        'code' => (int) $throwable->getCode(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}

function configureSharedRuntime(string $dbPath): void
{
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $dbPath,
        'cache.default' => 'database',
        'cache.stores.database.connection' => 'sqlite',
        'cache.stores.database.table' => 'cache',
        'cache.stores.database.lock_table' => 'cache_locks',
        'services.razorpay.key' => 'rzp_test_key',
        'services.razorpay.secret' => 'rzp_test_secret',
        'mail.default' => 'array',
        'mail.from.address' => 'shop@example.com',
        'queue.default' => 'sync',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');
}

function fakeSlowRazorpayCreate(string $counterFile, string $orderId): void
{
    Http::fake([
        'api.razorpay.com/v1/orders' => function () use ($counterFile, $orderId) {
            if ($counterFile !== '') {
                $count = (int) @file_get_contents($counterFile);
                file_put_contents($counterFile, (string) ($count + 1), LOCK_EX);
            }

            usleep(250_000);

            return Http::response([
                'id' => $orderId,
                'amount' => 119900,
                'currency' => 'INR',
            ], 200);
        },
    ]);
}

function runRazorpayWorker(int $orderId, string $counterFile): void
{
    fakeSlowRazorpayCreate($counterFile, 'order_process_shared');

    $order = Order::query()->findOrFail($orderId);
    $razorpayOrderId = app(OrderPaymentService::class)->ensureRazorpayOrderId($order);

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'razorpay_order_id' => $razorpayOrderId,
    ], JSON_THROW_ON_ERROR));
}

function runCheckoutWorker(int $userId, int $productId, string $counterFile): void
{
    fakeSlowRazorpayCreate($counterFile, 'order_checkout_shared');

    $user = User::query()->findOrFail($userId);
    Auth::login($user);

    $session = app('session.store');
    $session->start();
    $session->put('cart', [
        $productId => [
            'quantity' => 1,
            'finish_slug' => null,
            'finish_name' => null,
        ],
    ]);

    $payload = [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'customer_email' => 'jane@example.com',
        'customer_phone' => '9876543210',
        'house_building' => '123 Test Building',
        'street' => 'Test Street',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'pincode' => '400001',
        'country' => 'India',
        'payment_method' => 'razorpay',
        'billing_same_as_shipping' => '1',
    ];

    $request = Request::create('/checkout', 'POST', $payload);
    $request->setLaravelSession($session);
    app()->instance('request', $request);

    $response = app(CheckoutController::class)->store($request);

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'status' => $response->getStatusCode(),
        'target' => $response->headers->get('Location'),
    ], JSON_THROW_ON_ERROR));
}
