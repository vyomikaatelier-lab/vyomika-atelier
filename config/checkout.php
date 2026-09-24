<?php

return [
    /*
    |--------------------------------------------------------------------------
    | New checkout and payment initiation
    |--------------------------------------------------------------------------
    |
    | CHECKOUT_PAYMENTS_ENABLED
    |
    | false or absent: customers cannot start a new checkout, create an order,
    | or open a new Razorpay payment. Cart browsing stays available. The pay
    | page explains that checkout is temporarily unavailable.
    |
    | true: signed-in customers can place orders and start Razorpay Checkout.
    |
    | The Razorpay callback (POST /checkout/pay/{order}) and the webhook
    | (POST /webhooks/razorpay) stay available in both modes so a payment that
    | already started can still be recorded.
    |
    | The default is false. A deploy does not accept new payments until an
    | operator sets this flag to true. The value is only a boolean switch.
    |
    */
    'payments_enabled' => filter_var(env('CHECKOUT_PAYMENTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

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
