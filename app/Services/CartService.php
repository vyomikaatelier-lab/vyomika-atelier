<?php

namespace App\Services;

use App\Models\Product;
use App\Support\CartGuard;
use App\Support\FinishSwatches;
use Illuminate\Support\Collection;

class CartService
{
    private const SESSION_KEY = 'cart';

    private const BUY_NOW_KEY = 'buy_now';

    public const CHECKOUT_SOURCE_KEY = 'checkout_source';

    /**
     * @return array{quantity: int, finish_slug: ?string, finish_name: ?string, size_label: ?string, unit_price: ?float}
     */
    private function normalizeLine(mixed $value): array
    {
        if (is_array($value)) {
            return [
                'quantity' => max(1, (int) ($value['quantity'] ?? 1)),
                'finish_slug' => filled($value['finish_slug'] ?? null) ? (string) $value['finish_slug'] : null,
                'finish_name' => filled($value['finish_name'] ?? null) ? (string) $value['finish_name'] : null,
                'size_label' => filled($value['size_label'] ?? null) ? (string) $value['size_label'] : null,
                'unit_price' => filled($value['unit_price'] ?? null) ? round((float) $value['unit_price'], 2) : null,
            ];
        }

        return [
            'quantity' => max(1, (int) $value),
            'finish_slug' => null,
            'finish_name' => null,
            'size_label' => null,
            'unit_price' => null,
        ];
    }

    /**
     * Current cart contents, revalidated against CartGuard on every read.
     */
    public function all(): Collection
    {
        $cart = session(self::SESSION_KEY, []);

        if ($cart === []) {
            return collect();
        }

        $products = Product::with('category')->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $invalidIds = [];

        $items = collect($cart)->map(function ($line, $productId) use ($products, &$invalidIds) {
            $product = $products->get($productId);
            $normalized = $this->normalizeLine($line);

            if (! CartGuard::isEligible($product, $normalized['size_label'])) {
                $invalidIds[] = $productId;

                return null;
            }

            $unitPrice = CartGuard::trustedUnitPrice($product, $normalized['size_label']);

            if ($unitPrice === null) {
                $invalidIds[] = $productId;

                return null;
            }

            return [
                'product' => $product,
                'quantity' => $normalized['quantity'],
                'finish_slug' => $normalized['finish_slug'],
                'finish_name' => $normalized['finish_name'],
                'size_label' => $normalized['size_label'],
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $normalized['quantity'],
            ];
        })->filter()->values();

        if ($invalidIds !== []) {
            $this->removeMany($invalidIds);
        }

        return $items;
    }

    public function add(Product $product, int $quantity = 1, ?string $finishSlug = null, ?string $sizeLabel = null): void
    {
        $cart = session(self::SESSION_KEY, []);
        $existingQty = isset($cart[$product->id])
            ? $this->normalizeLine($cart[$product->id])['quantity']
            : 0;
        $existing = $this->normalizeLine($cart[$product->id] ?? null);
        $finish = FinishSwatches::resolve($finishSlug ?? $existing['finish_slug']);
        $resolvedSizeLabel = $sizeLabel ?? $existing['size_label'];
        $size = $product->hasSizeOptions() && filled($resolvedSizeLabel)
            ? $this->resolveSizeSelection($product, $resolvedSizeLabel)
            : ['label' => null, 'unit_price' => CartGuard::trustedUnitPrice($product, null) ?? 0.0];

        $cart[$product->id] = [
            'quantity' => $existingQty + $quantity,
            'finish_slug' => $finish['slug'],
            'finish_name' => $finish['name'],
            'size_label' => $size['label'],
            'unit_price' => $size['unit_price'],
        ];

        session([self::SESSION_KEY => $cart]);
    }

    /** @param array<int, int|string> $productIds */
    public function removeMany(array $productIds): void
    {
        $cart = session(self::SESSION_KEY, []);
        foreach ($productIds as $productId) {
            unset($cart[$productId]);
        }
        session([self::SESSION_KEY => $cart]);
    }

