<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffInvitationPendingEmailMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_email_guard_migration_rolls_back_without_dropping_invitations(): void
    {
        $path = 'database/migrations/2026_09_19_000001_add_pending_email_guard_to_staff_invitations_table.php';

        $this->assertTrue(Schema::hasColumn('staff_invitations', 'pending_email'));

        $this->artisan('migrate:rollback', [
            '--path' => $path,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('staff_invitations'));
        $this->assertFalse(Schema::hasColumn('staff_invitations', 'pending_email'));

        $this->artisan('migrate', [
            '--path' => $path,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasColumn('staff_invitations', 'pending_email'));
    }
}
