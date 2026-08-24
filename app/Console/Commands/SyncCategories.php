<?php

namespace App\Console\Commands;

use App\Support\ProductCatalog;
use Illuminate\Console\Command;

class SyncCategories extends Command
{
    protected $signature = 'catalog:sync-categories
                            {--assign-products : Reassign products missing category or wrong section}
                            {--dry-run : Report actions without writing to the database}';

    protected $description = 'Ensure canonical product categories exist with correct section and optionally reassign products';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = ProductCatalog::syncCanonicalCategories($dryRun);

        $suffix = $dryRun ? ' (dry-run — no changes written)' : '';
        $this->info("Synced {$result['synced']} canonical categories ({$result['created']} created, {$result['updated']} updated){$suffix}.");

        if ($this->option('assign-products') && ! $dryRun) {
            $assigned = ProductCatalog::assignUnclassifiedProducts();
            $this->info("Reassigned or updated {$assigned} product(s).");
        } elseif ($this->option('assign-products') && $dryRun) {
            $this->warn('--assign-products skipped in dry-run mode.');
        }

        return self::SUCCESS;
    }
}
