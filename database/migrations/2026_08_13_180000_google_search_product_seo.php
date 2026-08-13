<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_alt')->nullable()->after('image');
            $table->string('material')->nullable()->after('og_image');
            $table->string('finish')->nullable()->after('material');
            $table->string('color')->nullable()->after('finish');
            $table->decimal('weight_kg', 8, 3)->nullable()->after('color');
            $table->string('gtin', 14)->nullable()->after('weight_kg');
            $table->string('mpn')->nullable()->after('gtin');
            $table->string('seo_keyword')->nullable()->after('mpn');
            $table->string('canonical_url', 500)->nullable()->after('seo_keyword');
            $table->boolean('robots_index')->default(true)->after('canonical_url');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('og_image')->nullable()->after('meta_description');
        });

        $this->prepareSkusForUniqueIndex();

        Schema::table('products', function (Blueprint $table) {
            $table->unique('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn([
                'image_alt',
                'material',
                'finish',
                'color',
                'weight_kg',
                'gtin',
                'mpn',
                'seo_keyword',
                'canonical_url',
                'robots_index',
            ]);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('og_image');
        });
    }

    private function prepareSkusForUniqueIndex(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'sku')) {
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
            $examples = $duplicateSkus->take(10)->implode(', ');
            $suffix = $duplicateSkus->count() > 10 ? ' (and more)' : '';

            throw new \RuntimeException(
                'Cannot add unique index on products.sku: duplicate non-empty SKU values remain after blank normalization. '
                .'Resolve duplicates manually (do not auto-rename). Examples: '.$examples.$suffix.'. '
                .'Run php artisan products:audit-skus for a full report before migrating.'
            );
        }
    }
};
