<?php

namespace App\Support;

use App\Models\User;

/**
 * Single gate for checkout eligibility — mirrors middleware rules so
 * CheckoutController can re-check at order-creation time.
 */
class CheckoutCustomer
{
    public const MSG_SIGN_IN = 'Please sign in to complete your purchase.';

    public const MSG_VERIFY = 'Please verify your mobile number before checkout.';

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
            return self::MSG_SIGN_IN;
        }

        if (! $user->is_active) {
            return self::MSG_DISABLED;
        }

        if (! $user->hasVerifiedPhone()) {
            return self::MSG_VERIFY;
        }

        return null;
    }
}
