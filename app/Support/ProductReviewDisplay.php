<?php

namespace App\Support;

use App\Models\Product;

/**
 * Approved storefront review data only. Never fabricates ratings.
 */
class ProductReviewDisplay
{
    /**
     * @return array{average: float, count: int}|null
     */
    public static function summary(?Product $product): ?array
    {
        if (! $product) {
            return null;
        }

        // No approved public review store exists yet; expose only when real data is present.
        $count = (int) ($product->review_count ?? 0);
        $average = isset($product->review_average) ? (float) $product->review_average : null;

        if ($count <= 0 || $average === null || $average <= 0) {
            return null;
        }

        return [
            'average' => round($average, 1),
            'count' => $count,
        ];
    }
}
