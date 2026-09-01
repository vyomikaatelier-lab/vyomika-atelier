<?php

namespace Tests\Concerns;

use App\Models\Product;
use App\Services\CartService;
use App\Support\FinishSwatches;

trait InteractsWithCartSession
{
    protected function canonicalFinishSlug(): string
    {
        return FinishSwatches::defaultSlug();
    }

    /** @param  array<string, mixed>  $overrides */
    protected function purchaseInput(array $overrides = []): array
    {
        return array_merge([
            'quantity' => 1,
            'finish_slug' => $this->canonicalFinishSlug(),
        ], $overrides);
    }

    protected function sessionCartLine(Product $product, ?string $sizeLabel = null, ?string $finishSlug = null): ?array
    {
        $cart = session('cart', []);
        $finishSlug = $finishSlug ?? FinishSwatches::defaultSlug();
        $key = CartService::lineKey((int) $product->id, $sizeLabel, $finishSlug);

        if (isset($cart[$key]) && is_array($cart[$key])) {
            return $cart[$key];
        }

        if (isset($cart[$product->id])) {
            $line = $cart[$product->id];

            return is_array($line) ? $line : ['quantity' => (int) $line];
        }

        foreach ($cart as $line) {
            if (! is_array($line)) {
                continue;
            }

            $lineProductId = isset($line['product_id']) ? (int) $line['product_id'] : null;
            if ($lineProductId !== (int) $product->id) {
                continue;
            }

            if (($line['size_label'] ?? null) !== $sizeLabel) {
                continue;
            }

            if (($line['finish_slug'] ?? null) !== $finishSlug) {
                continue;
            }

            return $line;
        }

        return null;
    }

    protected function sessionCartHasProduct(Product $product): bool
    {
        return $this->sessionCartLine($product) !== null
            || app(CartService::class)->containsProduct((int) $product->id);
    }
}
