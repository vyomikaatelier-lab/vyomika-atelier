<?php

namespace App\Console\Commands;

use App\Support\BlogContentImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportBlogContentCommand extends Command
{
    protected $signature = 'blog:import-content
        {--dry-run : Preview create/update/skip/flag actions without writing}
        {--force : Apply changes without interactive confirmation}
        {--no-backup : Skip JSON backup export before applying}';

    protected $description = 'Import or update blog articles from database/content/blog (idempotent, slug-based)';

    public function handle(): int
    {
        if (! Schema::hasTable('blog_posts')) {
            $this->error('blog_posts table missing. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $importer = new BlogContentImporter(database_path('content/blog'));

        try {
            $articles = $importer->loadManifest();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Loaded '.count($articles).' article(s) from manifest.');

        if ($dryRun) {
            $this->warn('DRY RUN — no database changes will be written.');
        } elseif (! $force) {
            if (! $this->confirm('Export backup and apply blog content updates?', true)) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        if (! $dryRun && ! $this->option('no-backup')) {
            $backupPath = $importer->exportBackup();
            $this->line("Backup exported: {$backupPath}");
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
        $this->line("  Created: {$stats['created']}");
        $this->line("  Updated: {$stats['updated']}");
        $this->line("  Skipped: {$stats['skipped']}");
        $this->line("  Flagged: {$stats['flagged']}");

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

        return self::SUCCESS;
    }
}