    public function update(Product $product, int $quantity): void
    {
        $cart = session(self::SESSION_KEY, []);

        if ($quantity <= 0) {
            unset($cart[$product->id]);

            session([self::SESSION_KEY => $cart]);

            return;
        }

        $existing = $this->normalizeLine($cart[$product->id] ?? ['quantity' => $quantity]);
        $cart[$product->id] = [
            'quantity' => $quantity,
            'finish_slug' => $existing['finish_slug'],
            'finish_name' => $existing['finish_name'],
            'size_label' => $existing['size_label'],
            'unit_price' => $existing['unit_price'],
        ];

        session([self::SESSION_KEY => $cart]);
    }

    public function remove(Product $product): void
    {
        $cart = session(self::SESSION_KEY, []);
        unset($cart[$product->id]);
        session([self::SESSION_KEY => $cart]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function setBuyNow(Product $product, int $quantity = 1, ?string $finishSlug = null, ?string $sizeLabel = null): void
    {
        $finish = FinishSwatches::resolve($finishSlug);
        $size = $product->hasSizeOptions() && filled($sizeLabel)
            ? $this->resolveSizeSelection($product, $sizeLabel)
            : ['label' => null, 'unit_price' => CartGuard::trustedUnitPrice($product, null) ?? 0.0];

        session([self::BUY_NOW_KEY => [
            'product_id' => $product->id,
            'quantity' => max(1, $quantity),
            'finish_slug' => $finish['slug'],
            'finish_name' => $finish['name'],
            'size_label' => $size['label'],
            'created_at' => now()->timestamp,
        ]]);
    }

    public function clearBuyNow(): void
    {
        session()->forget(self::BUY_NOW_KEY);
    }

    public function hasBuyNow(): bool
    {
        return $this->buyNowItems()->isNotEmpty();
    }

    /**
     * Items charged at checkout: the Buy Now snapshot when present, otherwise the cart.
     */
    public function checkoutItems(): Collection
    {
        $buyNow = $this->buyNowItems();

        return $buyNow->isNotEmpty() ? $buyNow : $this->all();
    }

    public function checkoutSubtotal(): float
    {
        return $this->checkoutItems()->sum('line_total');
    }

    public function checkoutIsEmpty(): bool
    {
        return $this->checkoutItems()->isEmpty();
    }

    /**
     * @return Collection<int, array{product: Product, quantity: int, finish_slug: ?string, finish_name: ?string, size_label: ?string, unit_price: float, line_total: float}>
     */
    public function buyNowItems(): Collection
    {
        $line = session(self::BUY_NOW_KEY);
        if (! is_array($line) || empty($line['product_id'])) {
            return collect();
        }

        $createdAt = (int) ($line['created_at'] ?? 0);
        $ttl = max(1, (int) config('shop.buy_now_ttl_minutes', 120)) * 60;
        if ($createdAt > 0 && (now()->timestamp - $createdAt) > $ttl) {
            $this->clearBuyNow();

            return collect();
        }

        $product = Product::with('category')->find($line['product_id']);
        $normalized = $this->normalizeLine($line);

        if (! CartGuard::isEligible($product, $normalized['size_label'])) {
            $this->clearBuyNow();

            return collect();
        }

        $unitPrice = CartGuard::trustedUnitPrice($product, $normalized['size_label']);

        if ($unitPrice === null) {
            $this->clearBuyNow();

            return collect();
        }

        return collect([[
            'product' => $product,
            'quantity' => $normalized['quantity'],
            'finish_slug' => $normalized['finish_slug'],
            'finish_name' => $normalized['finish_name'],
            'size_label' => $normalized['size_label'],
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $normalized['quantity'],
        ]]);
    }

    public function count(): int
    {
        return (int) collect(session(self::SESSION_KEY, []))
            ->sum(fn ($line) => $this->normalizeLine($line)['quantity']);
    }

    public function subtotal(): float
    {
        return $this->all()->sum('line_total');
    }

    public function isEmpty(): bool
    {
        return $this->all()->isEmpty();
    }

    /**
     * @return array{label: ?string, unit_price: float}
     */
    private function resolveSizeSelection(Product $product, ?string $sizeLabel): array
    {
        if (! $product->hasSizeOptions()) {
            return [
                'label' => null,
                'unit_price' => CartGuard::trustedUnitPrice($product, null) ?? 0.0,
            ];
        }

        $option = $product->resolveSizeOption($sizeLabel);
        $label = $option['label'] ?? null;

        return [
            'label' => $label,
            'unit_price' => CartGuard::trustedUnitPrice($product, $label) ?? 0.0,
        ];
    }
}
