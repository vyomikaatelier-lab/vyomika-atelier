<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Support\BlogContentImporter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BlogContentImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $livePublishedSlugs = [
        'glass-partitions-open-plan',
        'pvd-coating-explained',
        'corten-steel-modern-facades',
    ];

    private function globalManifestCount(): int
    {
        $manifest = require database_path('content/blog/manifest.php');
        $importer = new BlogContentImporter(database_path('content/blog'));

        return count($importer->filterArticles($manifest));
    }

    private function seedLivePublishedPosts(): void
    {
        $dates = [
            'glass-partitions-open-plan' => '2026-06-15',
            'pvd-coating-explained' => '2026-06-10',
            'corten-steel-modern-facades' => '2026-06-05',
        ];

        foreach ($this->livePublishedSlugs as $slug) {
            BlogPost::create([
                'title' => 'Live '.$slug,
                'slug' => $slug,
                'content' => '<p>Live content</p>',
                'excerpt' => str_repeat('x', 150),
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_at' => Carbon::parse($dates[$slug]),
                'hero_image_alt' => 'Alt',
                'is_active' => true,
            ]);
        }
    }

    public function test_dry_run_global_only_processes_25_articles_without_writing(): void
    {
        $before = BlogPost::count();

        $exit = Artisan::call('blog:import-content', [
            '--dry-run' => true,
            '--global-only' => true,
            '--force' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertSame($before, BlogPost::count());
        $this->assertStringContainsString('dry-run', strtolower($output));
        $this->assertStringContainsString('Processed: 25', $output);
    }

    public function test_global_only_excludes_regional_create_actions(): void
    {
        Artisan::call('blog:import-content', [
            '--dry-run' => true,
            '--global-only' => true,
            '--force' => true,
        ]);
        $output = Artisan::output();

        $this->assertStringNotContainsString('[CREATE] india-pvd-partition-prices-materials-size-installation', $output);
        $this->assertStringNotContainsString('[CREATE] uk-metal-room-dividers-interiors-specification-guide', $output);
        $this->assertStringNotContainsString('[CREATE] uae-corten-steel-heat-humidity-coastal-considerations', $output);
    }

    public function test_regional_import_requires_explicit_flag(): void
    {
        Artisan::call('blog:import-content', [
            '--dry-run' => true,
            '--regional' => true,
            '--force' => true,
        ]);
        $output = Artisan::output();

        $this->assertStringContainsString('regional: 9 eligible', $output);
        $this->assertStringContainsString('[CREATE] india-pvd-partition-prices-materials-size-installation', $output);
    }

    public function test_live_published_slugs_update_existing_records_not_create(): void
    {
        $this->seedLivePublishedPosts();

        Artisan::call('blog:import-content', [
            '--dry-run' => true,
            '--global-only' => true,
            '--force' => true,
        ]);
        $output = Artisan::output();

        foreach ($this->livePublishedSlugs as $slug) {
            $this->assertStringContainsString("[UPDATE] {$slug}", $output);
            $this->assertStringNotContainsString("[CREATE] {$slug}", $output);
        }
    }

    public function test_legacy_longer_slugs_are_not_created(): void
    {
        Artisan::call('blog:import-content', [
            '--force' => true,
            '--global-only' => true,
            '--no-backup' => true,
        ]);

        foreach (array_keys(BlogContentImporter::LEGACY_SLUG_MAP) as $legacySlug) {
            $this->assertDatabaseMissing('blog_posts', ['slug' => $legacySlug]);
        }
    }

    public function test_published_live_slugs_remain_published_with_preserved_dates(): void
    {
        $this->seedLivePublishedPosts();
        $originalDates = BlogPost::query()
            ->whereIn('slug', $this->livePublishedSlugs)
            ->pluck('published_at', 'slug')
            ->all();

        Artisan::call('blog:import-content', [
            '--force' => true,
            '--global-only' => true,
            '--no-backup' => true,
        ]);

        foreach ($this->livePublishedSlugs as $slug) {
            $post = BlogPost::query()->where('slug', $slug)->first();
            $this->assertNotNull($post, "Missing live slug: {$slug}");
            $this->assertSame(BlogPost::STATUS_PUBLISHED, $post->status);
            $this->assertTrue($post->isPublished());
            $this->assertSame(
                $originalDates[$slug]->toDateString(),
                $post->published_at->toDateString(),
                "published_at changed for {$slug}"
            );
        }
    }

    public function test_live_published_urls_return_http_200(): void
    {
        $this->seedLivePublishedPosts();

        foreach ($this->livePublishedSlugs as $slug) {
            $this->get(route('blog.show', $slug))->assertOk();
        }
    }

    public function test_import_creates_global_posts_idempotently_by_slug(): void
    {
        $expectedCount = $this->globalManifestCount();

        Artisan::call('blog:import-content', [
            '--force' => true,
            '--global-only' => true,
            '--no-backup' => true,
        ]);

        $this->assertSame($expectedCount, BlogPost::count());

        $firstRunIds = BlogPost::query()->orderBy('id')->pluck('id', 'slug')->all();

        Artisan::call('blog:import-content', [
            '--force' => true,
            '--global-only' => true,
            '--no-backup' => true,
        ]);

        $this->assertSame($expectedCount, BlogPost::count());
        $this->assertSame($firstRunIds, BlogPost::query()->orderBy('id')->pluck('id', 'slug')->all());
    }

    public function test_non_pillar_global_articles_import_as_draft_or_scheduled(): void
    {
        Artisan::call('blog:import-content', [
            '--force' => true,
            '--global-only' => true,
            '--no-backup' => true,
        ]);

        $others = BlogPost::query()
            ->whereNotIn('slug', $this->livePublishedSlugs)
            ->get();

        $this->assertGreaterThan(0, $others->count());

        foreach ($others as $post) {
            $this->assertContains($post->status, [
                BlogPost::STATUS_DRAFT,
                BlogPost::STATUS_SCHEDULED,
            ], "Unexpected status for {$post->slug}: {$post->status}");
        }
    }

    public function test_no_duplicate_slugs_after_import(): void
    {
        Artisan::call('blog:import-content', [
            '--force' => true,
            '--global-only' => true,
            '--no-backup' => true,
        ]);

        $duplicates = BlogPost::query()
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');

        $this->assertCount(0, $duplicates);
    }

    public function test_backup_file_is_created_on_apply(): void
    {
        BlogPost::create([
            'title' => 'Existing',
            'slug' => 'existing-pre-import',
            'content' => '<p>Old</p>',
            'status' => BlogPost::STATUS_DRAFT,
            'hero_image_alt' => 'Alt text',
            'is_active' => true,
        ]);

        Artisan::call('blog:import-content', [
            '--force' => true,
            '--global-only' => true,
        ]);

        $backups = glob(storage_path('app/blog-backups/blog-posts-*.json'));
        $this->assertNotEmpty($backups);
    }

    public function test_force_does_not_bypass_published_slug_preservation(): void
    {
        $this->seedLivePublishedPosts();

        Artisan::call('blog:import-content', [
            '--force' => true,
            '--global-only' => true,
            '--no-backup' => true,
        ]);

        foreach ($this->livePublishedSlugs as $slug) {
            $post = BlogPost::query()->where('slug', $slug)->first();
            $this->assertSame(BlogPost::STATUS_PUBLISHED, $post->status);
            $this->assertSame($slug, $post->slug);
        }
    }

    public function test_invalid_corten_steel_facade_service_is_normalized(): void
    {
        $importer = new BlogContentImporter(database_path('content/blog'));
        $articles = $importer->loadManifest();
        $ukCorten = collect($articles)->firstWhere('slug', 'uk-corten-steel-cladding-weathering-drainage-detailing');

        $this->assertNotNull($ukCorten);
        $this->assertContains('corten-steel', $ukCorten['related_service_slugs'] ?? []);
        $this->assertNotContains('corten-steel-facade', $ukCorten['related_service_slugs'] ?? []);
    }
}
