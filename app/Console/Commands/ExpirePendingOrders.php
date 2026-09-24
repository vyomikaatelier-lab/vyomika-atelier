<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PendingOrderExpiry;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Cancel expired unpaid pending orders. Paid and reconciliation-required orders are left unchanged.';

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
            } else {
                $skipped++;
            }
        }

        $this->info("Expired {$processed} pending order(s); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
