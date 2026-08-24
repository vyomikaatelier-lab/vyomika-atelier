<?php

namespace App\Console\Commands;

use App\Support\ProductCatalog;
use Illuminate\Console\Command;

class SyncCategories extends Command
{
    protected $signature = 'catalog:sync-categories {--assign-products : Reassign products missing category or wrong section}';

    protected $description = 'Ensure canonical product categories exist with correct section and optionally reassign products';

    public function handle(): int
    {
        $synced = ProductCatalog::syncCanonicalCategories();
        $this->info("Synced {$synced} canonical categories.");

        if ($this->option('assign-products')) {
            $assigned = ProductCatalog::assignUnclassifiedProducts();
            $this->info("Reassigned or updated {$assigned} product(s).");
        }

        return self::SUCCESS;
    }
}
