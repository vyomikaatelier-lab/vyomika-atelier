<?php

namespace App\Services;

use App\Models\Order;
use App\Support\PaymentAtomicLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PendingOrderExpiry
{
    /**
     * Cancel a pending order only when it is still unpaid and past expiry.
     * Paid, in-fulfilment, and reconciliation-required rows are never overwritten.
     */
    public static function expireIfStillPending(Order $order): bool
    {
        try {
            return PaymentAtomicLock::run(
                PaymentAtomicLock::forRazorpayOrder((int) $order->id),
                PaymentAtomicLock::razorpayWaitSeconds(),
                fn () => self::expireLocked($order)
            );
        } catch (LockTimeoutException) {
            Log::info('Pending order expiry deferred while payment confirmation holds the lock.', [
                'event' => 'orders.expire_deferred',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return false;
        }
    }

    private static function expireLocked(Order $order): bool
    {
        return (bool) DB::transaction(function () use ($order) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->isPending() || $locked->isReconciliationRequired()) {
                return false;
            }

            if (filled($locked->payment_id) || $locked->needsPaymentReview()) {
                return false;
            }

            if ($locked->expires_at === null || $locked->expires_at->isFuture()) {
                return false;
            }

            $affected = Order::query()
                ->whereKey($locked->id)
                ->where('status', 'pending')
                ->whereNull('payment_id')
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
