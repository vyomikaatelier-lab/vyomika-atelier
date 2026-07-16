<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image')->nullable()->after('description');
            $table->string('meta_title')->nullable()->after('image');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->unsignedInteger('sort_order')->default(0)->after('meta_description');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_admin');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('location');
            $table->json('finishes')->nullable()->after('materials');
            $table->text('scope')->nullable()->after('design_details');
            $table->text('challenges')->nullable()->after('scope');
            $table->unsignedInteger('display_order')->default(0)->after('is_active');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('canonical_url')->nullable()->after('meta_description');
            $table->json('related_service_slugs')->nullable()->after('related_project_slugs');
            $table->string('status')->default('published')->after('is_active');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('admin_notes');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leads MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'inquiry'");
            DB::statement("ALTER TABLE leads MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'new'");
        }

        Schema::create('exhibitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('country')->default('India');
            $table->unsignedSmallInteger('year')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('sections')->nullable();
            $table->date('content_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('alt')->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('legal_pages');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('exhibitions');

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leads MODIFY COLUMN type ENUM('custom_order','contact','inquiry') NOT NULL DEFAULT 'inquiry'");
            DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('new','contacted','quoted','converted','closed') NOT NULL DEFAULT 'new'");
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['canonical_url', 'related_service_slugs', 'status']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['year', 'finishes', 'scope', 'challenges', 'display_order']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['image', 'meta_title', 'meta_description', 'sort_order']);
        });
    }
};
