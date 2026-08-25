<?php

namespace App\Support;

use App\Models\Product;

/**
 * Single authoritative gate deciding whether a product may enter the
 * cart/checkout flow. Used by CartController, CartService and
 * CheckoutController so the rule is enforced server-side no matter which
 * entry point is hit (including forged/direct POST requests).
 */
class CartGuard
{
    public const MSG_STUDIO = 'This Studio item is available through a custom enquiry only.';

    public const MSG_RAILINGS = 'Railings require a project quotation.';

    public const MSG_INACTIVE = 'This product is currently unavailable.';

    public const MSG_NO_PRICE = 'This product is available by enquiry only. Please contact the studio for pricing.';

    public const MSG_VARIANT_REQUIRED = 'Please select a valid size or variant before continuing.';

    /**
     * Trusted unit price from the database only — never browser/session input.
     */
    public static function trustedUnitPrice(?Product $product, ?string $sizeLabel = null): ?float
    {
        if (! $product) {
            return null;
        }

        if ($product->resolvedPricingType() === Product::PRICING_QUOTATION_ONLY) {
            return null;
        }

        if ($product->hasSizeOptions()) {
            if (! filled($sizeLabel)) {
                return null;
            }

            $option = $product->resolveSizeOption($sizeLabel);
            if (! $option) {
                return null;
            }

            $price = (float) ($option['price'] ?? 0);

            return $price > 0 ? $price : null;
        }

        $price = (float) $product->price;

        return $price > 0 ? $price : null;
    }

    public static function hasTrustedListingPrice(?Product $product): bool
    {
        if (! $product) {
            return false;
        }

        return $product->listingPrice() > 0;
    }

    /**
     * Returns null when the product is eligible for cart/checkout, or a
     * user-facing rejection message when it is not.
     */
    public static function checkoutEligibility(?Product $product, ?string $sizeLabel = null): ?string
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

        if (! $product->is_gallery_visible) {
            return self::MSG_INACTIVE;
        }

        if (! $product->usesCheckoutFlow()) {
            return self::MSG_INACTIVE;
        }

        if ($product->resolvedPricingType() === Product::PRICING_QUOTATION_ONLY) {
            return self::MSG_NO_PRICE;
        }

        if ($product->hasSizeOptions() && ! filled($sizeLabel)) {
            return self::MSG_VARIANT_REQUIRED;
        }

        if (self::trustedUnitPrice($product, $sizeLabel) === null) {
            return self::MSG_NO_PRICE;
        }

        return null;
    }

    public static function isEligible(?Product $product, ?string $sizeLabel = null): bool
    {
        return self::checkoutEligibility($product, $sizeLabel) === null;
    }

    /** Buy Now on gallery/search cards (no inline variant picker). */
    public static function canDisplayBuyNow(?Product $product): bool
    {
        if (! $product || ! $product->inStock()) {
            return false;
        }

        if (! self::isEligible($product) || ! self::hasTrustedListingPrice($product)) {
            return false;
        }

        return ! $product->hasSizeOptions();
    }

    /** Add to Bag / Buy Now block on the product-detail page. */
    public static function canPurchaseOnPdp(?Product $product): bool
    {
        if (! $product || ! $product->inStock()) {
            return false;
        }

        if ($product->isStudioItem() || $product->isRailingItem()) {
            return false;
        }

        if (! $product->is_active || ! $product->is_gallery_visible || ! $product->usesCheckoutFlow()) {
            return false;
        }

        if ($product->resolvedPricingType() === Product::PRICING_QUOTATION_ONLY) {
            return false;
        }

        if ($product->hasSizeOptions()) {
            foreach ($product->normalizedSizeOptions() as $option) {
                if ((float) ($option['price'] ?? 0) > 0) {
                    return true;
                }
            }

            return false;
        }

        return self::hasTrustedListingPrice($product);
    }
}
