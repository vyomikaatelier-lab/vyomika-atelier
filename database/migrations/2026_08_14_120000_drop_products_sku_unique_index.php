<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'sku')) {
            return;
        }

        if (! $this->skuUniqueIndexExists()) {
            return;
        }

        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique(['sku']);
            });
        } catch (\Throwable $e) {
            if (! $this->skuUniqueIndexExists()) {
                return;
            }

            throw $e;
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'sku')) {
            return;
        }

        if ($this->skuUniqueIndexExists()) {
            return;
        }

        DB::table('products')
            ->where('sku', '')
            ->update(['sku' => null]);

        $duplicateSkus = DB::table('products')
            ->select('sku')
            ->whereNotNull('sku')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('sku');

        if ($duplicateSkus->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot restore unique index on products.sku: duplicate non-empty SKU values exist. '
                .'Run php artisan products:audit-skus for details.'
            );
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique('sku');
        });
    }

    private function skuUniqueIndexExists(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('products')");
            foreach ($indexes as $index) {
                if (! ($index->unique ?? false)) {
                    continue;
                }

                $indexName = str_replace("'", "''", (string) $index->name);
                $columns = DB::select("PRAGMA index_info('{$indexName}')");
                foreach ($columns as $column) {
                    if (($column->name ?? null) === 'sku') {
                        return true;
                    }
                }
            }

            return false;
        }

        $indexes = DB::select('SHOW INDEX FROM products WHERE Column_name = ? AND Non_unique = 0', ['sku']);

        return $indexes !== [];
    }
};
