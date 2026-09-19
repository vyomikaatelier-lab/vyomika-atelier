<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-backed uniqueness for one actionable pending invitation per email.
 *
 * Maintenance: this additive column is ignored by the previous application
 * release. Deploy the schema first, then the new invitation code. Rolling
 * the application back leaves the unique guard in place and does not change
 * stored token hashes, roles, or user access. Down() drops only this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
            $table->unique('pending_email', 'staff_inv_pending_email_uq');
        });

        $pending = DB::table('staff_invitations')
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->get(['id', 'email']);

        $claimed = [];

        foreach ($pending as $row) {
            $email = strtolower((string) $row->email);

            if ($email === '' || isset($claimed[$email])) {
                continue;
            }

            $claimed[$email] = true;

            DB::table('staff_invitations')
                ->where('id', $row->id)
                ->update(['pending_email' => $email]);
        }
    }

    public function down(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->dropUnique('staff_inv_pending_email_uq');
            $table->dropColumn('pending_email');
        });
    }
};
