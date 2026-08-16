<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('services') && ! Schema::hasColumn('services', 'hide_from_nav')) {
            Schema::table('services', function (Blueprint $table) {
                $table->boolean('hide_from_nav')->default(false)->after('is_active');
            });
        }

        if (Schema::hasTable('categories') && ! Schema::hasColumn('categories', 'hide_from_nav')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('hide_from_nav')->default(false)->after('hide_when_unavailable');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('services') && Schema::hasColumn('services', 'hide_from_nav')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('hide_from_nav');
            });
        }

        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'hide_from_nav')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('hide_from_nav');
            });
        }
    }
};
