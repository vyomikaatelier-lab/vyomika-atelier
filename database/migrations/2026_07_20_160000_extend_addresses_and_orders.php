<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('alt_mobile', 20)->nullable()->after('phone');
            $table->string('email')->nullable()->after('alt_mobile');
            $table->string('country', 100)->default('India')->after('pincode');
            $table->string('locality', 120)->nullable()->after('country');
            $table->string('house_building', 120)->nullable()->after('locality');
            $table->string('street', 200)->nullable()->after('house_building');
            $table->string('landmark', 120)->nullable()->after('street');
            $table->string('address_type', 20)->default('home')->after('landmark');
            $table->string('floor', 30)->nullable()->after('address_type');
            $table->boolean('lift_available')->nullable()->after('floor');
            $table->text('delivery_instructions')->nullable()->after('lift_available');
            $table->boolean('billing_same_as_shipping')->default(true)->after('delivery_instructions');
            $table->string('pin_lookup_status', 20)->default('format_valid')->after('billing_same_as_shipping');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('country', 100)->nullable()->after('pincode');
            $table->string('alt_mobile', 20)->nullable()->after('customer_phone');
            $table->json('shipping_snapshot')->nullable()->after('notes');
            $table->json('billing_snapshot')->nullable()->after('shipping_snapshot');
            $table->string('checkout_token', 64)->nullable()->unique()->after('billing_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'country',
                'alt_mobile',
                'shipping_snapshot',
                'billing_snapshot',
                'checkout_token',
            ]);
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn([
                'alt_mobile',
                'email',
                'country',
                'locality',
                'house_building',
                'street',
                'landmark',
                'address_type',
                'floor',
                'lift_available',
                'delivery_instructions',
                'billing_same_as_shipping',
                'pin_lookup_status',
            ]);
        });
    }
};
