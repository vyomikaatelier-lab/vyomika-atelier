<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database-first product classification.
 *
 * Adds authoritative storefront-safety fields to `products` so cart/checkout
 * guards no longer depend solely on slug/category inference
 * (App\Support\ProductCatalog remains a fallback only during migration).
 *
 * Reversible: down() drops the columns without touching existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'section')) {
                $table->string('section', 20)->nullable()->after('category_id')
                    ->comment('shop | studio | railings');
            }

            if (! Schema::hasColumn('products', 'purchase_mode')) {
                $table->string('purchase_mode', 20)->nullable()->after('section')
                    ->comment('checkout | enquiry | quote');
            }

            if (! Schema::hasColumn('products', 'pricing_type')) {
                $table->string('pricing_type', 20)->nullable()->after('purchase_mode')
                    ->comment('fixed | square_foot | quotation_only');
            }

            if (! Schema::hasColumn('products', 'is_gallery_visible')) {
                $table->boolean('is_gallery_visible')->default(true)->after('is_active');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['section', 'purchase_mode'], 'products_section_purchase_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_section_purchase_mode_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            foreach (['section', 'purchase_mode', 'pricing_type', 'is_gallery_visible'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
