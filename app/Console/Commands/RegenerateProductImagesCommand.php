<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductImageDerivativeService;
use Illuminate\Console\Command;

class RegenerateProductImagesCommand extends Command
{
    protected $signature = 'images:regenerate-products {--product= : Regenerate for a single product ID}';

    protected $description = 'Generate missing WebP derivatives for product images (preserves originals)';

    public function handle(ProductImageDerivativeService $derivatives): int
    {
        $query = Product::query()->whereNotNull('image')->where('image', '!=', '');

        if ($id = $this->option('product')) {
            $query->whereKey($id);
        }

        $count = 0;
        $query->orderBy('id')->chunkById(50, function ($products) use ($derivatives, &$count) {
            foreach ($products as $product) {
                $path = (string) $product->image;
                if (str_starts_with($path, 'http')) {
                    continue;
                }

                $generated = $derivatives->generateForPath($path);
                if ($generated !== []) {
                    $count++;
                    $this->line('Generated '.count($generated).' derivatives for product #'.$product->id);
                }
            }
        });

        $this->info("Regenerated derivatives for {$count} product image(s).");

        return self::SUCCESS;
    }
}
