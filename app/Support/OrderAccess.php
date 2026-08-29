<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;

/**
 * Limits who may view or complete payment for a storefront order.
 *
 * Owned orders (non-null user_id) are exclusive to that customer. Session,
 * email and phone must never override a foreign owner. Legacy orders with
 * null user_id are authorized only by an exact placing-session match on
 * checkout_order_id — never by email or phone matching.
 */
class OrderAccess
{
    public const SESSION_KEY = 'checkout_order_id';

    public static function remember(Order $order): void
    {
        session([self::SESSION_KEY => $order->id]);
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function canAccess(Order $order): bool
    {
        if ($order->user_id !== null) {
            $user = Auth::user();

            return $user !== null
                && (int) $order->user_id === (int) $user->id;
        }

        return (int) session(self::SESSION_KEY) === (int) $order->id;
    }
}
