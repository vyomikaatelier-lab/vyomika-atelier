<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class CartService
{
    private const SESSION_KEY = 'cart';

    public function all(): Collection
    {
        $cart = session(self::SESSION_KEY, []);
        $products = Product::whereIn('id', array_keys($cart))->get()->keyBy('id');

        return collect($cart)->map(function ($quantity, $productId) use ($products) {
            $product = $products->get($productId);
            if (! $product) {
                return null;
            }

            return [
                'product' => $product,
                'quantity' => (int) $quantity,
                'line_total' => $product->price * $quantity,
            ];
        })->filter()->values();
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $cart = session(self::SESSION_KEY, []);
        $cart[$product->id] = ($cart[$product->id] ?? 0) + $quantity;
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
