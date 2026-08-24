<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class HideCatalogFillerCommand extends Command
{
    protected $signature = 'catalog:hide-filler
                            {--dry-run : Report filler rows without updating the database}';

    protected $description = 'Hide auto-generated catalog filler products from gallery grids (does not delete rows)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $fillers = Product::query()
            ->where('is_gallery_visible', true)
            ->get(['id', 'slug', 'name', 'sku', 'image'])
            ->filter(fn (Product $product) => $product->isCatalogFiller())
            ->values();

        $count = $fillers->count();

        if ($count === 0) {
            $this->info('No catalog filler products are visible in gallery grids.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("Dry-run: would hide {$count} filler product(s) from gallery grids.");

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
            ->update(['is_gallery_visible' => false]);

        $this->info("Hidden {$updated} catalog filler product(s) from gallery grids.");

        return self::SUCCESS;
    }
}
