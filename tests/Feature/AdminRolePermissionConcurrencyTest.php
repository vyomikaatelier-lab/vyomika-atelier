<?php

namespace Tests\Feature;

use App\Models\AdminRolePermissionAudit;
use App\Models\AdminRolePermissionOverride;
use App\Models\User;
use App\Support\AdminRole;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class AdminRolePermissionConcurrencyTest extends TestCase
{
    private string $sharedDbPath;

    protected function setUp(): void
    {
        $this->sharedDbPath = tempnam(sys_get_temp_dir(), 'vyomika_perm_lock_').'.sqlite';
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
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::statement('PRAGMA journal_mode=WAL');
        DB::statement('PRAGMA busy_timeout=15000');

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        if (isset($this->sharedDbPath) && is_file($this->sharedDbPath)) {
            @unlink($this->sharedDbPath);
        }

        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_DATABASE'] = ':memory:';
        putenv('DB_CONNECTION=sqlite');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_CONNECTION'] = 'sqlite';

        parent::tearDown();
    }

    public function test_concurrent_first_time_override_writes_do_not_return_500(): void
    {
        $firstOwner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $secondOwner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);

        DB::disconnect('sqlite');

        $first = $this->startWorker((string) $firstOwner->id);
        $second = $this->startWorker((string) $secondOwner->id);

        $firstResult = $this->decodeWorkerOutput($first->wait());
        $secondResult = $this->decodeWorkerOutput($second->wait());

        config(['database.connections.sqlite.database' => $this->sharedDbPath]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->assertTrue($firstResult['ok']);
        $this->assertTrue($secondResult['ok']);
        $this->assertNotSame(500, $firstResult['status'] ?? null);
        $this->assertNotSame(500, $secondResult['status'] ?? null);

        $overrides = AdminRolePermissionOverride::query()
            ->where('admin_role', AdminRole::VIEWER)
            ->where('permission', AdminRole::ORDERS_MANAGE)
            ->get();

        $this->assertCount(1, $overrides);
        $this->assertTrue((bool) $overrides->first()->enabled);
        $this->assertSame(1, AdminRolePermissionAudit::query()->count());
        $this->assertTrue(
            User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER])
                ->hasAdminPermission(AdminRole::ORDERS_MANAGE),
        );
    }

    private function startWorker(string $actorId): \Illuminate\Process\InvokedProcess
    {
        return Process::path(base_path())
            ->timeout(120)
            ->env([
                'APP_ENV' => 'testing',
                'APP_KEY' => (string) config('app.key'),
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->sharedDbPath,
            ])
            ->start([
                PHP_BINARY,
                'tests/Support/concurrent_permission_write_worker.php',
                $this->sharedDbPath,
                $actorId,
                AdminRole::VIEWER,
                AdminRole::ORDERS_MANAGE,
                '1',
            ]);
    }

    /** @return array<string, mixed> */
    private function decodeWorkerOutput(\Illuminate\Contracts\Process\ProcessResult $result): array
    {
        $this->assertTrue(
            $result->successful(),
            trim($result->errorOutput()."\n".$result->output())
        );

        return json_decode(trim($result->output()), true, 512, JSON_THROW_ON_ERROR);
    }
}
