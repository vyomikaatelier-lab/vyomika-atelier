<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Support\BlogContentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BlogContentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_all_manifest_slugs_without_writing(): void
    {
        $before = BlogPost::count();

        $exit = Artisan::call('blog:import-content', ['--dry-run' => true, '--force' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertSame($before, BlogPost::count());
        $this->assertStringContainsString('dry-run', strtolower($output));
        $this->assertStringContainsString('25', $output);
    }

    public function test_import_creates_twenty_five_posts_idempotently_by_slug(): void
    {
        Artisan::call('blog:import-content', ['--force' => true, '--no-backup' => true]);

        $this->assertSame(25, BlogPost::count());

        $firstRunIds = BlogPost::query()->orderBy('id')->pluck('id', 'slug')->all();

        Artisan::call('blog:import-content', ['--force' => true, '--no-backup' => true]);

        $this->assertSame(25, BlogPost::count());
        $this->assertSame($firstRunIds, BlogPost::query()->orderBy('id')->pluck('id', 'slug')->all());
    }

    public function test_published_pillar_slugs_remain_published_after_reimport(): void
    {
        Artisan::call('blog:import-content', ['--force' => true, '--no-backup' => true]);

        foreach (BlogContentImporter::PRESERVE_PUBLISHED_SLUGS as $slug) {
            $post = BlogPost::query()->where('slug', $slug)->first();
            $this->assertNotNull($post, "Missing pillar slug: {$slug}");
            $this->assertSame(BlogPost::STATUS_PUBLISHED, $post->status, "Pillar not published: {$slug}");
            $this->assertTrue($post->isPublished(), "Pillar not publicly visible: {$slug}");
        }
    }

    public function test_non_pillar_articles_import_as_draft_or_scheduled(): void
    {
        Artisan::call('blog:import-content', ['--force' => true, '--no-backup' => true]);

        $draftOrScheduled = BlogPost::query()
            ->whereNotIn('slug', BlogContentImporter::PRESERVE_PUBLISHED_SLUGS)
            ->get();

        $this->assertGreaterThan(0, $draftOrScheduled->count());

        foreach ($draftOrScheduled as $post) {
            $this->assertContains($post->status, [
                BlogPost::STATUS_DRAFT,
                BlogPost::STATUS_SCHEDULED,
            ], "Unexpected status for {$post->slug}: {$post->status}");
        }
    }

    public function test_no_duplicate_slugs_after_import(): void
    {
        Artisan::call('blog:import-content', ['--force' => true, '--no-backup' => true]);

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

        Artisan::call('blog:import-content', ['--force' => true]);

        $backups = glob(storage_path('app/blog-backups/blog-posts-*.json'));
        $this->assertNotEmpty($backups);
    }
}
