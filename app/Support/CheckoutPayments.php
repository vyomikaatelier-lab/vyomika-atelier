<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Throwable;

class CheckoutPayments
{
    public const UNAVAILABLE_MESSAGE = 'Checkout is temporarily unavailable. Your cart is saved. Please try again later.';

    public const SNAPSHOT_SUPPRESS_NOTIFICATIONS = 'suppress_notifications';

    public static function enabled(): bool
    {
        return (bool) config('checkout.payments_enabled', false);
    }

    /**
     * Test suites set this so ordinary payment tests are not an allowlist rehearsal.
     * Production must leave it false. When false, payments stay closed unless the
     * allowlist is non-empty and the expiry is still in the future.
     */
    public static function unrestricted(): bool
    {
        return (bool) config('checkout.payments_unrestricted', false);
    }

    /**
     * @return list<string>
     */
    public static function allowedEmails(): array
    {
        $raw = config('checkout.payments_allowed_emails', '');

        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/[\s,]+/', (string) $raw) ?: [];
        }

        $emails = [];
        foreach ($parts as $part) {
            $email = strtolower(trim((string) $part));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    public static function restrictsInitiation(): bool
    {
        return self::allowedEmails() !== [];
    }

    public static function expiresAt(): ?Carbon
    {
        $raw = trim((string) config('checkout.payments_expires_at', ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }

    public static function windowOpen(): bool
    {
        $expires = self::expiresAt();

        return $expires !== null && now()->lt($expires);
    }

    public static function emailAllowed(?User $user): bool
    {
        if (! CheckoutCustomer::canCheckout($user) || ! self::restrictsInitiation()) {
            return false;
        }

        return in_array(strtolower((string) $user->email), self::allowedEmails(), true);
    }

    public static function initiationDenied(?User $user): bool
    {
        return self::enabled()
            && ! self::unrestricted()
            && ! self::canInitiate($user);
    }

    public static function canInitiate(?User $user, ?Order $current = null): bool
    {
        if (! self::enabled() || ! CheckoutCustomer::canCheckout($user)) {
            return false;
        }

        if (self::unrestricted()) {
            return true;
        }

        if (! self::windowOpen() || ! self::emailAllowed($user)) {
            return false;
        }

        return ! self::hasOtherLiveTestOrder($user, $current);
    }

    /**
     * The one already-created test order may still open Razorpay and reuse its
     * gateway id after new initiation is switched off. A missing gateway id,
     * a second order, or a passed expiry cannot.
     */
    public static function canContinuePayment(?User $user, ?Order $order): bool
    {
        if (self::unrestricted() || ! $order || ! $user || ! self::windowOpen() || ! self::emailAllowed($user)) {
            return false;
        }

        if ((int) $order->user_id !== (int) $user->id) {
            return false;
        }

        if ($order->status !== 'pending' || $order->payment_method !== 'razorpay' || $order->isExpired()) {
            return false;
        }

        if (! filled($order->razorpay_order_id)) {
            return false;
        }

        $order->loadMissing('items.product');

        if ($order->items->isEmpty()) {
            return false;
        }

        foreach ($order->items as $item) {
            if (! ProductPublicationPolicy::isLiveTestItem($item->product)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Server-side only. A posted flag is ignored. Suppression follows the
     * order lines, not the customer, so a later ordinary order still notifies.
     *
     * @param  iterable<mixed>  $items
     */
    public static function itemsAreLiveTestOnly(iterable $items): bool
    {
        $count = 0;

        foreach ($items as $item) {
            $product = is_array($item) ? ($item['product'] ?? null) : null;

            if (! $product instanceof Product || ! ProductPublicationPolicy::isLiveTestItem($product)) {
                return false;
            }

            $count++;
        }

        return $count > 0;
    }

    public static function orderMailSuppressed(Order $order): bool
    {
        $snapshot = $order->shipping_snapshot;

        return is_array($snapshot) && ($snapshot[self::SNAPSHOT_SUPPRESS_NOTIFICATIONS] ?? false) === true;
    }

    private static function hasOtherLiveTestOrder(User $user, ?Order $current): bool
    {
        $prefix = ProductPublicationPolicy::LIVE_TEST_SKU_PREFIX;

        return Order::query()
            ->where('user_id', $user->id)
            ->when($current?->id, fn ($query) => $query->whereKeyNot($current->id))
            ->whereHas('items.product', function ($query) use ($prefix) {
                $query->whereRaw('UPPER(sku) LIKE ?', [$prefix.'%']);
            })
            ->exists();
    }
}
