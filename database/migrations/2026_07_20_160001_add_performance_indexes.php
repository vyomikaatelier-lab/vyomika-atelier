<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['is_active', 'phone_verified_at']);
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->index(['user_id', 'is_default']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('customer_email');
            $table->index('status');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'purchase_mode']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'phone_verified_at']);
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_default']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['customer_email']);
            $table->dropIndex(['status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'purchase_mode']);
        });
    }
};
