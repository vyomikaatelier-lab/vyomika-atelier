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
    | Atomic checkout / Razorpay locks (database cache_locks)
    |--------------------------------------------------------------------------
    |
    | Bounded seconds to hold and wait for shared locks. Do not raise these
    | enough to hold a database transaction open across gateway HTTP calls —
    | the lock itself is not a SQL transaction.
    |
    */
    'customer_lock_seconds' => 20,
    'customer_lock_wait' => 10,
    'razorpay_lock_seconds' => 20,
    'razorpay_lock_wait' => 10,
];
