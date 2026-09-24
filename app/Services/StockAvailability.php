<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class StockAvailability
{
    /** Quantity reserved by other unpaid pending orders (excluding a given order). */
    public static function reservedQuantity(int $productId, ?int $excludeOrderId = null): int
    {
        return (int) OrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', function ($query) use ($excludeOrderId) {
                $query->where('status', 'pending')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });

                if ($excludeOrderId) {
                    $query->where('id', '!=', $excludeOrderId);
                }
            })
            ->sum('quantity');
    }

    public static function availableForProduct(Product $product, ?int $excludeOrderId = null): int
    {
        $reserved = self::reservedQuantity($product->id, $excludeOrderId);

        return max(0, $product->stock - $reserved);
    }

    /**
     * Deduct every line or none. Returns a reason code when fulfilment cannot
     * proceed. Caller must already hold a transaction and the order row lock.
     */
    public static function deductIfAvailable(Order $order): ?string
    {
        if ($order->stock_deducted_at !== null) {
            return null;
        }

        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return 'no_line_items';
        }

        $items = $order->items->sortBy(fn ($item) => sprintf('%020d', (int) $item->product_id))->values();

        foreach ($items as $item) {
            if (! $item->product_id) {
                return 'stock_unverified';
            }
        }

        $products = [];
        foreach ($items->pluck('product_id')->unique()->sort()->values() as $productId) {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->first();

            if (! $product) {
                return 'stock_unverified';
            }

            $products[$product->id] = $product;
        }

        $needed = [];
        foreach ($items as $item) {
            $needed[$item->product_id] = ($needed[$item->product_id] ?? 0) + (int) $item->quantity;
        }

        foreach ($needed as $productId => $quantity) {
            if ($quantity > self::availableForProduct($products[$productId], $order->id)) {
                return 'insufficient_stock';
            }
        }

        foreach ($needed as $productId => $quantity) {
            Product::query()->whereKey($productId)->decrement('stock', $quantity);
        }

        $order->forceFill(['stock_deducted_at' => now()])->save();

        return null;
    }

    /**
     * Deduct stock for a paid order once. Safe to call on payment callback retries.
     *
     * @return bool True when stock was deducted or was already deducted for this order.
     */
    public static function deductForPaidOrder(Order $order): bool
    {
        return (bool) DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked) {
                return false;
            }

            $blocker = self::deductIfAvailable($locked);

            if ($blocker !== null) {
                throw new \RuntimeException($blocker);
            }

            return true;
        });
    }
}
