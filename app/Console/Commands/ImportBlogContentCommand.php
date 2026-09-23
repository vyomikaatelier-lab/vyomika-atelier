<?php

namespace App\Console\Commands;

use App\Support\BlogContentImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

class ImportBlogContentCommand extends Command
{
    protected $signature = 'blog:import-content
        {--dry-run : Preview create/update/skip/flag actions without writing}
        {--global-only : Import only global articles (default behaviour)}
        {--regional : Import regional articles (requires explicit confirmation)}
        {--published-only : Process only manifest entries marked published}
        {--drafts-only : Process only manifest entries marked draft}
        {--force : Apply changes without interactive confirmation}
        {--backup : Export JSON backup before applying (default in production)}
        {--no-backup : Skip backup (tests/local only)}';

    protected $description = 'Import or update blog articles from database/content/blog (idempotent, slug-based)';

    public function handle(): int
    {
        if (! Schema::hasTable('blog_posts')) {
            $this->error('blog_posts table missing. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $regional = (bool) $this->option('regional');
        $globalOnly = ! $regional || (bool) $this->option('global-only');

        if ($regional && $this->option('global-only')) {
            $this->error('Use either --global-only or --regional, not both.');

            return self::FAILURE;
        }

        if ($this->option('published-only') && $this->option('drafts-only')) {
            $this->error('Use either --published-only or --drafts-only, not both.');

            return self::FAILURE;
        }

        $importer = (new BlogContentImporter(database_path('content/blog')))
            ->setGlobalOnly($regional ? false : $globalOnly)
            ->setRegionalOnly($regional)
            ->setPublishedOnly((bool) $this->option('published-only'))
            ->setDraftsOnly((bool) $this->option('drafts-only'));

        try {
            $allArticles = $importer->loadManifest();
            $articles = $importer->filterArticles($allArticles);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $scope = $regional ? 'regional' : 'global-only';
        $this->info('Loaded '.count($allArticles)." article(s) from manifest ({$scope}: ".count($articles).' eligible).');

        if ($dryRun) {
            $this->warn('DRY RUN — no database changes will be written.');
        } elseif ($regional) {
            if (! $force && ! $this->confirm('Regional articles are draft/noindex. Export backup and apply regional import?', false)) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        } elseif (! $force) {
            if (! $this->confirm('Export backup and apply global blog content updates?', true)) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        $skipBackup = (bool) $this->option('no-backup');
        $wantBackup = $this->option('backup') || (! $dryRun && ! $skipBackup);

        if ($wantBackup && ! $dryRun) {
            if ($skipBackup && App::environment('production')) {
                $this->error('Backup is mandatory in production. Remove --no-backup.');

                return self::FAILURE;
            }

            if (! $skipBackup) {
                $backupPath = $importer->exportBackup();
                $this->line("Backup exported: {$backupPath}");
            }
        }

        try {
            $stats = $importer->import($dryRun);
        } catch (\Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $report = $importer->report();

        $this->newLine();
        $this->info('Import summary'.($dryRun ? ' (dry-run)' : '').':');
        $this->line("  Processed: {$stats['processed']}");
        $this->line("  Created: {$stats['created']}");
        $this->line("  Updated: {$stats['updated']}");
        $this->line("  Skipped: {$stats['skipped']}");
        $this->line("  Flagged: {$stats['flagged']}");

        if ($report['rows'] !== []) {
            $this->newLine();
            $this->info('Detailed report:');
            $this->table(
                ['DB ID', 'Slug', 'Manifest key', 'Final slug', 'Action', 'Status before', 'Status after', 'Published before', 'Published after', 'Words'],
                collect($report['rows'])->map(fn (array $row) => [
                    $row['db_id'] ?? '—',
                    $row['slug'] ?? '—',
                    $row['manifest_key'] ?? '—',
                    $row['final_slug'] ?? '—',
                    $row['action'] ?? '—',
                    $row['status_before'] ?? '—',
                    $row['status_after'] ?? '—',
                    $row['published_at_before'] ?? '—',
                    $row['published_at_after'] ?? '—',
                    $row['word_count'] ?? '—',
                ])->all()
            );
        }

        foreach (['create' => 'CREATE', 'update' => 'UPDATE', 'skip' => 'SKIP'] as $key => $label) {
            foreach ($report[$key] as $slug) {
                $this->line("  [{$label}] {$slug}");
            }
        }

        if ($report['flag'] !== []) {
            $this->newLine();
            $this->warn('Flags / confirmations needed:');
            foreach ($report['flag'] as $flag) {
                $this->line("  ⚑ {$flag}");
            }
        }

        if ($report['errors'] !== []) {
            $this->newLine();
            $this->error('Errors:');
            foreach ($report['errors'] as $error) {
                $this->line("  ✗ {$error}");
            }
        }

        return self::SUCCESS;
    }
}
