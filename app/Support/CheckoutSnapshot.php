<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Collection;

class CheckoutSnapshot
{
    public const SOURCE_CART = 'cart';

    public const SOURCE_BUY_NOW = 'buy_now';

    public const SOURCE_KEY = '_checkout_source';

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $addressSnapshot
     * @return array<string, mixed>
     */
    public static function fromCheckout(
        string $source,
        Collection $items,
        float|int|string $subtotal,
        float|int|string $shipping,
        float|int|string $total,
        array $addressSnapshot,
    ): array {
        return self::normalize([
            'source' => self::normalizeSource($source),
            'items' => $items->map(fn (array $item) => [
                'product_id' => (int) $item['product']->id,
                'size_label' => self::normalizeLabel($item['size_label'] ?? null),
                'finish_slug' => self::normalizeLabel($item['finish_slug'] ?? null),
                'quantity' => (int) $item['quantity'],
                'unit_price' => self::money($item['unit_price'] ?? 0),
                'line_total' => self::money($item['line_total'] ?? 0),
            ])->all(),
            'subtotal' => self::money($subtotal),
            'shipping_cost' => self::money($shipping),
            'total' => self::money($total),
            'customer_name' => self::normalizeText($addressSnapshot['full_name'] ?? ''),
            'customer_email' => self::normalizeEmail($addressSnapshot['email'] ?? ''),
            'customer_phone' => self::normalizePhone($addressSnapshot['phone'] ?? ''),
            'alt_mobile' => self::normalizePhone($addressSnapshot['alt_mobile'] ?? ''),
            'shipping_address' => self::normalizeText($addressSnapshot['formatted_line'] ?? ''),
            'city' => self::normalizeText($addressSnapshot['city'] ?? ''),
            'state' => self::normalizeText($addressSnapshot['state'] ?? ''),
            'pincode' => self::normalizeText($addressSnapshot['pincode'] ?? ''),
            'country' => self::normalizeText($addressSnapshot['country'] ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromOrder(Order $order): array
    {
        $order->loadMissing('items');
        $storedSource = $order->shipping_snapshot[self::SOURCE_KEY] ?? self::SOURCE_CART;

        return self::normalize([
            'source' => self::normalizeSource(is_string($storedSource) ? $storedSource : self::SOURCE_CART),
            'items' => $order->items->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'size_label' => self::normalizeLabel($item->size_label),
                'finish_slug' => self::normalizeLabel($item->finish_slug),
                'quantity' => (int) $item->quantity,
                'unit_price' => self::money($item->price),
                'line_total' => self::money($item->total),
            ])->all(),
            'subtotal' => self::money($order->subtotal),
            'shipping_cost' => self::money($order->shipping_cost),
            'total' => self::money($order->total),
            'customer_name' => self::normalizeText($order->customer_name),
            'customer_email' => self::normalizeEmail($order->customer_email),
            'customer_phone' => self::normalizePhone($order->customer_phone),
            'alt_mobile' => self::normalizePhone($order->alt_mobile),
            'shipping_address' => self::normalizeText($order->shipping_address),
            'city' => self::normalizeText($order->city),
            'state' => self::normalizeText($order->state),
            'pincode' => self::normalizeText($order->pincode),
            'country' => self::normalizeText($order->country),
        ]);
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    public static function matches(array $left, array $right): bool
    {
        return hash_equals(self::hash($left), self::hash($right));
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public static function hash(array $normalized): string
    {
        return hash('sha256', (string) json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function withSource(array $addressSnapshot, string $source): array
    {
        $addressSnapshot[self::SOURCE_KEY] = self::normalizeSource($source);

        return $addressSnapshot;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function normalize(array $payload): array
    {
        $items = $payload['items'];
        usort($items, function (array $left, array $right): int {
            return [$left['product_id'], $left['size_label'], $left['finish_slug']]
                <=> [$right['product_id'], $right['size_label'], $right['finish_slug']];
        });
        $payload['items'] = array_values($items);

        return $payload;
    }

    private static function normalizeSource(string $source): string
    {
        return $source === self::SOURCE_BUY_NOW ? self::SOURCE_BUY_NOW : self::SOURCE_CART;
    }

    private static function normalizeLabel(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private static function normalizeText(mixed $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));
    }

    private static function normalizeEmail(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private static function normalizePhone(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    private static function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
