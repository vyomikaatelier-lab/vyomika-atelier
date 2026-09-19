<?php

declare(strict_types=1);

/**
 * CLI worker for concurrent invitation regeneration.
 *
 * Usage:
 *   php tests/Support/concurrent_staff_regenerate_worker.php <dbPath> <actorId> <invitationId>
 */

use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\StaffManagementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

$dbPath = $argv[1] ?? '';
$actorId = (int) ($argv[2] ?? 0);
$invitationId = (int) ($argv[3] ?? 0);

if ($dbPath === '' || $actorId < 1 || $invitationId < 1) {
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
    'mail.default' => 'array',
    'queue.default' => 'sync',
]);

DB::purge('sqlite');
DB::reconnect('sqlite');
DB::statement('PRAGMA journal_mode=WAL');
DB::statement('PRAGMA busy_timeout=15000');
Mail::fake();

try {
    $actor = User::query()->findOrFail($actorId);
    $invitation = StaffInvitation::query()->findOrFail($invitationId);
    $result = app(StaffManagementService::class)->regenerateLink($actor, $invitation);

    fwrite(STDOUT, json_encode([
        'ok' => true,
        'regenerated' => true,
        'hash' => $result['invitation']->token_hash,
    ], JSON_THROW_ON_ERROR));
} catch (ValidationException $exception) {
    fwrite(STDOUT, json_encode([
        'ok' => true,
        'regenerated' => false,
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
