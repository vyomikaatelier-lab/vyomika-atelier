<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Cancel stale unpaid orders and release their stock reservations';

    public function handle(): int
    {
        $expired = Order::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($expired as $order) {
            $order->update([
                'status' => 'cancelled',
                'expires_at' => null,
            ]);
            $count++;
            $this->line("Cancelled expired order {$order->order_number}");
        }

        $this->info("Expired {$count} pending order(s).");

        return self::SUCCESS;
    }
}
