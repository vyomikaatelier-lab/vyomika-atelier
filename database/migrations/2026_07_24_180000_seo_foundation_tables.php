<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('url_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500)->unique();
            $table->string('to_url', 1000);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('description');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->string('og_image')->nullable()->after('meta_description');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_posts', 'seo_source')) {
                $table->string('seo_source', 64)->nullable()->after('status');
            }
            if (! Schema::hasColumn('blog_posts', 'og_image')) {
                $table->string('og_image')->nullable()->after('image');
            }
            if (! Schema::hasColumn('blog_posts', 'primary_keyword')) {
                $table->string('primary_keyword')->nullable()->after('meta_description');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('url_redirects');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'og_image']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            foreach (['seo_source', 'og_image', 'primary_keyword'] as $col) {
                if (Schema::hasColumn('blog_posts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
