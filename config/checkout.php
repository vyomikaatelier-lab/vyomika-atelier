<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Require a verified customer phone number at checkout
    |--------------------------------------------------------------------------
    |
    | WhatsApp customer OTP is unfinished and is not payment verification.
    | Keep this false so authenticated customers can complete Razorpay
    | checkout without phone_verified_at. Set true only after OTP is ready.
    |
    */
    'require_verified_phone' => filter_var(
        env('CHECKOUT_REQUIRE_VERIFIED_PHONE', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
