<?php

namespace App\Support;

use App\Models\User;

/**
 * Single gate for checkout eligibility — mirrors middleware rules so
 * CheckoutController can re-check at order-creation time.
 *
 * Customer WhatsApp OTP is retired. Authenticated, active, non-admin
 * customers may checkout without phone_verified_at. Delivery phone is
 * collected and validated on the checkout form; it is not payment proof.
 */
class CheckoutCustomer
{
    public const MSG_SIGN_IN = 'Please sign in to complete your purchase.';

    public const MSG_ADMIN = 'Admin accounts cannot checkout. Sign out and use a customer account to buy.';

    public const MSG_DISABLED = 'This account has been disabled. Contact the studio for assistance.';

    public static function canCheckout(?User $user): bool
    {
        return self::denialMessage($user) === null;
    }

    public static function denialMessage(?User $user): ?string
    {
        if (! $user) {
            return self::MSG_SIGN_IN;
        }

        if ($user->isAdmin()) {
            return self::MSG_ADMIN;
        }

        if (! $user->is_active) {
            return self::MSG_DISABLED;
        }

        return null;
    }
}
