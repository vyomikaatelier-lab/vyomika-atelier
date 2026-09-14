<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_id', 20)->nullable()->unique()->after('is_active');
            $table->string('admin_role', 40)->nullable()->index()->after('staff_id');
            $table->unsignedInteger('admin_session_version')->default(1)->after('admin_role');
            $table->timestamp('admin_last_login_at')->nullable()->after('admin_session_version');
            $table->string('admin_last_login_ip', 45)->nullable()->after('admin_last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['staff_id']);
            $table->dropIndex(['admin_role']);
            $table->dropColumn([
                'staff_id',
                'admin_role',
                'admin_session_version',
                'admin_last_login_at',
                'admin_last_login_ip',
            ]);
        });
    }
};
