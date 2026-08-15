<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('hide_when_out_of_stock')
                ->default(false)
                ->after('stock');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('hide_when_unavailable')
                ->default(false)
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('hide_when_out_of_stock');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('hide_when_unavailable');
        });
    }
};
