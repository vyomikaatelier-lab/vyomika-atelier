<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('dim_width_cm', 8, 2)->nullable()->after('tab_shipping');
            $table->decimal('dim_height_cm', 8, 2)->nullable()->after('dim_width_cm');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['dim_width_cm', 'dim_height_cm']);
        });
    }
};
