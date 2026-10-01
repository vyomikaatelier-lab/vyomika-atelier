<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive fulfilment settings and order charge snapshots.
 *
 * Re-running up is safe if a previous attempt stopped halfway.
 * Down refuses while order, refund, or backfill evidence exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addProductColumns();
        $this->addOrderColumns();
        $this->addOrderItemColumns();
        $this->addRefundColumns();
        $this->createOriginals();
    }

    public function down(): void
    {
        if ($this->evidenceExists()) {
            throw new RuntimeException(
                'Cannot roll back fulfilment settings while order, refund, or backfill evidence exists.'
            );
        }

        Schema::dropIfExists('product_fulfilment_originals');

        if (Schema::hasTable('order_refunds') && Schema::hasColumn('order_refunds', 'packing_amount_paise')) {
            Schema::table('order_refunds', function (Blueprint $table) {
                $table->dropColumn('packing_amount_paise');
            });
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'fulfilment_snapshot')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('fulfilment_snapshot');
            });
        }

        if (Schema::hasTable('orders')) {
            foreach (['fulfilment_snapshot', 'packing_cost'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    Schema::table('orders', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (! Schema::hasTable('products')) {
            return;
        }

        $columns = [
            'needs_fulfilment_review',
            'fulfilment_review_note',
            'packing_international_basis',
            'packing_international_amount',
            'packing_international_mode',
            'packing_india_basis',
            'packing_india_amount',
            'packing_india_mode',
            'shipping_international_basis',
            'shipping_international_amount',
            'shipping_international_mode',
            'shipping_india_basis',
            'shipping_india_amount',
            'shipping_india_mode',
            'production_starts',
            'production_unit',
            'production_max',
            'production_min',
            'availability_mode',
        ];
        $present = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn('products', $column)
        ));
        if ($present !== []) {
            Schema::table('products', function (Blueprint $table) use ($present) {
                $table->dropColumn($present);
            });
        }
    }

    private function addProductColumns(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'availability_mode')) {
                $table->string('availability_mode', 32)->default('confirm_with_team');
            }
            if (! Schema::hasColumn('products', 'production_min')) {
                $table->unsignedSmallInteger('production_min')->nullable();
            }
            if (! Schema::hasColumn('products', 'production_max')) {
                $table->unsignedSmallInteger('production_max')->nullable();
            }
            if (! Schema::hasColumn('products', 'production_unit')) {
                $table->string('production_unit', 16)->nullable();
            }
            if (! Schema::hasColumn('products', 'production_starts')) {
                $table->string('production_starts', 40)->nullable();
            }
            if (! Schema::hasColumn('products', 'shipping_india_mode')) {
                $table->string('shipping_india_mode', 16)->default('quoted');
            }
            if (! Schema::hasColumn('products', 'shipping_india_amount')) {
                $table->decimal('shipping_india_amount', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'shipping_india_basis')) {
                $table->string('shipping_india_basis', 16)->nullable();
            }
            if (! Schema::hasColumn('products', 'shipping_international_mode')) {
                $table->string('shipping_international_mode', 16)->default('quoted');
            }
            if (! Schema::hasColumn('products', 'shipping_international_amount')) {
                $table->decimal('shipping_international_amount', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'shipping_international_basis')) {
                $table->string('shipping_international_basis', 16)->nullable();
            }
            if (! Schema::hasColumn('products', 'packing_india_mode')) {
                $table->string('packing_india_mode', 16)->default('quoted');
            }
            if (! Schema::hasColumn('products', 'packing_india_amount')) {
                $table->decimal('packing_india_amount', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'packing_india_basis')) {
                $table->string('packing_india_basis', 16)->nullable();
            }
            if (! Schema::hasColumn('products', 'packing_international_mode')) {
                $table->string('packing_international_mode', 16)->default('quoted');
            }
            if (! Schema::hasColumn('products', 'packing_international_amount')) {
                $table->decimal('packing_international_amount', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'packing_international_basis')) {
                $table->string('packing_international_basis', 16)->nullable();
            }
            if (! Schema::hasColumn('products', 'fulfilment_review_note')) {
                $table->text('fulfilment_review_note')->nullable();
            }
            if (! Schema::hasColumn('products', 'needs_fulfilment_review')) {
                $table->boolean('needs_fulfilment_review')->default(false);
            }
        });
    }

    private function addOrderColumns(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'packing_cost')) {
                $table->decimal('packing_cost', 10, 2)->nullable()->after('shipping_cost');
            }
            if (! Schema::hasColumn('orders', 'fulfilment_snapshot')) {
                $table->json('fulfilment_snapshot')->nullable()->after('shipping_snapshot');
            }
        });
    }

    private function addOrderItemColumns(): void
    {
        if (! Schema::hasTable('order_items') || Schema::hasColumn('order_items', 'fulfilment_snapshot')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('fulfilment_snapshot')->nullable();
        });
    }

    private function addRefundColumns(): void
    {
        if (! Schema::hasTable('order_refunds') || Schema::hasColumn('order_refunds', 'packing_amount_paise')) {
            return;
        }

        Schema::table('order_refunds', function (Blueprint $table) {
            $table->unsignedInteger('packing_amount_paise')->nullable()->after('shipping_amount_paise');
        });
    }

    private function createOriginals(): void
    {
        if (Schema::hasTable('product_fulfilment_originals')) {
            return;
        }

        Schema::create('product_fulfilment_originals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->json('original');
            $table->json('applied')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    private function evidenceExists(): bool
    {
        if (Schema::hasTable('product_fulfilment_originals') && DB::table('product_fulfilment_originals')->exists()) {
            return true;
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'fulfilment_snapshot')) {
            if (DB::table('orders')->whereNotNull('fulfilment_snapshot')->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'packing_cost')) {
            if (DB::table('orders')->whereNotNull('packing_cost')->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('order_refunds') && Schema::hasColumn('order_refunds', 'packing_amount_paise')) {
            if (DB::table('order_refunds')->whereNotNull('packing_amount_paise')->exists()) {
                return true;
            }
        }

        return false;
    }
};
