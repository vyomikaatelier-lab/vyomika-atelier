<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an explicit captured-but-unfulfilled state.
 *
 * Existing rows keep their status. Paid orders are not rewritten.
 * Down refuses to run while any reconciliation evidence remains,
 * including duplicate-capture data stored on a paid order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'reconciliation_reason')) {
                $table->string('reconciliation_reason', 64)->nullable()->after('payment_id');
            }

            if (! Schema::hasColumn('orders', 'reconciliation_meta')) {
                $table->json('reconciliation_meta')->nullable()->after(
                    Schema::hasColumn('orders', 'reconciliation_reason') ? 'reconciliation_reason' : 'payment_id'
                );
            }
        });

        $this->syncStatusEnum(includeReconciliation: true);
    }

    public function down(): void
    {
        if ($this->unresolvedEvidenceExists()) {
            throw new RuntimeException(
                'Cannot roll back payment reconciliation while unresolved reconciliation records exist.'
            );
        }

        $this->syncStatusEnum(includeReconciliation: false);

        $columns = array_values(array_filter(
            ['reconciliation_meta', 'reconciliation_reason'],
            fn (string $column) => Schema::hasColumn('orders', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    private function syncStatusEnum(bool $includeReconciliation): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $values = "'pending','paid','processing','shipped','delivered','cancelled'";
        if ($includeReconciliation) {
            $values .= ",'reconciliation_required'";
        }

        $connection->statement(
            "ALTER TABLE orders MODIFY status ENUM({$values}) NOT NULL DEFAULT 'pending'"
        );
    }

    private function unresolvedEvidenceExists(): bool
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'status')) {
            return false;
        }

        $columns = ['id', 'status'];
        $hasReason = Schema::hasColumn('orders', 'reconciliation_reason');
        $hasMeta = Schema::hasColumn('orders', 'reconciliation_meta');

        if ($hasReason) {
            $columns[] = 'reconciliation_reason';
        }

        if ($hasMeta) {
            $columns[] = 'reconciliation_meta';
        }

        $rows = Schema::getConnection()
            ->table('orders')
            ->select($columns)
            ->orderBy('id')
            ->lazy(100);

        foreach ($rows as $row) {
            if ((string) ($row->status ?? '') === 'reconciliation_required') {
                return true;
            }

            if ($hasReason && trim((string) ($row->reconciliation_reason ?? '')) !== '') {
                return true;
            }

            if ($hasMeta && $this->metaHasEvidence($row->reconciliation_meta ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function metaHasEvidence(mixed $meta): bool
    {
        if ($meta === null) {
            return false;
        }

        if (is_array($meta)) {
            return $meta !== [];
        }

        if (! is_string($meta)) {
            return true;
        }

        $trimmed = trim($meta);

        if ($trimmed === '' || $trimmed === 'null' || $trimmed === '[]' || $trimmed === '{}') {
            return false;
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return is_array($decoded) && $decoded !== [];
        }

        return true;
    }
};
