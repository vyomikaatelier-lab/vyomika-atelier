<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Retired: require a verified customer phone number at checkout
    |--------------------------------------------------------------------------
    |
    | Customer WhatsApp OTP is removed from the storefront. Authenticated,
    | active, non-admin customers proceed to checkout without phone_verified_at.
    | This flag is unused and kept only so existing env files do not error.
    |
    */
    'require_verified_phone' => false,

    /*
    |--------------------------------------------------------------------------
    | Razorpay order-create HTTP bounds (seconds)
    |--------------------------------------------------------------------------
    |
    | Order creation must finish well before the lock lease expires. Lock lease
    | must exceed the HTTP timeout by at least 30 seconds (see *_lock_seconds).
    |
    */
    'razorpay_create_timeout' => 15,
    'razorpay_connect_timeout' => 5,

    /*
    |--------------------------------------------------------------------------
    | Atomic checkout / Razorpay locks (database cache_locks)
    |--------------------------------------------------------------------------
    |
    | Lease seconds = how long a holder may retain the lock (must exceed the
    | maximum Razorpay create HTTP duration plus a safety margin).
    | Wait seconds = how long a competitor waits to acquire the lock.
    |
    */
    'customer_lock_seconds' => 60,
    'customer_lock_wait' => 10,
    'razorpay_lock_seconds' => 60,
    'razorpay_lock_wait' => 10,
];
