<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\SiteSetting;
use App\Support\StaticPageContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InstallIndiaSeoContent extends Command
{
    public const SOURCE = 'india-seo-v1';

    protected $signature = 'seo:install-india-content
        {--dry-run : Report actions without writing}
        {--rollback-tagged : Delete blog drafts created by this installer (seo_source='.self::SOURCE.')}';

    protected $description = 'Install India SEO static page defaults and draft blog articles without overwriting admin edits';

    public function handle(): int
    {
        if ($this->option('rollback-tagged')) {
            return $this->rollbackTagged();
        }

        if (! Schema::hasTable('site_settings') || ! Schema::hasTable('blog_posts')) {
            $this->error('Required tables missing. Run migrations first.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;
        $updated = 0;

        DB::beginTransaction();
        try {
            [$u, $s] = $this->installStaticPages($dry);
            $updated += $u;
            $skipped += $s;

            [$c, $sk] = $this->installBlogDrafts($dry);
            $created += $c;
            $skipped += $sk;

            if ($dry) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Static fields updated: {$updated}; blog created: {$created}; skipped: {$skipped}".($dry ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    private function rollbackTagged(): int
    {
        if (! Schema::hasTable('blog_posts') || ! Schema::hasColumn('blog_posts', 'seo_source')) {
            $this->warn('Nothing to roll back.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $query = BlogPost::query()->where('seo_source', self::SOURCE)->where('status', 'draft');
        $count = $query->count();

        if (! $dry) {
            $query->delete();
        }

        $this->info("Removed {$count} tagged draft blog(s)".($dry ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    /** @return array{0:int,1:int} */
    private function installStaticPages(bool $dry): array
    {
        $defaults = config('seo.static_pages', []);
        $existing = SiteSetting::getValue('static_pages', []) ?? [];
        if (! is_array($existing)) {
            $existing = [];
        }

        $updated = 0;
        $skipped = 0;

        foreach (StaticPageContent::slugs() as $slug) {
            $incoming = is_array($defaults[$slug] ?? null) ? $defaults[$slug] : [];
            $current = is_array($existing[$slug] ?? null) ? $existing[$slug] : [];
            $merged = StaticPageContent::fillEmptyOnly($current, $incoming);
            if ($merged !== $current) {
                $existing[$slug] = $merged;
                $updated++;
                $this->line(($dry ? '[dry] ' : '')."static_pages.{$slug} filled empty fields");
            } else {
                $skipped++;
            }
        }

        if (! $dry) {
            SiteSetting::setValue('static_pages', $existing);
        }

        return [$updated, $skipped];
    }

    /** @return array{0:int,1:int} */
    private function installBlogDrafts(bool $dry): array
    {
        $drafts = require database_path('data/seo/blog-drafts.php');
        $created = 0;
        $skipped = 0;

        foreach ($drafts as $draft) {
            $slug = $draft['slug'];
            $existing = BlogPost::query()->where('slug', $slug)->first();
            if ($existing) {
                $skipped++;
                $this->line("skip blog {$slug} (exists)");

                continue;
            }

            $content = $this->buildArticleHtml($draft);
            $payload = [
                'title' => $draft['title'],
                'slug' => $slug,
                'excerpt' => $draft['excerpt'],
                'content' => $content,
                'meta_title' => $draft['meta_title'],
                'meta_description' => $draft['meta_description'],
                'primary_keyword' => $draft['primary_keyword'] ?? null,
                'category' => $draft['category'] ?? null,
                'author' => 'Vyomika Atelier Editorial',
                'faq' => $draft['faqs'] ?? [],
                'related_service_slugs' => $draft['related_service_slugs'] ?? [],
                'related_product_slugs' => [],
                'related_project_slugs' => [],
                'status' => 'draft',
                'is_active' => false,
                'published_at' => null,
                'seo_source' => self::SOURCE,
                'reading_time_minutes' => max(4, (int) ceil(str_word_count(strip_tags($content)) / 200)),
            ];

            if (! $dry) {
                BlogPost::query()->create($payload);
            }
            $created++;
            $this->line(($dry ? '[dry] ' : '')."create draft blog {$slug}");
        }

        return [$created, $skipped];
    }

    /** @param  array<string, mixed>  $draft */
    private function buildArticleHtml(array $draft): string
    {
        $title = e($draft['title']);
        $keyword = e($draft['primary_keyword'] ?? '');
        $link = url($draft['link_path'] ?? '/');
        $linkLabel = e(ltrim((string) ($draft['link_path'] ?? '/'), '/'));

        $sections = [
            '<p><strong>Quick answer:</strong> '.$title.' — practical guidance for architects, designers and homeowners specifying work in India. This article explains selection factors; final engineering and pricing remain project-specific.</p>',
            '<h2>Why this matters on Indian projects</h2>',
            '<p>Specifications succeed when drawings, finishes and site realities stay aligned. Clients searching for <em>'.$keyword.'</em> usually need clarity on materials, finishes, process and what to share for a quotation — not generic marketing copy.</p>',
            '<h2>Key selection criteria</h2>',
            '<ul><li>Confirm the application (interior, entrance, exterior or landscape) and exposure conditions.</li><li>Match finish language to adjacent hardware, doors and furniture.</li><li>Share approximate sizes, drawings or photos early so fabrication tolerances are realistic.</li><li>Plan delivery and installation access for multi-storey or dense urban sites.</li><li>Ask for samples where finish colour or glass texture affects the design intent.</li></ul>',
            '<h2>Advantages and limitations</h2>',
            '<p>Custom metalwork offers precise detailing and coordinated PVD or weathering finishes. Limitations include lead time for fabrication, the need for accurate site data, and climate-specific detailing for exterior pieces. Avoid unsupported claims about standards, warranties or universal “best” grades without project review.</p>',
            '<h2>Common mistakes to avoid</h2>',
            '<ul><li>Locking finish colour from a phone photo instead of a physical or approved sample.</li><li>Omitting drainage and runoff planning for weathering steel.</li><li>Ordering railings or doors without layout templates and opening sizes.</li><li>Comparing quotations that do not list the same glass, hardware and installation scope.</li></ul>',
            '<h2>Process guidance</h2>',
            '<ol><li>Share the brief, drawings or site photos.</li><li>Align materials, finishes and movement type (where relevant).</li><li>Review shop drawings or templates before fabrication.</li><li>Coordinate delivery and installation with your contractor.</li></ol>',
            '<h2>Related commercial page</h2>',
            '<p>Continue on our <a href="'.$link.'">'.$linkLabel.'</a> page for applications, galleries and an enquiry or quotation path. Replace placeholder imagery with your project photographs before publishing this draft.</p>',
            '<h2>Professional enquiry</h2>',
            '<p>Architects and designers can also start from the <a href="'.url('/professionals').'">professionals collaboration page</a>. Homeowners may use the contact form with drawings attached.</p>',
        ];

        if (! empty($draft['faqs']) && is_array($draft['faqs'])) {
            $sections[] = '<h2>Frequently asked questions</h2>';
            foreach ($draft['faqs'] as $faq) {
                $q = e($faq['q'] ?? '');
                $a = e($faq['a'] ?? '');
                if ($q === '') {
                    continue;
                }
                $sections[] = '<h3>'.$q.'</h3><p>'.$a.'</p>';
            }
        }

        $sections[] = '<p><em>Draft for administrator review. Do not treat this as project-specific engineering advice. Update images and publish when ready.</em></p>';

        return implode("\n", $sections);
    }
}
