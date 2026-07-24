<?php

namespace App\Services;

use App\Models\Product;
use App\Support\CartGuard;
use App\Support\FinishSwatches;
use Illuminate\Support\Collection;

class CartService
{
    private const SESSION_KEY = 'cart';

    /**
     * @return array{quantity: int, finish_slug: ?string, finish_name: ?string}
     */
    private function normalizeLine(mixed $value): array
    {
        if (is_array($value)) {
            return [
                'quantity' => max(1, (int) ($value['quantity'] ?? 1)),
                'finish_slug' => filled($value['finish_slug'] ?? null) ? (string) $value['finish_slug'] : null,
                'finish_name' => filled($value['finish_name'] ?? null) ? (string) $value['finish_name'] : null,
            ];
        }

        return [
            'quantity' => max(1, (int) $value),
            'finish_slug' => null,
            'finish_name' => null,
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

            if (! CartGuard::isEligible($product)) {
                $invalidIds[] = $productId;

                return null;
            }

            return [
                'product' => $product,
                'quantity' => $normalized['quantity'],
                'finish_slug' => $normalized['finish_slug'],
                'finish_name' => $normalized['finish_name'],
                'line_total' => $product->price * $normalized['quantity'],
            ];
        })->filter()->values();

        if ($invalidIds !== []) {
            $this->removeMany($invalidIds);
        }

        return $items;
    }

    public function add(Product $product, int $quantity = 1, ?string $finishSlug = null): void
    {
        $cart = session(self::SESSION_KEY, []);
        $existingQty = isset($cart[$product->id])
            ? $this->normalizeLine($cart[$product->id])['quantity']
            : 0;
        $existing = $this->normalizeLine($cart[$product->id] ?? null);
        $finish = FinishSwatches::resolve($finishSlug ?? $existing['finish_slug']);

        $cart[$product->id] = [
            'quantity' => $existingQty + $quantity,
            'finish_slug' => $finish['slug'],
            'finish_name' => $finish['name'],
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
}
