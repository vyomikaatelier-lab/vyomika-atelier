<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('status');
            $table->timestamp('stock_deducted_at')->nullable()->after('expires_at');
            $table->timestamp('order_received_email_sent_at')->nullable()->after('notes');
            $table->timestamp('payment_email_sent_at')->nullable()->after('order_received_email_sent_at');
            $table->timestamp('admin_order_notified_at')->nullable()->after('payment_email_sent_at');
            $table->timestamp('admin_payment_notified_at')->nullable()->after('admin_order_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'expires_at',
                'stock_deducted_at',
                'order_received_email_sent_at',
                'payment_email_sent_at',
                'admin_order_notified_at',
                'admin_payment_notified_at',
            ]);
        });
    }
};
