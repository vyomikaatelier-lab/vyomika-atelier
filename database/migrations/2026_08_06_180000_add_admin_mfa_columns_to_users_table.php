<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
            if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }
            if (! Schema::hasColumn('users', 'two_factor_grace_ends_at')) {
                $table->timestamp('two_factor_grace_ends_at')->nullable()->after('two_factor_confirmed_at');
            }
        });

        $graceDays = (int) (env('ADMIN_MFA_GRACE_DAYS', 7));
        $graceEnds = now()->addDays(max(0, $graceDays));

        if (Schema::hasColumn('users', 'two_factor_grace_ends_at')) {
            DB::table('users')
                ->where('is_admin', true)
                ->whereNull('two_factor_confirmed_at')
                ->whereNull('two_factor_grace_ends_at')
                ->update(['two_factor_grace_ends_at' => $graceEnds]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_grace_ends_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
