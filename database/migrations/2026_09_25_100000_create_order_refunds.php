<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only refund ledger plus order summary columns.
 *
 * Down refuses to run while any refund row or order refund evidence remains.
 * Re-running up is safe if a previous attempt stopped halfway.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addOrderColumns();
        $this->createRefunds();
        $this->createLines();
        $this->createEvents();
    }

    public function down(): void
    {
        if ($this->evidenceExists()) {
            throw new RuntimeException(
                'Cannot roll back payment refunds while refund evidence exists.'
            );
        }

        Schema::dropIfExists('order_refund_events');
        Schema::dropIfExists('order_refund_lines');
        Schema::dropIfExists('order_refunds');

        if (Schema::hasTable('orders') && Schema::hasIndex('orders', 'orders_refund_status_idx')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_refund_status_idx');
            });
        }

        $columns = array_values(array_filter(
            [
                'refund_status',
                'refund_pending_amount_paise',
                'refunded_amount_paise',
                'captured_amount_paise',
            ],
            fn (string $column) => Schema::hasColumn('orders', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    private function addOrderColumns(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'captured_amount_paise')) {
                $table->unsignedBigInteger('captured_amount_paise')->nullable()->after('payment_id');
            }

            if (! Schema::hasColumn('orders', 'refunded_amount_paise')) {
                $table->unsignedBigInteger('refunded_amount_paise')->default(0)->after(
                    Schema::hasColumn('orders', 'captured_amount_paise') ? 'captured_amount_paise' : 'payment_id'
                );
            }

            if (! Schema::hasColumn('orders', 'refund_pending_amount_paise')) {
                $table->unsignedBigInteger('refund_pending_amount_paise')->default(0)->after('refunded_amount_paise');
            }

            if (! Schema::hasColumn('orders', 'refund_status')) {
                $table->string('refund_status', 32)->default('none')->after('refund_pending_amount_paise');
            }
        });

        if (Schema::hasColumn('orders', 'refund_status') && ! Schema::hasIndex('orders', 'orders_refund_status_idx')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('refund_status', 'orders_refund_status_idx');
            });
        }
    }

    private function createRefunds(): void
    {
        if (Schema::hasTable('order_refunds')) {
            return;
        }

        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('payment_id', 191);
            $table->string('idempotency_key', 64);
            $table->string('gateway_refund_id', 191)->nullable();
            $table->string('receipt', 40);
            $table->unsignedInteger('amount_paise');
            $table->char('currency', 3)->default('INR');
            $table->string('kind', 16);
            $table->string('reason_code', 64);
            $table->string('status', 32);
            $table->boolean('includes_shipping')->default(false);
            $table->unsignedInteger('shipping_amount_paise')->default(0);
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_staff_id', 20)->nullable();
            $table->text('internal_note')->nullable();
            $table->char('request_body_sha256', 64)->nullable();
            $table->unsignedInteger('submit_attempts')->default(0);
            $table->string('gateway_status', 32)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->unique('idempotency_key', 'order_refunds_idempotency_uq');
            $table->unique('gateway_refund_id', 'order_refunds_gateway_uq');
            $table->unique('receipt', 'order_refunds_receipt_uq');
            $table->index(['order_id', 'status'], 'order_refunds_order_status_idx');
            $table->index('payment_id', 'order_refunds_payment_idx');
            $table->index(['status', 'submitted_at'], 'order_refunds_status_submitted_idx');
        });
    }

    private function createLines(): void
    {
        if (Schema::hasTable('order_refund_lines')) {
            return;
        }

        Schema::create('order_refund_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_refund_id')->constrained('order_refunds')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('amount_paise');
            $table->string('stock_restoration', 16)->default('pending');
            $table->timestamp('stock_restored_at')->nullable();
            $table->timestamps();

            $table->unique(['order_refund_id', 'order_item_id'], 'order_refund_lines_item_uq');
        });
    }

    private function createEvents(): void
    {
        if (Schema::hasTable('order_refund_events')) {
            return;
        }

        Schema::create('order_refund_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_refund_id')->constrained('order_refunds')->restrictOnDelete();
            $table->string('event', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->string('gateway_refund_id', 191)->nullable();
            $table->unsignedInteger('amount_paise')->nullable();
            $table->char('payload_sha256', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_refund_id', 'order_refund_events_refund_idx');
        });
    }

    private function evidenceExists(): bool
    {
        foreach (['order_refunds', 'order_refund_lines', 'order_refund_events'] as $table) {
            if (Schema::hasTable($table) && Schema::getConnection()->table($table)->exists()) {
                return true;
            }
        }

        if (! Schema::hasTable('orders')) {
            return false;
        }

        $query = Schema::getConnection()->table('orders');
        $constrained = false;

        if (Schema::hasColumn('orders', 'captured_amount_paise')) {
            $query->whereNotNull('captured_amount_paise');
            $constrained = true;
        }

        if (Schema::hasColumn('orders', 'refunded_amount_paise')) {
            $method = $constrained ? 'orWhere' : 'where';
            $query->{$method}('refunded_amount_paise', '>', 0);
            $constrained = true;
        }

        if (Schema::hasColumn('orders', 'refund_pending_amount_paise')) {
            $method = $constrained ? 'orWhere' : 'where';
            $query->{$method}('refund_pending_amount_paise', '>', 0);
            $constrained = true;
        }

        if (Schema::hasColumn('orders', 'refund_status')) {
            $method = $constrained ? 'orWhere' : 'where';
            $query->{$method}(function ($inner) {
                $inner->whereNotNull('refund_status')
                    ->where('refund_status', '!=', 'none')
                    ->where('refund_status', '!=', '');
            });
            $constrained = true;
        }

        return $constrained && $query->exists();
    }
};
