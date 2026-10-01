<?php

namespace App\Console\Commands;

use App\Services\ProductFulfilmentBackfill;
use Illuminate\Console\Command;

class ClearResolvedReadyStockFlagsCommand extends Command
{
    protected $signature = 'products:clear-resolved-ready-stock-flags';

    protected $description = 'Clear Shop ready-stock review flags that only asked for a production estimate.';

    public function handle(ProductFulfilmentBackfill $backfill): int
    {
        $counts = $backfill->clearResolvedReadyStockFlags();
        $this->info(sprintf(
            'Ready-stock review flags cleared=%d trimmed=%d kept=%d',
            $counts['cleared'],
            $counts['trimmed'],
            $counts['kept'],
        ));

        return self::SUCCESS;
    }
}
