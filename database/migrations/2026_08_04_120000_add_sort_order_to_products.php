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
            if (! Schema::hasColumn('products', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }
        });

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'sort_order')) {
            DB::table('products')
                ->orderBy('id')
                ->pluck('id')
                ->each(function (int $id) {
                    DB::table('products')->where('id', $id)->update(['sort_order' => $id]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
