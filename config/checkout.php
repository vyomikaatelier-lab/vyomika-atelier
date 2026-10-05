<?php

return [
    /*
    |--------------------------------------------------------------------------
    | New checkout and payment initiation
    |--------------------------------------------------------------------------
    |
    | CHECKOUT_PAYMENTS_ENABLED
    |
    | false or absent: customers cannot start a new checkout or create an order.
    | Cart browsing stays available. A supervised test order that already has
    | a Razorpay id can still open its pay page until the expiry. Every other
    | pay page says checkout is temporarily unavailable.
    |
    | true: signed-in customers can place orders and start Razorpay Checkout
    | only when CHECKOUT_PAYMENTS_UNRESTRICTED is also true. Production leaves
    | that false, so true alone does not open checkout.
    |
    | The Razorpay callback (POST /checkout/pay/{order}) and the webhook
    | (POST /webhooks/razorpay) stay available in both modes so a payment that
    | already started can still be recorded.
    |
    | The default is false. A deploy does not accept new payments until an
    | operator sets this flag to true. The value is only a boolean switch.
    |
    | Checkout offers Razorpay only. Cash on delivery is not offered.
    | bank_transfer remains a historical orders.payment_method value and is
    | not offered at checkout. This application does not add a workflow for it.
    |
    */
    'payments_enabled' => filter_var(env('CHECKOUT_PAYMENTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Test-suite bypass
    |--------------------------------------------------------------------------
    |
    | Production must leave this false or absent. Automated payment tests set
    | it so they are not rehearsing the supervised allowlist. When false, an
    | empty allowlist or a missing/past expiry denies initiation even if
    | payments_enabled is true.
    |
    */
    'payments_unrestricted' => filter_var(env('CHECKOUT_PAYMENTS_UNRESTRICTED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Supervised initiation allowlist
    |--------------------------------------------------------------------------
    |
    | Empty: nobody may start a payment while unrestricted is false.
    | Non-empty: only those customer emails may start the one test order,
    | open its pay page, or call create-order for that order.
    | Signed Razorpay callbacks and webhooks stay reachable either way.
    |
    */
    'payments_allowed_emails' => env('CHECKOUT_PAYMENTS_ALLOWED_EMAILS', ''),

    /*
    |--------------------------------------------------------------------------
    | Supervised access expiry
    |--------------------------------------------------------------------------
    |
    | ISO-8601 timestamp. Initiation and pay-page resume stop at this instant
    | even if the operator session has disconnected and payments_enabled is
    | still true. A missing or unparseable value keeps initiation closed.
    | Signed settlement does not read this value.
    |
    */
    'payments_expires_at' => env('CHECKOUT_PAYMENTS_EXPIRES_AT', ''),

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

    /*
    |--------------------------------------------------------------------------
    | Refund recovery pagination
    |--------------------------------------------------------------------------
    |
    | One recovery scan reads at most this many pages of 100 refunds. The value
    | must be a positive integer from 1 through 100. Anything else uses 100.
    | A scan that reaches the ceiling leaves the refund unchanged.
    |
    */
    'refund_recovery_max_pages' => 100,
];
