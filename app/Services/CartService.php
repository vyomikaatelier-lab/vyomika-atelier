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

    public const NOTICE_KEY = 'cart_notice';

    /**
     * Stable cart line identity: product + validated size + validated finish.
     */
    public static function lineKey(int $productId, ?string $sizeLabel, ?string $finishSlug): string
    {
        return $productId.'|'.(string) $sizeLabel.'|'.(string) $finishSlug;
    }

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
     * Current cart contents, revalidated against CartGuard, live price and stock.
     *
     * @return Collection<int, array{product: Product, quantity: int, finish_slug: ?string, finish_name: ?string, size_label: ?string, unit_price: float, line_total: float, line_key: string, max_quantity: int}>
     */
    public function all(): Collection
    {
        return $this->sanitizedItems();
    }

    /**
     * @return Collection<int, array{product: Product, quantity: int, finish_slug: ?string, finish_name: ?string, size_label: ?string, unit_price: float, line_total: float, line_key: string, max_quantity: int}>
     */
    private function sanitizedItems(): Collection
    {
        $cart = session(self::SESSION_KEY, []);

        if ($cart === []) {
            return collect();
        }

        $notices = [];
        $rebuilt = [];
        $productIds = [];

        foreach ($cart as $key => $value) {
            $productId = $this->productIdFromKey($key, $value);
            if ($productId !== null) {
                $productIds[] = $productId;
            }
        }

        $products = $productIds === []
            ? collect()
            : Product::with('category')->whereIn('id', array_unique($productIds))->get()->keyBy('id');

        foreach ($cart as $key => $value) {
            $normalized = $this->normalizeLine($value);
            $productId = $this->productIdFromKey($key, $value);
            $product = $productId !== null ? $products->get($productId) : null;

            $variant = $this->validatedVariant($product, $normalized['size_label'], $normalized['finish_slug'], true);

            if ($variant === null || $product === null || ! CartGuard::isEligible($product, $variant['size_label'])) {
                $notices[] = 'An item was removed from your bag because it is no longer available.';

                continue;
            }

            $unitPrice = CartGuard::trustedUnitPrice($product, $variant['size_label']);

            if ($unitPrice === null) {
                $notices[] = 'An item was removed from your bag because its price is no longer available.';

                continue;
            }

            $lineKey = self::lineKey($product->id, $variant['size_label'], $variant['finish_slug']);

            if (isset($rebuilt[$lineKey])) {
                $rebuilt[$lineKey]['quantity'] += $normalized['quantity'];
            } else {
                $rebuilt[$lineKey] = [
                    'quantity' => $normalized['quantity'],
                    'finish_slug' => $variant['finish_slug'],
                    'finish_name' => $variant['finish_name'],
                    'size_label' => $variant['size_label'],
                    'unit_price' => $unitPrice,
                ];
            }
        }

        $rebuilt = $this->clampStock($rebuilt, $products, $notices);

        if ($rebuilt !== $cart) {
            session([self::SESSION_KEY => $rebuilt]);
        }

        $this->flashNotices($notices);

        return collect($rebuilt)->map(function (array $line, string $lineKey) use ($products) {
            $productId = $this->productIdFromKey($lineKey, $line);
            $product = $products->get($productId);
            $unitPrice = CartGuard::trustedUnitPrice($product, $line['size_label']);

            return [
                'product' => $product,
                'quantity' => $line['quantity'],
                'finish_slug' => $line['finish_slug'],
                'finish_name' => $line['finish_name'],
                'size_label' => $line['size_label'],
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $line['quantity'],
                'line_key' => $lineKey,
                'max_quantity' => $line['quantity'],
            ];
        })->filter(fn (array $item) => $item['product'] && $item['unit_price'] !== null)->values()
            ->map(function (array $item) use ($rebuilt) {
                $usedOthers = $this->quantityForProduct($item['product']->id, $rebuilt, $item['line_key']);
                $available = StockAvailability::availableForProduct($item['product']);
                $item['max_quantity'] = min(99, max($item['quantity'], max(0, $available - $usedOthers)));

                return $item;
            });
    }

    /**
     * @param  array<string, array{quantity: int, finish_slug: ?string, finish_name: ?string, size_label: ?string, unit_price: float}>  $cart
     * @param  Collection<int, Product>  $products
     * @param  list<string>  $notices
     * @return array<string, array{quantity: int, finish_slug: ?string, finish_name: ?string, size_label: ?string, unit_price: float}>
     */
    private function clampStock(array $cart, Collection $products, array &$notices): array
    {
        $grouped = [];
        foreach ($cart as $lineKey => $line) {
            $productId = $this->productIdFromKey($lineKey, $line);
            $grouped[$productId][$lineKey] = $line;
        }

        $clamped = [];

        foreach ($grouped as $productId => $lines) {
            $product = $products->get($productId);
            $remaining = $product ? StockAvailability::availableForProduct($product) : 0;

            foreach ($lines as $lineKey => $line) {
                $desired = min(99, $line['quantity']);
                $qty = min($desired, $remaining);

                if ($qty < $line['quantity'] && $qty >= 1) {
                    $notices[] = ($product?->name ?? 'An item').' quantity was limited to available stock.';
                }

                if ($qty < 1) {
                    $notices[] = ($product?->name ?? 'An item').' was removed because it is out of stock.';

                    continue;
                }

                $line['quantity'] = $qty;
                $clamped[$lineKey] = $line;
                $remaining = max(0, $remaining - $qty);
            }
        }

        return $clamped;
    }

    /**
     * @param  array<string, array{quantity: int}>  $cart
     */
    private function quantityForProduct(int $productId, array $cart, ?string $exceptKey = null): int
    {
        $total = 0;

        foreach ($cart as $key => $line) {
            if ($exceptKey !== null && $key === $exceptKey) {
                continue;
            }

            if ($this->productIdFromKey($key, $line) === $productId) {
                $total += $this->normalizeLine($line)['quantity'];
            }
        }

        return $total;
    }

    private function productIdFromKey(int|string $key, mixed $line): ?int
    {
        if (is_numeric($key)) {
            return (int) $key;
        }

        if (is_string($key) && str_contains($key, '|')) {
            return (int) explode('|', $key, 3)[0];
        }

        if (is_array($line) && isset($line['product_id'])) {
            return (int) $line['product_id'];
        }

        return null;
    }

    /**
     * @return array{size_label: ?string, finish_slug: string, finish_name: string}|null
     */
    public function validatedVariant(?Product $product, ?string $sizeLabel, ?string $finishSlug, bool $allowDefaultFinish = false): ?array
    {
        if (! $product) {
            return null;
        }

        $size = filled($sizeLabel) ? trim((string) $sizeLabel) : null;
        if ($size === '') {
            $size = null;
        }

        if ($product->hasSizeOptions()) {
            if (! filled($size) || CartGuard::exactSizeOption($product, $size) === null) {
                return null;
            }
        } elseif (filled($size)) {
            return null;
        }

        $finish = filled($finishSlug) ? FinishSwatches::findBySlug($finishSlug) : null;

        if ($finish === null && $allowDefaultFinish && ! filled($finishSlug)) {
            $finish = FinishSwatches::findBySlug(FinishSwatches::defaultSlug())
                ?? (FinishSwatches::all()[0] ?? null);
        }

        if ($finish === null) {
            return null;
        }

        return [
            'size_label' => $size,
            'finish_slug' => $finish['slug'],
            'finish_name' => $finish['name'],
        ];
    }

    /**
     * @return array{quantity: int, clamped: bool}
     */
    public function add(Product $product, int $quantity = 1, ?string $finishSlug = null, ?string $sizeLabel = null): array
    {
        $this->sanitizedItems();

        $variant = $this->validatedVariant($product, $sizeLabel, $finishSlug, true);

        if ($variant === null) {
            return ['quantity' => 0, 'clamped' => false];
        }

        $lineKey = self::lineKey($product->id, $variant['size_label'], $variant['finish_slug']);
        $cart = session(self::SESSION_KEY, []);
        $existingQty = isset($cart[$lineKey]) ? $this->normalizeLine($cart[$lineKey])['quantity'] : 0;
        $usedOthers = $this->quantityForProduct($product->id, $cart, $lineKey);
        $available = StockAvailability::availableForProduct($product);
        $maxForLine = min(99, max(0, $available - $usedOthers));
        $incoming = max(1, $quantity);
        $newQty = min($existingQty + $incoming, $maxForLine);

        if ($newQty < 1) {
            return ['quantity' => 0, 'clamped' => true];
        }

        $unitPrice = CartGuard::trustedUnitPrice($product, $variant['size_label']) ?? 0.0;

        $cart[$lineKey] = [
            'quantity' => $newQty,
            'finish_slug' => $variant['finish_slug'],
            'finish_name' => $variant['finish_name'],
            'size_label' => $variant['size_label'],
            'unit_price' => $unitPrice,
        ];

        session([self::SESSION_KEY => $cart]);

        return [
            'quantity' => $newQty,
            'clamped' => $newQty < ($existingQty + $incoming),
        ];
    }

    /** @param  list<int|string>  $lineKeys */
    public function removeMany(array $lineKeys): void
    {
        $cart = session(self::SESSION_KEY, []);
        foreach ($lineKeys as $lineKey) {
            unset($cart[$lineKey]);
        }
        session([self::SESSION_KEY => $cart]);
    }

    public function update(Product $product, int $quantity, ?string $sizeLabel = null, ?string $finishSlug = null): bool
    {
        $this->sanitizedItems();

        $lineKey = $this->resolveExistingLineKey($product, $sizeLabel, $finishSlug);
        $cart = session(self::SESSION_KEY, []);

        if ($lineKey === null || ! isset($cart[$lineKey])) {
            return false;
        }

        if ($quantity <= 0) {
            unset($cart[$lineKey]);
            session([self::SESSION_KEY => $cart]);

            return true;
        }

        $existing = $this->normalizeLine($cart[$lineKey]);
        $usedOthers = $this->quantityForProduct($product->id, $cart, $lineKey);
        $available = StockAvailability::availableForProduct($product);
        $maxForLine = min(99, max(0, $available - $usedOthers));
        $qty = min(max(1, $quantity), $maxForLine);

        if ($qty < 1) {
            unset($cart[$lineKey]);
            session([self::SESSION_KEY => $cart]);

            return true;
        }

        $cart[$lineKey] = [
            'quantity' => $qty,
            'finish_slug' => $existing['finish_slug'],
            'finish_name' => $existing['finish_name'],
            'size_label' => $existing['size_label'],
            'unit_price' => $existing['unit_price'],
        ];

        session([self::SESSION_KEY => $cart]);

        return true;
    }

    public function remove(Product $product, ?string $sizeLabel = null, ?string $finishSlug = null): bool
    {
        $this->sanitizedItems();

        $lineKey = $this->resolveExistingLineKey($product, $sizeLabel, $finishSlug);
        $cart = session(self::SESSION_KEY, []);

        if ($lineKey === null || ! isset($cart[$lineKey])) {
            return false;
        }

        unset($cart[$lineKey]);
        session([self::SESSION_KEY => $cart]);

        return true;
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
        return (int) $this->all()->sum('quantity');
    }

    public function subtotal(): float
    {
        return $this->all()->sum('line_total');
    }

    public function isEmpty(): bool
    {
        return $this->all()->isEmpty();
    }

    public function containsProduct(int $productId): bool
    {
        foreach (session(self::SESSION_KEY, []) as $key => $line) {
            if ($this->productIdFromKey($key, $line) === $productId) {
                return true;
            }
        }

        return false;
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

        $option = CartGuard::exactSizeOption($product, $sizeLabel);
        $label = $option['label'] ?? null;

        return [
            'label' => $label,
            'unit_price' => CartGuard::trustedUnitPrice($product, $label) ?? 0.0,
        ];
    }

    private function resolveExistingLineKey(Product $product, ?string $sizeLabel, ?string $finishSlug): ?string
    {
        $cart = session(self::SESSION_KEY, []);
        $variant = $this->validatedVariant($product, $sizeLabel, $finishSlug, ! filled($finishSlug));

        if ($variant !== null) {
            $key = self::lineKey($product->id, $variant['size_label'], $variant['finish_slug']);
            if (isset($cart[$key])) {
                return $key;
            }
        }

        $matches = [];
        foreach ($cart as $key => $line) {
            if ($this->productIdFromKey($key, $line) === $product->id) {
                $matches[] = (string) $key;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /** @param  list<string>  $notices */
    private function flashNotices(array $notices): void
    {
        $notices = array_values(array_unique(array_filter($notices)));

        if ($notices === []) {
            return;
        }

        session()->flash(self::NOTICE_KEY, $notices);
    }
}
