<?php

namespace Tests\Concerns;

use App\Models\Product;
use App\Services\CartService;
use App\Support\FinishSwatches;

trait InteractsWithCartSession
{
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

        foreach ($cart as $sessionKey => $line) {
            if (! is_array($line)) {
                continue;
            }

            if ((string) $sessionKey === (string) $product->id || str_starts_with((string) $sessionKey, $product->id.'|')) {
                if (($line['size_label'] ?? null) !== $sizeLabel) {
                    continue;
                }

                if (($line['finish_slug'] ?? null) !== $finishSlug) {
                    continue;
                }

                return $line;
            }
        }

        return null;
    }

    protected function sessionCartHasProduct(Product $product): bool
    {
        return $this->sessionCartLine($product) !== null
            || app(CartService::class)->containsProduct((int) $product->id);
    }
}
