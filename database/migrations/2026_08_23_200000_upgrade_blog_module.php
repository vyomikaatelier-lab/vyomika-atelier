<?php

use App\Models\SiteSetting;
use App\Support\AnnouncementGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_posts', 'related_article_slugs')) {
                $table->json('related_article_slugs')->nullable()->after('related_service_slugs');
            }
            if (! Schema::hasColumn('blog_posts', 'hero_image_caption')) {
                $table->string('hero_image_caption')->nullable()->after('hero_image_alt');
            }
            if (! Schema::hasColumn('blog_posts', 'content_updated_at')) {
                $table->timestamp('content_updated_at')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('blog_posts', 'gallery_meta')) {
                $table->json('gallery_meta')->nullable()->after('gallery');
            }
        });

        if (Schema::hasTable('site_settings')) {
            $homepage = SiteSetting::getValue('homepage');

            if (is_array($homepage)
                && AnnouncementGuard::isBlocked(data_get($homepage, 'announcement.text'))) {
                data_set($homepage, 'announcement.text', null);
                data_set($homepage, 'announcement.link_label', null);
                data_set($homepage, 'announcement.link_href', null);
                SiteSetting::setValue('homepage', $homepage);
            }
        }
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            foreach (['related_article_slugs', 'hero_image_caption', 'content_updated_at', 'gallery_meta'] as $column) {
                if (Schema::hasColumn('blog_posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
