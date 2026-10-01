<?php

namespace App\Console\Commands;

use App\Services\ProductFulfilmentBackfill;
use Illuminate\Console\Command;

class BackfillProductFulfilmentCommand extends Command
{
    protected $signature = 'products:backfill-fulfilment';

    protected $description = 'Snapshot product shipping text and apply retry-safe fulfilment defaults.';

    public function handle(ProductFulfilmentBackfill $backfill): int
    {
        $counts = $backfill->run();
        $this->info(sprintf(
            'Fulfilment backfill shop=%d studio=%d unknown=%d flagged=%d skipped=%d',
            $counts['shop'],
            $counts['studio'],
            $counts['unknown'],
            $counts['flagged'],
            $counts['skipped'],
        ));

        return self::SUCCESS;
    }
}
