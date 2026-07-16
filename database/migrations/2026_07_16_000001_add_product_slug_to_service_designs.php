<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_designs')) {
            return;
        }

        if (! Schema::hasColumn('service_designs', 'product_slug')) {
            Schema::table('service_designs', function (Blueprint $table) {
                $table->string('product_slug')->nullable()->after('image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_designs') && Schema::hasColumn('service_designs', 'product_slug')) {
            Schema::table('service_designs', function (Blueprint $table) {
                $table->dropColumn('product_slug');
            });
        }
    }
};
