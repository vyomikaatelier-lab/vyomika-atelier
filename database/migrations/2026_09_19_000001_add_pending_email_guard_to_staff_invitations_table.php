<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-backed uniqueness for one actionable pending invitation per email.
 *
 * Nullable pending_email does NOT protect uniqueness while previous application
 * code is still live. Old writers leave pending_email NULL, and MySQL/MariaDB
 * allow multiple NULLs in a unique column. Do not go live across a mixed
 * schema/code window that still accepts invitation writes.
 *
 * Exact deployment rule:
 * 1. Put the application into maintenance mode.
 * 2. Back up the database.
 * 3. Run schema migrations.
 * 4. Deploy the new application code.
 * 5. Leave maintenance mode only after the new code is live.
 *
 * Do not permit invitation writes during the schema/code transition.
 * Rolling the application back while this unique column remains is only safe
 * if invitation writes stay blocked or the new writers stay deployed.
 * Down() drops only this column and unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
            $table->unique('pending_email', 'staff_inv_pending_email_uq');
        });

        DB::transaction(function (): void {
            $pending = DB::table('staff_invitations')
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get(['id', 'email']);

            $claimed = [];

            foreach ($pending as $row) {
                $email = strtolower(trim((string) $row->email));

                if ($email === '') {
                    continue;
                }

                if (isset($claimed[$email])) {
                    DB::table('staff_invitations')
                        ->where('id', $row->id)
                        ->update([
                            'revoked_at' => now(),
                            'pending_email' => null,
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                $claimed[$email] = true;

                DB::table('staff_invitations')
                    ->where('id', $row->id)
                    ->update(['pending_email' => $email]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->dropUnique('staff_inv_pending_email_uq');
            $table->dropColumn('pending_email');
        });
    }
};
