<?php

namespace App\Console\Commands;

use Database\Seeders\CatalogSyncSeeder;
use Illuminate\Console\Command;

class SyncCatalogCommand extends Command
{
    protected $signature = 'catalog:sync
                            {--dry-run : Report actions without writing to the database}
                            {--force : Required to write catalog changes in production}';

    protected $description = 'Sync catalog categories, products, and services without overwriting admin edits';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (app()->environment('production') && ! $force && ! $dryRun) {
            $this->error('Refusing to sync catalog in production without --force.');

            return self::FAILURE;
        }

        CatalogSyncSeeder::$dryRun = $dryRun;
        CatalogSyncSeeder::$force = $force || ! app()->environment('production');

        $this->call('db:seed', [
            '--class' => CatalogSyncSeeder::class,
            '--force' => true,
        ]);

        if ($dryRun) {
            $this->warn('Dry-run complete — no database changes were written.');
        }

        CatalogSyncSeeder::$dryRun = false;
        CatalogSyncSeeder::$force = false;

        return self::SUCCESS;
    }
}
