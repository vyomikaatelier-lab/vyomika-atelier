<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditProductSkusCommand extends Command
{
    protected $signature = 'products:audit-skus';

    protected $description = 'Report product SKU duplicates and blank values (informational; does not block saves)';

    public function handle(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('products')) {
            $this->error('products table does not exist.');

            return self::FAILURE;
        }

        $blankCount = DB::table('products')->where('sku', '')->count();
        $nullCount = DB::table('products')->whereNull('sku')->count();
        $nonEmptyCount = DB::table('products')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->count();

        $this->line('SKU summary:');
        $this->line("  null: {$nullCount}");
        $this->line("  blank (empty string): {$blankCount}");
        $this->line("  non-empty: {$nonEmptyCount}");

        if ($blankCount > 0) {
            $this->warn('Blank SKU strings found — migration will normalize these to NULL.');
        }

        $duplicateSkus = DB::table('products')
            ->select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('sku')
            ->pluck('sku');

        if ($duplicateSkus->isEmpty()) {
            $this->info('No duplicate non-empty SKUs found.');

            return self::SUCCESS;
        }

        $this->warn('Duplicate non-empty SKUs found ('.$duplicateSkus->count().' value(s)) — informational only:');

        foreach ($duplicateSkus as $sku) {
            $this->line('');
            $this->line("SKU: {$sku}");

            $rows = Product::query()
                ->where('sku', $sku)
                ->orderBy('id')
                ->get(['id', 'name', 'sku', 'is_active', 'slug', 'section']);

            foreach ($rows as $product) {
                $status = $product->is_active ? 'active' : 'inactive';
                $url = $this->productUrl($product);

                $this->line(sprintf(
                    '  id=%d | %s | sku=%s | status=%s | slug=%s | url=%s',
                    $product->id,
                    $product->name,
                    $product->sku,
                    $status,
                    $product->slug,
                    $url
                ));
            }
        }

        $this->line('');
        $this->warn('Duplicate SKUs are allowed. Use this report for cleanup only if you want unique values.');

        return self::SUCCESS;
    }

    private function productUrl(Product $product): string
    {
        if ($product->section === Product::SECTION_SHOP && filled($product->slug)) {
            return route('shop.show', $product->slug);
        }

        return '/shop/'.$product->slug;
    }
}
