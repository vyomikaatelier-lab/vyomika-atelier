<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PendingOrderExpiry;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Cancel stale unpaid orders and release their stock reservations';

    public function handle(): int
    {
        $candidates = Order::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $processed = 0;
        $skipped = 0;

        foreach ($candidates as $order) {
            if (PendingOrderExpiry::expireIfStillPending($order)) {
                $processed++;
                $this->line("Cancelled expired order {$order->order_number}");
            } else {
                $skipped++;
            }
        }

        $this->info("Expired {$processed} pending order(s); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
