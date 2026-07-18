<?php

namespace App\Services;

use App\Models\Product;
use App\Support\CartGuard;
use Illuminate\Support\Collection;

class CartService
{
    private const SESSION_KEY = 'cart';

    /**
     * Current cart contents, revalidated against CartGuard on every read.
     * Any item that is no longer eligible (deactivated, reclassified as
     * Studio/Railings, or deleted) is silently dropped from the session so
     * legacy/invalid cart items never reach checkout or order creation.
     */
    public function all(): Collection
    {
        $cart = session(self::SESSION_KEY, []);

        if ($cart === []) {
            return collect();
        }

        $products = Product::with('category')->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $invalidIds = [];

        $items = collect($cart)->map(function ($quantity, $productId) use ($products, &$invalidIds) {
            $product = $products->get($productId);

            if (! CartGuard::isEligible($product)) {
                $invalidIds[] = $productId;

                return null;
            }

            return [
                'product' => $product,
                'quantity' => (int) $quantity,
                'line_total' => $product->price * $quantity,
            ];
        })->filter()->values();

        if ($invalidIds !== []) {
            $this->removeMany($invalidIds);
        }

        return $items;
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $cart = session(self::SESSION_KEY, []);
        $cart[$product->id] = ($cart[$product->id] ?? 0) + $quantity;
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
        } else {
            $cart[$product->id] = $quantity;
        }

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

    public function count(): int
    {
        return (int) collect(session(self::SESSION_KEY, []))->sum();
    }

    public function subtotal(): float
    {
        return $this->all()->sum('line_total');
    }

    public function isEmpty(): bool
    {
        return $this->all()->isEmpty();
    }
}
