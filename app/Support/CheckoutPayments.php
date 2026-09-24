<?php

namespace App\Support;

class CheckoutPayments
{
    public const UNAVAILABLE_MESSAGE = 'Checkout is temporarily unavailable. Your cart is saved. Please try again later.';

    public static function enabled(): bool
    {
        return (bool) config('checkout.payments_enabled', false);
    }
}
