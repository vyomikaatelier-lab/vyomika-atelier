<?php

namespace App\Support;

use App\Models\Product;

/**
 * Single storefront price formatter. Never invents amounts, never renders ₹0,
 * and never uses a compare price unless it is a genuine higher list price.
 */
class StorefrontPrice
{
    public static function formatInr(float|int|string|null $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $value = (float) $amount;
        if ($value <= 0) {
            return null;
        }

        return '₹'.number_format($value, 0);
    }

    /**
     * Readable unit for public prices, mapped from stored pricing_type values.
     */
    public static function unitSuffix(?string $pricingType): ?string
    {
        $type = strtolower(trim((string) $pricingType));

        return match ($type) {
            Product::PRICING_SQUARE_FOOT, 'sqft', 'sq_ft', 'per_sqft', 'per_sq_ft', 'square-foot' => '/ sq ft',
            'panel', 'per_panel' => '/ panel',
            'piece', 'unit', 'per_piece', 'per_unit', 'pc', 'per_pc' => '/ pc',
            default => null,
        };
    }

    public static function listingLabel(?Product $product): ?string
    {
        if (! $product) {
            return null;
        }

        $amount = $product->listingPrice();
        $formatted = self::formatInr($amount);

        if ($formatted === null) {
            return $product->isStudioItem() || $product->resolvedPricingType() === Product::PRICING_QUOTATION_ONLY
                ? 'Price on request'
                : null;
        }

        $prefix = self::usesFromPrefix($product) ? 'From ' : '';
        $suffix = self::publicUnitSuffix($product);

        return $prefix.$formatted.($suffix ? ' '.$suffix : '');
    }

    public static function compareLabel(?Product $product): ?string
    {
        if (! $product || $product->hasSizeOptions()) {
            return null;
        }

        $current = $product->listingPrice();
        $compare = $product->compare_price;

        if ($compare === null || $current <= 0 || (float) $compare <= $current) {
            return null;
        }

        return self::formatInr((float) $compare);
    }

    public static function usesFromPrefix(Product $product): bool
    {
        if ($product->hasSizeOptions()) {
            return true;
        }

        if (self::isFixedUnitPricingType($product->pricing_type)) {
            return false;
        }

        return $product->resolvedPricingType() === Product::PRICING_SQUARE_FOOT
            || in_array(strtolower((string) $product->pricing_type), ['sqft', 'sq_ft', 'per_sqft', 'per_sq_ft', 'square-foot'], true);
    }

    public static function publicUnitSuffix(Product $product): ?string
    {
        $fromStored = self::unitSuffix($product->pricing_type);
        if ($fromStored) {
            return $fromStored;
        }

        $fromType = self::unitSuffix($product->resolvedPricingType());
        if ($fromType) {
            return $fromType;
        }

        if ($product->usesCheckoutFlow() && $product->isDoorHandleProduct()) {
            return '/ pc';
        }

        return null;
    }

    private static function isFixedUnitPricingType(?string $pricingType): bool
    {
        return in_array(strtolower(trim((string) $pricingType)), [
            'panel', 'per_panel', 'piece', 'unit', 'per_piece', 'per_unit', 'pc', 'per_pc',
        ], true);
    }
}
