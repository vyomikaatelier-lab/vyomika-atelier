<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PaymentAtomicLock
{
    /**
     * Production default CACHE_STORE is `database` (see config/cache.php and
     * .env.example). Laravel's database lock uses an atomic INSERT into
     * cache_locks (primary key on the lock name), which is shared across
     * PHP processes that use the same database. File/array stores are not
     * used here — they are not a reliable cross-process shared lock.
     */
    public static function assertStoreSupportsSharedLocks(): void
    {
        $driver = (string) config('cache.stores.database.driver');

        if ($driver !== 'database') {
            throw new RuntimeException(
                'Checkout payment locks require the database cache store for a shared atomic lock. A database-backed guard/migration is required.'
            );
        }

        if (! Schema::hasTable((string) config('cache.stores.database.lock_table', 'cache_locks'))) {
            throw new RuntimeException(
                'Checkout payment locks require the cache_locks table. A database-backed guard/migration is required.'
            );
        }
    }

    public static function forCustomer(int $userId): Lock
    {
        self::assertStoreSupportsSharedLocks();

        return Cache::store('database')->lock(
            'checkout:customer:'.$userId,
            (int) config('checkout.customer_lock_seconds', 60)
        );
    }

    public static function forRazorpayOrder(int $orderId): Lock
    {
        self::assertStoreSupportsSharedLocks();

        return Cache::store('database')->lock(
            'razorpay:order:'.$orderId,
            (int) config('checkout.razorpay_lock_seconds', 60)
        );
    }

    public static function customerWaitSeconds(): int
    {
        return (int) config('checkout.customer_lock_wait', 10);
    }

    public static function razorpayWaitSeconds(): int
    {
        return (int) config('checkout.razorpay_lock_wait', 10);
    }

    /**
     * Acquire a bounded lock, run the callback, and always release.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(Lock $lock, int $waitSeconds, callable $callback): mixed
    {
        return $lock->block($waitSeconds, $callback);
    }
}
