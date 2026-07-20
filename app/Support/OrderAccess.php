<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;

/**
 * Limits who may view or complete payment for a storefront order.
 * Prevents enumerating /checkout/pay/{id} and /checkout/success/{id}.
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
        if ((int) session(self::SESSION_KEY) === (int) $order->id) {
            return true;
        }

        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($order->customer_email && strcasecmp($order->customer_email, (string) $user->email) === 0) {
            return true;
        }

        if ($order->user_id && (int) $order->user_id === (int) $user->id) {
            return true;
        }

        if ($user->mobile && $order->customer_phone) {
            $mobile = preg_replace('/\D/', '', (string) $user->mobile);
            $phone = preg_replace('/\D/', '', (string) $order->customer_phone);

            if ($mobile !== '' && $phone !== '' && str_ends_with($phone, $mobile)) {
                return true;
            }
        }

        return false;
    }
}
