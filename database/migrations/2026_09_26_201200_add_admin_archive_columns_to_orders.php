<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visibility-only archive metadata for protected test orders.
 *
 * Existing order, payment, gateway, stock, reconciliation, refund, email,
 * and customer evidence is left unchanged. Down refuses to run while any
 * archive evidence remains. Re-running up is safe after a partial stop.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $this->addColumns();
        $this->addForeignKey();
        $this->addIndex();
    }

    public function down(): void
    {
        if ($this->archiveEvidenceExists()) {
            throw new RuntimeException(
                'Cannot roll back test-order archiving while an archived order exists.'
            );
        }

        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::hasIndex('orders', 'orders_admin_archived_at_idx')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_admin_archived_at_idx');
            });
        }

        if ($this->foreignKeyExists()) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['admin_archived_by_user_id']);
            });
        }

        $columns = array_values(array_filter(
            ['admin_archive_reason', 'admin_archived_by_user_id', 'admin_archived_at'],
            fn (string $column) => Schema::hasColumn('orders', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    private function addColumns(): void
    {
        if (! Schema::hasColumn('orders', 'admin_archived_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('admin_archived_at')->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'admin_archived_by_user_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('admin_archived_by_user_id')->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'admin_archive_reason')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('admin_archive_reason', 64)->nullable();
            });
        }
    }

    private function addForeignKey(): void
    {
        if (! Schema::hasColumn('orders', 'admin_archived_by_user_id') || $this->foreignKeyExists()) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('admin_archived_by_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    private function addIndex(): void
    {
        if (! Schema::hasColumn('orders', 'admin_archived_at') || Schema::hasIndex('orders', 'orders_admin_archived_at_idx')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->index('admin_archived_at', 'orders_admin_archived_at_idx');
        });
    }

    private function foreignKeyExists(): bool
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'admin_archived_by_user_id')) {
            return false;
        }

        foreach (Schema::getForeignKeys('orders') as $foreign) {
            $columns = $foreign['columns'] ?? [];

            if (in_array('admin_archived_by_user_id', $columns, true)) {
                return true;
            }
        }

        return false;
    }

    private function archiveEvidenceExists(): bool
    {
        if (! Schema::hasTable('orders')) {
            return false;
        }

        $query = DB::table('orders');
        $constrained = false;

        foreach (['admin_archived_at', 'admin_archived_by_user_id', 'admin_archive_reason'] as $column) {
            if (! Schema::hasColumn('orders', $column)) {
                continue;
            }

            $method = $constrained ? 'orWhereNotNull' : 'whereNotNull';
            $query->{$method}($column);
            $constrained = true;
        }

        return $constrained && $query->exists();
    }
};
