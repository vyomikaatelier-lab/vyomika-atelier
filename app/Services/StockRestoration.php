<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\OrderRefundLine;
use App\Models\Product;
use RuntimeException;

class StockRestoration
{
    /**
     * Restore each pending line at most once. Caller holds the order row lock
     * inside a transaction. Product rows are locked in ascending product id.
     */
    public function restore(Order $order, OrderRefund $refund): void
    {
        $lines = OrderRefundLine::query()
            ->where('order_refund_id', $refund->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($lines->isEmpty()) {
            return;
        }

        if ($order->stock_deducted_at === null) {
            $this->markSkipped($lines);

            return;
        }

        $items = OrderItem::query()
            ->whereIn('id', $lines->pluck('order_item_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $needed = [];

        foreach ($lines as $line) {
            if ($line->stock_restoration !== OrderRefundLine::STOCK_PENDING) {
                continue;
            }

            $item = $items->get($line->order_item_id);

            if (! $item || ! $item->product_id) {
                $this->skipLine($line);

                continue;
            }

            $alreadyRestored = (int) OrderRefundLine::query()
                ->where('order_item_id', $item->id)
                ->where('stock_restoration', OrderRefundLine::STOCK_RESTORED)
                ->sum('quantity');

            if ($alreadyRestored + (int) $line->quantity > (int) $item->quantity) {
                throw new RuntimeException('stock_restore_exceeds_deducted');
            }

            $needed[(int) $item->product_id] = ($needed[(int) $item->product_id] ?? 0) + (int) $line->quantity;
        }

        $productIds = collect(array_keys($needed))->sort()->values();
        $lockedProducts = [];

        foreach ($productIds as $productId) {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->first();

            if ($product) {
                $lockedProducts[(int) $product->id] = true;
            }
        }

        foreach ($lockedProducts as $productId => $present) {
            Product::query()->whereKey($productId)->increment('stock', $needed[$productId]);
        }

        foreach ($lines as $line) {
            if ($line->stock_restoration !== OrderRefundLine::STOCK_PENDING) {
                continue;
            }

            $item = $items->get($line->order_item_id);
            $productId = (int) ($item->product_id ?? 0);

            if (! isset($lockedProducts[$productId])) {
                $this->skipLine($line);

                continue;
            }

            OrderRefundLine::query()
                ->whereKey($line->getKey())
                ->where('stock_restoration', OrderRefundLine::STOCK_PENDING)
                ->update([
                    'stock_restoration' => OrderRefundLine::STOCK_RESTORED,
                    'stock_restored_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OrderRefundLine>  $lines
     */
    private function markSkipped($lines): void
    {
        foreach ($lines as $line) {
            if ($line->stock_restoration === OrderRefundLine::STOCK_PENDING) {
                $this->skipLine($line);
            }
        }
    }

    private function skipLine(OrderRefundLine $line): void
    {
        OrderRefundLine::query()
            ->whereKey($line->getKey())
            ->where('stock_restoration', OrderRefundLine::STOCK_PENDING)
            ->update([
                'stock_restoration' => OrderRefundLine::STOCK_SKIPPED,
                'updated_at' => now(),
            ]);

        $line->stock_restoration = OrderRefundLine::STOCK_SKIPPED;
    }
}
