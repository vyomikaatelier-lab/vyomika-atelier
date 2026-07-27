<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('headline_text')->nullable()->after('description');
            $table->text('swatches_note')->nullable()->after('headline_text');
            $table->text('tab_specifications')->nullable()->after('swatches_note');
            $table->text('tab_packaging')->nullable()->after('tab_specifications');
            $table->text('tab_shipping')->nullable()->after('tab_packaging');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'headline_text',
                'swatches_note',
                'tab_specifications',
                'tab_packaging',
                'tab_shipping',
            ]);
        });
    }
};
