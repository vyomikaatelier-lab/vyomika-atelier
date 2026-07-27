<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('size_options')->nullable()->after('price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('size_label')->nullable()->after('finish_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('size_options');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('size_label');
        });
    }
};
