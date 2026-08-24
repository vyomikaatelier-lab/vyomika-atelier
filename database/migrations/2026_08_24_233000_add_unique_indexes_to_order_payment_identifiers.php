<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nullable unique indexes for Razorpay identifiers.
 *
 * Pre-deployment duplicate check (read-only; do not UPDATE/DELETE rows):
 *
 *   SELECT razorpay_order_id, COUNT(*) AS c
 *   FROM orders
 *   WHERE razorpay_order_id IS NOT NULL AND razorpay_order_id != ''
 *   GROUP BY razorpay_order_id
 *   HAVING c > 1;
 *
 *   SELECT payment_id, COUNT(*) AS c
 *   FROM orders
 *   WHERE payment_id IS NOT NULL AND payment_id != ''
 *   GROUP BY payment_id
 *   HAVING c > 1;
 *
 * If either query returns rows, resolve duplicates manually before migrating.
 * This migration refuses to run when duplicates exist and does not change payment data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicateIdentifiers('razorpay_order_id');
        $this->assertNoDuplicateIdentifiers('payment_id');

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('razorpay_order_id');
            $table->unique('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['razorpay_order_id']);
            $table->dropUnique(['payment_id']);
        });
    }

    private function assertNoDuplicateIdentifiers(string $column): void
    {
        $duplicates = DB::table('orders')
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                "Refusing to add a unique index on orders.{$column}: {$duplicates->count()} duplicated value(s) exist. ".
                'Run the read-only duplicate-check SQL in this migration file, resolve rows manually, then migrate. '.
                'No payment data was modified.'
            );
        }
    }
};
