<?php

namespace App\Support;

use App\Models\Product;

/**
 * Single authoritative gate deciding whether a product may enter the
 * cart/checkout flow. Used by CartController, CartService and
 * CheckoutController so the rule is enforced server-side no matter which
 * entry point is hit (including forged/direct POST requests).
 *
 * Rules:
 *  - Only active Shop products with purchase_mode=checkout may enter cart.
 *  - Studio items never enter cart/checkout (enquiry only).
 *  - Railings never enter cart (quotation only, handled at /railings).
 */
class CartGuard
{
    public const MSG_STUDIO = 'This Studio item is available through a custom enquiry only.';

    public const MSG_RAILINGS = 'Railings require a project quotation.';

    public const MSG_INACTIVE = 'This product is currently unavailable.';

    /**
     * Returns null when the product is eligible for cart/checkout, or a
     * user-facing rejection message when it is not.
     */
    public static function checkoutEligibility(?Product $product): ?string
    {
        if (! $product) {
            return self::MSG_INACTIVE;
        }

        if ($product->isStudioItem()) {
            return self::MSG_STUDIO;
        }

        if ($product->isRailingItem()) {
            return self::MSG_RAILINGS;
        }

        if (! $product->is_active) {
            return self::MSG_INACTIVE;
        }

        if (! $product->usesCheckoutFlow()) {
            return self::MSG_INACTIVE;
        }

        return null;
    }

    public static function isEligible(?Product $product): bool
    {
        return self::checkoutEligibility($product) === null;
    }
}
