<?php

namespace Tests\Feature;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StaffInvitationPendingEmailMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = 'database/migrations/2026_09_19_000001_add_pending_email_guard_to_staff_invitations_table.php';

    public function test_pending_email_guard_migration_rolls_back_without_dropping_invitations(): void
    {
        $this->assertTrue(Schema::hasColumn('staff_invitations', 'pending_email'));

        $this->artisan('migrate:rollback', [
            '--path' => self::PATH,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('staff_invitations'));
        $this->assertFalse(Schema::hasColumn('staff_invitations', 'pending_email'));

        $this->artisan('migrate', [
            '--path' => self::PATH,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasColumn('staff_invitations', 'pending_email'));
    }

    public function test_backfill_revokes_duplicate_live_invitations_and_preserves_history(): void
    {
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);

        $this->artisan('migrate:rollback', [
            '--path' => self::PATH,
            '--force' => true,
        ])->assertExitCode(0);

        $now = now();
        $oldToken = str_repeat('o', 64);
        $newToken = str_repeat('n', 64);
        $expiredToken = str_repeat('e', 64);
        $acceptedToken = str_repeat('a', 64);
        $revokedToken = str_repeat('r', 64);
        $historyToken = str_repeat('h', 64);

        DB::table('staff_invitations')->insert([
            $this->invitationRow($owner, 'History Null', 'history@example.com', $historyToken, $now->copy()->subDays(10), acceptedAt: $now->copy()->subDays(9)),
            $this->invitationRow($owner, 'Accepted', 'accepted@example.com', $acceptedToken, $now->copy()->addDay(), acceptedAt: $now->copy()->subHour()),
            $this->invitationRow($owner, 'Revoked', 'revoked@example.com', $revokedToken, $now->copy()->addDay(), revokedAt: $now->copy()->subHour()),
            $this->invitationRow($owner, 'Expired', 'expired@example.com', $expiredToken, $now->copy()->subMinute()),
            $this->invitationRow($owner, 'Older Duplicate', 'Staff@Example.com', $oldToken, $now->copy()->addDay(), createdAt: $now->copy()->subHour()),
            $this->invitationRow($owner, 'Case Sibling', 'STAFF@EXAMPLE.COM', str_repeat('c', 64), $now->copy()->addHours(12), createdAt: $now->copy()->subMinutes(30)),
            $this->invitationRow($owner, 'Newest Duplicate', 'staff@example.com', $newToken, $now->copy()->addDay(), createdAt: $now),
        ]);

        $this->artisan('migrate', [
            '--path' => self::PATH,
            '--force' => true,
        ])->assertExitCode(0);

        $newest = StaffInvitation::query()->where('name', 'Newest Duplicate')->firstOrFail();
        $older = StaffInvitation::query()->where('name', 'Older Duplicate')->firstOrFail();
        $caseSibling = StaffInvitation::query()->where('name', 'Case Sibling')->firstOrFail();
        $expired = StaffInvitation::query()->where('name', 'Expired')->firstOrFail();
        $accepted = StaffInvitation::query()->where('name', 'Accepted')->firstOrFail();
        $revoked = StaffInvitation::query()->where('name', 'Revoked')->firstOrFail();
        $history = StaffInvitation::query()->where('name', 'History Null')->firstOrFail();

        $this->assertSame('staff@example.com', $newest->pending_email);
        $this->assertTrue($newest->isPending());
        $this->assertNull($older->pending_email);
        $this->assertNotNull($older->revoked_at);
        $this->assertFalse($older->isPending());
        $this->assertNull($caseSibling->pending_email);
        $this->assertNotNull($caseSibling->revoked_at);
        $this->assertFalse($caseSibling->isPending());

        $this->assertNull($expired->pending_email);
        $this->assertNull($expired->revoked_at);
        $this->assertNull($expired->accepted_at);
        $this->assertNull($accepted->pending_email);
        $this->assertNotNull($accepted->accepted_at);
        $this->assertNull($revoked->pending_email);
        $this->assertNotNull($revoked->revoked_at);
        $this->assertNull($history->pending_email);
        $this->assertNotNull($history->accepted_at);

        $this->assertSame(1, StaffInvitation::query()->where('pending_email', 'staff@example.com')->count());
        $this->assertGreaterThanOrEqual(4, StaffInvitation::query()->whereNull('pending_email')->count());

        $this->get($this->signedUrl($older, $oldToken))->assertGone();
        $this->get($this->signedUrl($caseSibling, str_repeat('c', 64)))->assertGone();
        $this->get($this->signedUrl($newest, $newToken))->assertOk()->assertSee('Set up your account');
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationRow(
        User $owner,
        string $name,
        string $email,
        string $token,
        $expiresAt,
        $acceptedAt = null,
        $revokedAt = null,
        $createdAt = null,
    ): array {
        $timestamp = $createdAt ?? now();

        return [
            'name' => $name,
            'email' => $email,
            'admin_role' => AdminRole::VIEWER,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $owner->getKey(),
            'expires_at' => $expiresAt,
            'accepted_at' => $acceptedAt,
            'revoked_at' => $revokedAt,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function signedUrl(StaffInvitation $invitation, string $token): string
    {
        return URL::temporarySignedRoute(
            'admin.staff-invitations.accept',
            now()->addHour(),
            ['invitation' => $invitation, 'token' => $token],
        );
    }
}
