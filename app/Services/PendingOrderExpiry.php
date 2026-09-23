<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class PendingOrderExpiry
{
    /**
     * Cancel a pending order only when it is still pending and past expiry.
     * Paid / processing / shipped / delivered rows are never overwritten.
     */
    public static function expireIfStillPending(Order $order): bool
    {
        return (bool) DB::transaction(function () use ($order) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== 'pending') {
                return false;
            }

            if ($locked->expires_at === null || $locked->expires_at->isFuture()) {
                return false;
            }

            $affected = Order::query()
                ->whereKey($locked->id)
                ->where('status', 'pending')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => 'cancelled',
                    'expires_at' => null,
                ]);

            return $affected === 1;
        });
    }
}
