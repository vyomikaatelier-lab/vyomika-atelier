<?php

namespace Tests\Feature;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\AdminRole;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class StaffInvitationConcurrencyTest extends TestCase
{
    private string $sharedDbPath;

    protected function setUp(): void
    {
        $this->sharedDbPath = tempnam(sys_get_temp_dir(), 'vyomika_invite_lock_').'.sqlite';
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

    public function test_concurrent_owners_cannot_create_two_actionable_invitations(): void
    {
        $firstOwner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $secondOwner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $email = 'shared-staff@example.com';

        DB::disconnect('sqlite');

        $first = $this->startWorker((string) $firstOwner->id, $email);
        $second = $this->startWorker((string) $secondOwner->id, $email);

        $firstResult = $this->decodeWorkerOutput($first->wait());
        $secondResult = $this->decodeWorkerOutput($second->wait());

        config(['database.connections.sqlite.database' => $this->sharedDbPath]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->assertTrue($firstResult['ok']);
        $this->assertTrue($secondResult['ok']);
        $this->assertTrue($firstResult['created'] xor $secondResult['created']);

        $pending = StaffInvitation::query()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->get();

        $this->assertCount(1, $pending);
        $this->assertSame($email, $pending->first()->pending_email);
        $this->assertSame(1, StaffInvitation::query()->whereNotNull('pending_email')->where('pending_email', $email)->count());
    }

    public function test_concurrent_regeneration_leaves_only_one_valid_token(): void
    {
        $firstOwner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $secondOwner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $invitation = StaffInvitation::query()->create([
            'name' => 'Pending Staff',
            'email' => 'regen@example.com',
            'pending_email' => 'regen@example.com',
            'admin_role' => AdminRole::VIEWER,
            'token_hash' => hash('sha256', str_repeat('z', 64)),
            'invited_by' => $firstOwner->getKey(),
            'expires_at' => now()->addDay(),
        ]);
        $originalHash = $invitation->token_hash;

        DB::disconnect('sqlite');

        $first = $this->startRegenerateWorker((string) $firstOwner->id, (string) $invitation->id);
        $second = $this->startRegenerateWorker((string) $secondOwner->id, (string) $invitation->id);

        $firstResult = $this->decodeWorkerOutput($first->wait());
        $secondResult = $this->decodeWorkerOutput($second->wait());

        config(['database.connections.sqlite.database' => $this->sharedDbPath]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->assertTrue($firstResult['ok']);
        $this->assertTrue($secondResult['ok']);
        $this->assertTrue(($firstResult['regenerated'] ?? false) || ($secondResult['regenerated'] ?? false));

        $fresh = $invitation->fresh();
        $this->assertNotSame($originalHash, $fresh->token_hash);
        $this->assertTrue($fresh->isPending());
        $hashes = array_values(array_filter([
            $firstResult['hash'] ?? null,
            $secondResult['hash'] ?? null,
        ]));
        $this->assertContains($fresh->token_hash, $hashes);
        $this->assertSame(1, StaffInvitation::query()->whereKey($invitation->getKey())->count());
        $this->assertSame(1, StaffInvitation::query()->where('pending_email', 'regen@example.com')->count());
    }

    private function startWorker(string $actorId, string $email): \Illuminate\Process\InvokedProcess
    {
        return Process::path(base_path())
            ->timeout(120)
            ->env([
                'APP_ENV' => 'testing',
                'APP_KEY' => (string) config('app.key'),
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->sharedDbPath,
                'MAIL_MAILER' => 'array',
            ])
            ->start([PHP_BINARY, 'tests/Support/concurrent_staff_invite_worker.php', $this->sharedDbPath, $actorId, $email]);
    }

    private function startRegenerateWorker(string $actorId, string $invitationId): \Illuminate\Process\InvokedProcess
    {
        return Process::path(base_path())
            ->timeout(120)
            ->env([
                'APP_ENV' => 'testing',
                'APP_KEY' => (string) config('app.key'),
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->sharedDbPath,
                'MAIL_MAILER' => 'array',
            ])
            ->start([
                PHP_BINARY,
                'tests/Support/concurrent_staff_regenerate_worker.php',
                $this->sharedDbPath,
                $actorId,
                $invitationId,
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
