<?php

namespace App\Console\Commands;

use Database\Seeders\CatalogSyncSeeder;
use Illuminate\Console\Command;

class SyncCatalogCommand extends Command
{
    protected $signature = 'catalog:sync {--dry-run : Report actions without writing to the database}';

    protected $description = 'Sync catalog categories, products, and services without overwriting admin edits';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        CatalogSyncSeeder::$dryRun = $dryRun;

        $this->call('db:seed', [
            '--class' => CatalogSyncSeeder::class,
            '--force' => true,
        ]);

        if ($dryRun) {
            $this->warn('Dry-run complete — no database changes were written.');
        }

        CatalogSyncSeeder::$dryRun = false;

        return self::SUCCESS;
    }
}
