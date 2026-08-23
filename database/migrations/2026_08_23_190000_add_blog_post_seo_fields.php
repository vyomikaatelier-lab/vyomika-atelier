<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_posts', 'og_title')) {
                $table->string('og_title')->nullable()->after('og_image');
            }
            if (! Schema::hasColumn('blog_posts', 'og_description')) {
                $table->string('og_description', 500)->nullable()->after('og_title');
            }
            if (! Schema::hasColumn('blog_posts', 'robots_index')) {
                $table->boolean('robots_index')->default(true)->after('canonical_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            foreach (['og_title', 'og_description', 'robots_index'] as $column) {
                if (Schema::hasColumn('blog_posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
