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

        $query = Product::query()
            ->where('is_gallery_visible', true)
            ->where(function ($q) {
                $q->whereNull('name')
                    ->orWhere('name', '=', '')
                    ->orWhereNull('image')
                    ->orWhere('image', '=', '')
                    ->orWhere('image', 'like', '%unsplash.com%')
                    ->orWhere('sku', 'like', 'SSM-P%');
            });

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No catalog filler products are visible in gallery grids.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("Dry-run: would hide {$count} filler product(s) from gallery grids.");

            $query->orderBy('slug')->limit(20)->get(['slug', 'name', 'sku'])->each(
                fn (Product $product) => $this->line("  - {$product->slug} ({$product->name})")
            );

            if ($count > 20) {
                $this->line('  … and '.($count - 20).' more');
            }

            return self::SUCCESS;
        }

        $updated = $query->update(['is_gallery_visible' => false]);

        $this->info("Hidden {$updated} catalog filler product(s) from gallery grids.");

        return self::SUCCESS;
    }
}
