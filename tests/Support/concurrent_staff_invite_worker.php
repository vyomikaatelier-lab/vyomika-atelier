<?php

declare(strict_types=1);

/**
 * CLI worker for concurrent staff invitation uniqueness tests.
 *
 * Usage:
 *   php tests/Support/concurrent_staff_invite_worker.php <dbPath> <actorId> <email>
 */

use App\Models\User;
use App\Services\StaffManagementService;
use App\Support\AdminRole;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

$dbPath = $argv[1] ?? '';
$actorId = (int) ($argv[2] ?? 0);
$email = (string) ($argv[3] ?? '');

if ($dbPath === '' || $actorId < 1 || $email === '') {
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
    'mail.default' => 'array',
    'queue.default' => 'sync',
]);

DB::purge('sqlite');
DB::reconnect('sqlite');
DB::statement('PRAGMA journal_mode=WAL');
DB::statement('PRAGMA busy_timeout=5000');
Mail::fake();

try {
    $actor = User::query()->findOrFail($actorId);
    $result = app(StaffManagementService::class)->invite(
        $actor,
        'Concurrent Staff',
        $email,
        AdminRole::VIEWER,
    );

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'created' => true,
        'invitation_id' => $result['invitation']->getKey(),
    ], JSON_THROW_ON_ERROR));
} catch (ValidationException $exception) {
    fwrite(STDOUT, json_encode([
        'ok' => true,
        'created' => false,
        'errors' => $exception->errors(),
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $throwable) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'message' => $throwable->getMessage(),
        'code' => (int) $throwable->getCode(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
