<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sparse role-permission overrides plus an immutable audit log.
 *
 * Previous application code ignores these tables and keeps using AdminRole
 * code defaults. That mixed window is only safe while invitation and
 * permission writes are blocked.
 *
 * Exact deployment rule: maintenance mode, database backup, schema
 * migrations, new application code, then leave maintenance mode. Do not
 * serve live writes between schema and code. Down() drops only these tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_role_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('admin_role', 40);
            $table->string('permission', 80);
            $table->boolean('enabled');
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['admin_role', 'permission'], 'admin_rpo_role_perm_uq');
            $table->index('admin_role', 'admin_rpo_role_idx');
        });

        Schema::create('admin_role_permission_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_user_id');
            $table->string('actor_staff_id', 20)->nullable();
            $table->string('admin_role', 40);
            $table->string('permission', 80);
            $table->boolean('previous_enabled');
            $table->boolean('new_enabled');
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_user_id', 'admin_rpa_actor_idx');
            $table->index(['admin_role', 'permission'], 'admin_rpa_role_perm_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_permission_audits');
        Schema::dropIfExists('admin_role_permission_overrides');
    }
};
