<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('category')->nullable()->after('image');
            $table->string('author')->default('Vyomika Atelier LLP')->after('category');
            $table->unsignedSmallInteger('reading_time_minutes')->nullable()->after('author');
            $table->string('hero_image_alt')->nullable()->after('reading_time_minutes');
            $table->json('gallery')->nullable()->after('hero_image_alt');
            $table->json('related_product_slugs')->nullable()->after('gallery');
            $table->json('related_project_slugs')->nullable()->after('related_product_slugs');
            $table->json('faq')->nullable()->after('related_project_slugs');
            $table->boolean('is_featured')->default(false)->after('faq');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'author',
                'reading_time_minutes',
                'hero_image_alt',
                'gallery',
                'related_product_slugs',
                'related_project_slugs',
                'faq',
                'is_featured',
            ]);
        });
    }
};
