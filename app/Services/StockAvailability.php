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
     * Deduct stock for a paid order once. Safe to call on payment callback retries.
     *
     * @return bool True when stock was deducted or was already deducted for this order.
     */
    public static function deductForPaidOrder(Order $order): bool
    {
        if ($order->stock_deducted_at !== null) {
            return true;
        }

        return (bool) DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked?->stock_deducted_at !== null) {
                return true;
            }

            $order->loadMissing('items.product');

            foreach ($order->items as $item) {
                if (! $item->product_id || ! $item->product) {
                    continue;
                }

                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();

                if (! $product) {
                    continue;
                }

                $available = self::availableForProduct($product, $order->id);

                if ($item->quantity > $available) {
                    throw new \RuntimeException("Insufficient stock for {$item->product_name}.");
                }
            }

            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::query()->whereKey($item->product_id)->decrement('stock', $item->quantity);
                }
            }

            $locked->forceFill(['stock_deducted_at' => now()])->save();

            return true;
        });
    }
}
