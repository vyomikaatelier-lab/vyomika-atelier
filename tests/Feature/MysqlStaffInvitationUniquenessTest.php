<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Tests\Support\MysqlInvitationHarnessGuard;
use Tests\TestCase;

/**
 * Opt-in MySQL/MariaDB uniqueness proof. Default phpunit does not run this
 * against any database. SQLite tests are not production uniqueness proof.
 *
 * Never point these variables at production. Never use DB_* credentials.
 *
 *   MYSQL_INVITATION_TEST=1
 *   MYSQL_TEST_HOST=127.0.0.1
 *   MYSQL_TEST_PORT=3306
 *   MYSQL_TEST_DATABASE=vyomika_invitation_test
 *   MYSQL_TEST_USERNAME=local_test_user
 *   MYSQL_TEST_PASSWORD=local_test_password
 */
class MysqlStaffInvitationUniquenessTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_09_19_000001_add_pending_email_guard_to_staff_invitations_table.php';

    protected function setUp(): void
    {
        $flag = getenv('MYSQL_INVITATION_TEST');
        if ($flag === false) {
            $flag = $_ENV['MYSQL_INVITATION_TEST'] ?? null;
        }

        if (! MysqlInvitationHarnessGuard::isEnabled($flag === false ? null : (string) $flag)) {
            $this->markTestSkipped('MySQL/MariaDB invitation uniqueness proof is opt-in and was NOT EXECUTED.');
        }

        parent::setUp();

        $reasons = MysqlInvitationHarnessGuard::refusalReasons(
            $this->optionalEnv('MYSQL_TEST_HOST') ?? '127.0.0.1',
            $this->optionalEnv('MYSQL_TEST_DATABASE'),
            (string) config('database.connections.mysql.database'),
        );

        if ($reasons !== []) {
            $this->fail('Refusing to run MySQL uniqueness proof: '.implode('; ', $reasons));
        }
    }

    public function test_mysql_pending_email_uniqueness_and_migration_round_trip(): void
    {
        $this->configureMysql();
        $this->createHarnessSchema();

        $now = now();
        $olderToken = str_repeat('o', 64);
        $newerToken = str_repeat('n', 64);

        DB::connection('mysql_invitation_test')->table('staff_invitations')->insert([
            $this->row(1, 'Accepted', 'accepted@example.com', $now->copy()->addDay(), acceptedAt: $now),
            $this->row(2, 'Revoked', 'revoked@example.com', $now->copy()->addDay(), revokedAt: $now),
            $this->row(3, 'Expired', 'expired@example.com', $now->copy()->subMinute()),
            $this->row(4, 'Older Duplicate', 'Staff@Example.com', $now->copy()->addDay(), createdAt: $now->copy()->subHour(), token: $olderToken),
            $this->row(5, 'Newest Duplicate', 'staff@example.com', $now->copy()->addDay(), createdAt: $now, token: $newerToken),
        ]);

        $this->artisan('migrate', [
            '--database' => 'mysql_invitation_test',
            '--path' => self::MIGRATION,
            '--force' => true,
        ])->assertExitCode(0);

        $connection = DB::connection('mysql_invitation_test');

        $this->assertTrue(Schema::connection('mysql_invitation_test')->hasColumn('staff_invitations', 'pending_email'));

        $newest = $connection->table('staff_invitations')->where('id', 5)->first();
        $older = $connection->table('staff_invitations')->where('id', 4)->first();
        $this->assertSame('staff@example.com', $newest->pending_email);
        $this->assertNull($older->pending_email);
        $this->assertNotNull($older->revoked_at);
        $this->assertNull($newest->revoked_at);

        $this->assertSame(4, $connection->table('staff_invitations')->whereNull('pending_email')->count());

        $connection->table('staff_invitations')->insert([
            $this->row(6, 'History One', 'history-one@example.com', $now->copy()->subDay(), acceptedAt: $now->copy()->subHour()),
            $this->row(7, 'History Two', 'history-two@example.com', $now->copy()->subDay(), acceptedAt: $now->copy()->subHour()),
        ]);

        $this->assertSame(6, $connection->table('staff_invitations')->whereNull('pending_email')->count());

        $connection->table('staff_invitations')->insert([
            array_merge($this->row(8, 'Live', 'live@example.com', $now->copy()->addDay()), ['pending_email' => 'live@example.com']),
        ]);

        try {
            $connection->table('staff_invitations')->insert([
                array_merge($this->row(9, 'Live Duplicate', 'live@example.com', $now->copy()->addDay()), ['pending_email' => 'live@example.com']),
            ]);
            $this->fail('Normalized duplicate pending_email values must be rejected.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', (string) ($exception->errorInfo[0] ?? ''));
            $this->assertSame(1062, (int) ($exception->errorInfo[1] ?? 0));
            $this->assertStringContainsString('staff_inv_pending_email_uq', strtolower($exception->getMessage()));
        }

        $connection->table('staff_invitations')->where('id', 8)->update(['pending_email' => null, 'accepted_at' => $now]);
        $connection->table('staff_invitations')->insert([
            array_merge($this->row(10, 'Replacement After Accept', 'live@example.com', $now->copy()->addDay()), ['pending_email' => 'live@example.com']),
        ]);

        $connection->table('staff_invitations')->where('id', 10)->update(['pending_email' => null, 'revoked_at' => $now]);
        $connection->table('staff_invitations')->insert([
            array_merge($this->row(11, 'Replacement After Revoke', 'live@example.com', $now->copy()->addDay()), ['pending_email' => 'live@example.com']),
        ]);

        $connection->table('staff_invitations')->where('id', 11)->update(['pending_email' => null, 'expires_at' => $now->copy()->subMinute()]);
        $connection->table('staff_invitations')->insert([
            array_merge($this->row(12, 'Replacement After Expire', 'live@example.com', $now->copy()->addDay()), ['pending_email' => 'live@example.com']),
        ]);

        $this->assertSame(1, $connection->table('staff_invitations')->where('pending_email', 'live@example.com')->count());
        $this->assertSame(1, $connection->table('staff_invitations')->where('pending_email', 'staff@example.com')->count());

        $this->artisan('migrate:rollback', [
            '--database' => 'mysql_invitation_test',
            '--path' => self::MIGRATION,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertFalse(Schema::connection('mysql_invitation_test')->hasColumn('staff_invitations', 'pending_email'));
        $this->assertSame(11, $connection->table('staff_invitations')->count());

        $this->artisan('migrate', [
            '--database' => 'mysql_invitation_test',
            '--path' => self::MIGRATION,
            '--force' => true,
        ])->assertExitCode(0);

        Schema::connection('mysql_invitation_test')->dropIfExists('staff_invitations');
        Schema::connection('mysql_invitation_test')->dropIfExists('users');
        Schema::connection('mysql_invitation_test')->dropIfExists('migrations');
    }

    private function configureMysql(): void
    {
        config([
            'database.connections.mysql_invitation_test' => [
                'driver' => 'mysql',
                'host' => $this->optionalEnv('MYSQL_TEST_HOST') ?? '127.0.0.1',
                'port' => $this->optionalEnv('MYSQL_TEST_PORT') ?? '3306',
                'database' => $this->requiredEnv('MYSQL_TEST_DATABASE'),
                'username' => $this->requiredEnv('MYSQL_TEST_USERNAME'),
                'password' => $this->optionalEnv('MYSQL_TEST_PASSWORD') ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],
        ]);

        try {
            DB::connection('mysql_invitation_test')->getPdo();
        } catch (PDOException $exception) {
            $this->markTestSkipped('Local MySQL/MariaDB was not reachable. Uniqueness proof was NOT EXECUTED.');
        }
    }

    private function createHarnessSchema(): void
    {
        Schema::connection('mysql_invitation_test')->dropIfExists('staff_invitations');
        Schema::connection('mysql_invitation_test')->dropIfExists('users');
        Schema::connection('mysql_invitation_test')->dropIfExists('migrations');

        Schema::connection('mysql_invitation_test')->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::connection('mysql_invitation_test')->create('staff_invitations', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('admin_role', 40);
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('invited_by');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        DB::connection('mysql_invitation_test')->table('users')->insert([
            'id' => 1,
            'name' => 'Harness Owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::connection('mysql_invitation_test')->create('migrations', function ($table): void {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
    }

    /** @return array<string, mixed> */
    private function row(
        int $id,
        string $name,
        string $email,
        $expiresAt,
        $acceptedAt = null,
        $revokedAt = null,
        $createdAt = null,
        ?string $token = null,
    ): array {
        $timestamp = $createdAt ?? now();

        return [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'admin_role' => 'viewer',
            'token_hash' => hash('sha256', $token ?? str_pad((string) $id, 64, 'x')),
            'invited_by' => 1,
            'expires_at' => $expiresAt,
            'accepted_at' => $acceptedAt,
            'revoked_at' => $revokedAt,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function requiredEnv(string $key): string
    {
        $value = $this->optionalEnv($key);

        if ($value === null) {
            $this->fail($key.' must be provided explicitly for the MySQL invitation harness.');
        }

        return $value;
    }

    private function optionalEnv(string $key): ?string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
