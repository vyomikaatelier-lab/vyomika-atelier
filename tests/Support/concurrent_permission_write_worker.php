<?php

declare(strict_types=1);

/**
 * CLI worker for concurrent first-time role-permission writes.
 *
 * Usage:
 *   php tests/Support/concurrent_permission_write_worker.php <dbPath> <actorId> <role> <permission> <enabled>
 */

use App\Models\User;
use App\Services\AdminRolePermissionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$dbPath = $argv[1] ?? '';
$actorId = (int) ($argv[2] ?? 0);
$role = (string) ($argv[3] ?? '');
$permission = (string) ($argv[4] ?? '');
$enabled = (string) ($argv[5] ?? '1');

if ($dbPath === '' || $actorId < 1 || $role === '' || $permission === '') {
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
]);

DB::purge('sqlite');
DB::reconnect('sqlite');
DB::statement('PRAGMA journal_mode=WAL');
DB::statement('PRAGMA busy_timeout=15000');

try {
    $actor = User::query()->findOrFail($actorId);
    $applied = app(AdminRolePermissionService::class)->update($actor, [
        $role => [
            $permission => $enabled,
        ],
    ]);

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'applied' => $applied,
        'status' => 200,
    ], JSON_THROW_ON_ERROR));
} catch (ValidationException $exception) {
    fwrite(STDOUT, json_encode([
        'ok' => true,
        'applied' => 0,
        'status' => 422,
        'errors' => $exception->errors(),
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $throwable) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'status' => (int) $throwable->getCode(),
        'class' => $throwable::class,
        'message' => $throwable->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
