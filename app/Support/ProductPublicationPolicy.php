<?php

namespace App\Support;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Single authoritative rule set for whether a product may appear on the
 * public storefront, in SEO surfaces, or in cart/checkout flows.
 */
class ProductPublicationPolicy
{
    public const LIVE_TEST_SKU_PREFIX = 'VA-LIVE-TEST-';

    /**
     * Shared prerequisites: active, classified, valid slug, active category.
     */
    public static function passesBasePublicationRules(?Product $product): bool
    {
        if (! $product) {
            return false;
        }

        if (! $product->is_active) {
            return false;
        }

        if (! filled($product->slug)) {
            return false;
        }

        if (! $product->isClassified()) {
            return false;
        }

        return self::hasActiveCategory($product);
    }

    public static function hasActiveCategory(Product $product): bool
    {
        $category = $product->relationLoaded('category')
            ? $product->category
            : $product->category()->first();

        return $category !== null && $category->is_active;
    }

    /**
     * SKU prefix marks the supervised payment-test item. It is not access control.
     * The direct URL and cart stay closed unless the signed-in customer is on
     * the allowlist and the expiry window is still open.
     */
    public static function isLiveTestItem(?Product $product): bool
    {
        if (! $product || ! is_string($product->sku) || $product->sku === '') {
            return false;
        }

        return str_starts_with(strtoupper($product->sku), self::LIVE_TEST_SKU_PREFIX);
    }

    public static function isPubliclyAccessible(?Product $product): bool
    {
        if (! self::passesBasePublicationRules($product)) {
            return false;
        }

        if (self::isLiveTestItem($product)) {
            return self::liveTestVisibleTo($product, Auth::user());
        }

        return (bool) $product->is_gallery_visible;
    }

    public static function liveTestVisibleTo(?Product $product, ?User $user): bool
    {
        return self::isLiveTestItem($product)
            && self::passesBasePublicationRules($product)
            && CheckoutPayments::windowOpen()
            && CheckoutPayments::emailAllowed($user);
    }

    /** Galleries, search, homepage sections, related products, internal lists. */
    public static function isGalleryListed(?Product $product): bool
    {
        if (! self::passesBasePublicationRules($product)) {
            return false;
        }

        return (bool) $product->is_gallery_visible;
    }

    /** Included in sitemap.xml product URLs. */
    public static function isSitemapListed(?Product $product): bool
    {
        if (! self::isGalleryListed($product)) {
            return false;
        }

        return $product->robots_index !== false;
    }

    /** Product / Offer JSON-LD and rich-result eligibility. */
    public static function isStructuredDataEligible(?Product $product): bool
    {
        if (! self::isPubliclyAccessible($product) || self::isLiveTestItem($product)) {
            return false;
        }

        if ($product->robots_index === false) {
            return false;
        }

        if ($product->isStudioItem() || ! $product->usesCheckoutFlow()) {
            return false;
        }

        return true;
    }

    /** Cart, Buy Now, and checkout eligibility (before price / section guards). */
    public static function isCartEligible(?Product $product): bool
    {
        if (! self::passesBasePublicationRules($product)) {
            return false;
        }

        if (self::isLiveTestItem($product)) {
            return self::liveTestVisibleTo($product, Auth::user()) && $product->usesCheckoutFlow();
        }

        if (! $product->is_gallery_visible) {
            return false;
        }

        return $product->usesCheckoutFlow();
    }

    /** Meta robots directive when the product page returns HTTP 200. */
    public static function robotsMeta(?Product $product): ?string
    {
        if (! self::isPubliclyAccessible($product)) {
            return null;
        }

        if (self::isLiveTestItem($product) || $product->robots_index === false) {
            return 'noindex,follow';
        }

        return null;
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyGalleryScope(Builder $query): Builder
    {
        return self::applyClassifiedScope(
            $query
                ->where('is_active', true)
                ->where('is_gallery_visible', true)
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->whereHas('category', fn (Builder $category) => $category->where('is_active', true))
        );
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyPublicAccessScope(Builder $query): Builder
    {
        return self::applyGalleryScope($query);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applySitemapScope(Builder $query): Builder
    {
        return self::applyGalleryScope($query)->where('robots_index', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private static function applyClassifiedScope(Builder $query): Builder
    {
        return $query
            ->whereNotNull('section')
            ->whereIn('section', Product::SECTIONS)
            ->whereNotNull('purchase_mode')
            ->whereIn('purchase_mode', Product::PURCHASE_MODES)
            ->whereNotNull('pricing_type')
            ->whereIn('pricing_type', Product::PRICING_TYPES)
            ->whereNotNull('category_id');
    }
}
