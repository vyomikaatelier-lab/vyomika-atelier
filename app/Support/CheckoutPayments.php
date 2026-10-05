<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

class CheckoutPayments
{
    public const UNAVAILABLE_MESSAGE = 'Checkout is temporarily unavailable. Your cart is saved. Please try again later.';

    public const SNAPSHOT_SUPPRESS_NOTIFICATIONS = 'suppress_notifications';

    public static function enabled(): bool
    {
        return (bool) config('checkout.payments_enabled', false);
    }

    /**
     * @return list<string>
     */
    public static function allowedEmails(): array
    {
        $raw = config('checkout.payments_allowed_emails', '');

        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/[\s,]+/', (string) $raw) ?: [];
        }

        $emails = [];
        foreach ($parts as $part) {
            $email = strtolower(trim((string) $part));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    public static function restrictsInitiation(): bool
    {
        return self::allowedEmails() !== [];
    }

    public static function initiationDenied(?User $user): bool
    {
        return self::enabled()
            && self::restrictsInitiation()
            && ! self::canInitiate($user);
    }

    public static function canInitiate(?User $user): bool
    {
        if (! self::enabled()) {
            return false;
        }

        if (! CheckoutCustomer::canCheckout($user)) {
            return false;
        }

        if (! self::restrictsInitiation()) {
            return true;
        }

        return in_array(strtolower((string) $user->email), self::allowedEmails(), true);
    }

    public static function shouldSuppressOrderMail(?User $user): bool
    {
        return self::restrictsInitiation()
            && $user
            && in_array(strtolower((string) $user->email), self::allowedEmails(), true);
    }

    public static function orderMailSuppressed(Order $order): bool
    {
        $snapshot = $order->shipping_snapshot;

        return is_array($snapshot) && ($snapshot[self::SNAPSHOT_SUPPRESS_NOTIFICATIONS] ?? false) === true;
    }
}
