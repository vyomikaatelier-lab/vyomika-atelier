<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class PurgeCatalogFillerCommand extends Command
{
    protected $signature = 'catalog:purge-filler
                            {--dry-run : Report filler rows without updating the database}';

    protected $description = 'Deactivate auto-generated catalog filler products (removes from admin default list and galleries)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $fillers = Product::query()
            ->where('is_active', true)
            ->get(['id', 'slug', 'name', 'sku', 'image'])
            ->filter(fn (Product $product) => $product->isCatalogFiller())
            ->values();

        $count = $fillers->count();

        if ($count === 0) {
            $this->info('No active catalog filler products to purge.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("Dry-run: would deactivate {$count} filler product(s).");

            $fillers->take(20)->each(
                fn (Product $product) => $this->line("  - {$product->slug} ({$product->name})")
            );

            if ($count > 20) {
                $this->line('  … and '.($count - 20).' more');
            }

            return self::SUCCESS;
        }

        $updated = Product::query()
            ->whereIn('id', $fillers->pluck('id'))
            ->update([
                'is_active' => false,
                'is_gallery_visible' => false,
            ]);

        $this->info("Deactivated {$updated} catalog filler product(s). They no longer appear in admin or storefront galleries.");

        return self::SUCCESS;
    }
}
