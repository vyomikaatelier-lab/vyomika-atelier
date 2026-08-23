<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BlogPublicTraceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const BLOCKED_MARKERS = [
        'SYNC-TRACE',
        'ChatGPT',
        'OpenAI',
        'Cursor',
        'AI-generated',
        'system prompt',
        'dry-run report',
        'content importer',
        'database/content/blog',
        'docs/blog-international-seo',
        'owner-confirmation-required',
        'C:\\Users\\',
        'D:\\VYOMIKA',
        '.cursor/projects',
    ];

    /** @var list<string> */
    private const REPRESENTATIVE_SLUGS = [
        'glass-partitions-open-plan',
        'pvd-coating-explained',
        'corten-steel-modern-facades',
        'pvd-partition-price-in-india-what-determines-final-cost',
        'stainless-steel-railings-types-finishes-selection-guide',
    ];

    private function importGlobalContent(): void
    {
        Artisan::call('blog:import-content', [
            '--force' => true,
            '--global-only' => true,
            '--no-backup' => true,
        ]);
    }

    private function assertHtmlFreeOfBlockedMarkers(string $html, string $context): void
    {
        foreach (self::BLOCKED_MARKERS as $marker) {
            $this->assertStringNotContainsString(
                $marker,
                $html,
                "Blocked trace marker [{$marker}] found in {$context}"
            );
        }
    }

    public function test_blog_index_html_has_no_ai_or_internal_trace_markers(): void
    {
        $this->importGlobalContent();

        $html = $this->get(route('blog.index'))->assertOk()->getContent();

        $this->assertHtmlFreeOfBlockedMarkers($html, 'blog index');
        $this->assertStringNotContainsString('human written', strtolower($html));

        if (BlogPost::query()->where('status', BlogPost::STATUS_PUBLISHED)->exists()) {
            $this->assertStringContainsString('Vyomika Atelier Editorial Team', $html);
        }
    }

    public function test_representative_articles_have_no_ai_or_internal_trace_markers(): void
    {
        $this->importGlobalContent();

        $checked = 0;

        foreach (self::REPRESENTATIVE_SLUGS as $slug) {
            $post = BlogPost::query()->where('slug', $slug)->first();
            if ($post === null) {
                continue;
            }

            if (! $post->isPublished()) {
                $post->update([
                    'status' => BlogPost::STATUS_PUBLISHED,
                    'published_at' => now()->subDay(),
                    'is_active' => true,
                ]);
            }

            $html = $this->get(route('blog.show', $slug))->assertOk()->getContent();

            $this->assertHtmlFreeOfBlockedMarkers($html, "article: {$slug}");
            $this->assertStringContainsString('Vyomika Atelier Editorial Team', $html);
            $this->assertStringContainsString('BlogPosting', $html);
            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'Expected at least one representative article to import for trace audit');
    }

    public function test_sitemap_and_core_error_pages_have_no_trace_markers(): void
    {
        $this->importGlobalContent();

        $sitemap = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertHtmlFreeOfBlockedMarkers($sitemap, 'sitemap');

        $notFound = $this->get('/blog/non-existent-slug-for-trace-audit')->assertNotFound()->getContent();
        $this->assertHtmlFreeOfBlockedMarkers($notFound, '404 page');
    }

    public function test_imported_manifest_content_library_has_no_ai_trace_markers(): void
    {
        $manifest = require database_path('content/blog/manifest.php');
        $articlesDir = database_path('content/blog/articles');

        foreach ($manifest as $entry) {
            if (($entry['locale'] ?? null) !== null) {
                continue;
            }

            $slug = $entry['slug'];
            $articleFile = $articlesDir.'/'.$slug.'.php';
            $this->assertFileExists($articleFile, "Missing article file for {$slug}");

            $article = require $articleFile;
            $blob = json_encode($article, JSON_UNESCAPED_UNICODE);

            foreach (self::BLOCKED_MARKERS as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $blob,
                    "Blocked marker [{$marker}] in content library: {$slug}"
                );
            }
        }
    }

    public function test_public_htaccess_denies_sensitive_path_patterns(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($htaccess);
        $this->assertStringContainsString('storage/(app|framework|logs', $htaccess);
        $this->assertStringContainsString('^(docs|database|tests|vendor', $htaccess);
        $this->assertStringContainsString('\.env', $htaccess);
    }
}
