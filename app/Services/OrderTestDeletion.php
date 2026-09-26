<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\OrderRefundEvent;
use App\Models\OrderRefundLine;
use App\Models\User;
use App\Support\AdminRole;
use App\Support\PaymentAtomicLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Hard-deletes one financially inert order and its order-item rows.
 *
 * Refund, payment, and reconciliation rows are never deleted. Every refusal
 * is decided again on the row locked inside the transaction.
 */
class OrderTestDeletion
{
    public const DELETED = 'deleted';

    public const MISSING = 'missing';

    public const BLOCKED_GATEWAY = 'blocked_gateway';

    public const BLOCKED_PAYMENT = 'blocked_payment';

    public const BLOCKED_STOCK = 'blocked_stock';

    public const BLOCKED_RECONCILIATION = 'blocked_reconciliation';

    public const BLOCKED_REFUND = 'blocked_refund';

    public const BLOCKED_STATUS = 'blocked_status';

    public const BLOCKED_CONFIRMATION = 'blocked_confirmation';

    public const BLOCKED_PASSWORD = 'blocked_password';

    public const BLOCKED_BUSY = 'blocked_busy';

    /**
     * The Razorpay order cache lock is acquired before any database transaction.
     * Order creation holds that same lock across its gateway HTTP call, so a
     * delete cannot remove the row during that window. The order-row lock is
     * taken only inside the callback, after the cache lock.
     */
    public function delete(User $actor, int $orderId, string $typedOrderNumber, string $password): string
    {
        try {
            return PaymentAtomicLock::run(
                PaymentAtomicLock::forRazorpayOrder($orderId),
                PaymentAtomicLock::razorpayWaitSeconds(),
                fn (): string => $this->deleteAfterGatewayLock($actor, $orderId, $typedOrderNumber, $password),
            );
        } catch (LockTimeoutException) {
            return self::BLOCKED_BUSY;
        }
    }

    private function deleteAfterGatewayLock(User $actor, int $orderId, string $typedOrderNumber, string $password): string
    {
        return DB::transaction(function () use ($actor, $orderId, $typedOrderNumber, $password): string {
            $freshActor = User::query()->find($actor->getKey());

            if (
                $freshActor === null
                || ! $freshActor->is_active
                || ! $freshActor->isAdmin()
                || ! $freshActor->isOwner()
                || ! $freshActor->hasAdminPermission(AdminRole::ORDERS_DELETE_TEST)
            ) {
                abort(403);
            }

            /** @var Order|null $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                return self::MISSING;
            }

            if (! Hash::check($password, (string) $freshActor->password)) {
                return self::BLOCKED_PASSWORD;
            }

            $block = $this->blockReason($order);

            if ($block !== null) {
                return $block;
            }

            $expected = (string) $order->order_number;

            if ($expected === '' || ! hash_equals($expected, $typedOrderNumber)) {
                return self::BLOCKED_CONFIRMATION;
            }

            /** @var Collection<int, int> $itemIds */
            $itemIds = OrderItem::query()
                ->where('order_id', $order->getKey())
                ->lockForUpdate()
                ->pluck('id');

            if ($this->ledgerExists((int) $order->getKey(), $itemIds->all())) {
                return self::BLOCKED_REFUND;
            }

            $deleted = Order::query()
                ->whereKey($order->getKey())
                ->whereNull('payment_id')
                ->whereNull('razorpay_order_id')
                ->whereNull('stock_deducted_at')
                ->whereIn('status', ['pending', 'cancelled'])
                ->where(function ($query): void {
                    $query->whereNull('reconciliation_reason')->orWhere('reconciliation_reason', '');
                })
                // JSON that is clean to the PHP predicate but not exactly null,
                // [], null, or {} stays in place. A zero-row delete is a generic
                // refusal. Do not widen this list to match every clean value.
                ->where(function ($query): void {
                    $query->whereNull('reconciliation_meta')
                        ->orWhereIn('reconciliation_meta', ['[]', 'null', '{}']);
                })
                ->where(function ($query): void {
                    $query->whereNull('captured_amount_paise')->orWhere('captured_amount_paise', 0);
                })
                ->where(function ($query): void {
                    $query->whereNull('refunded_amount_paise')->orWhere('refunded_amount_paise', 0);
                })
                ->where(function ($query): void {
                    $query->whereNull('refund_pending_amount_paise')->orWhere('refund_pending_amount_paise', 0);
                })
                ->where(function ($query): void {
                    $query->whereNull('refund_status')->orWhere('refund_status', 'none');
                })
                ->delete();

            if ($deleted !== 1) {
                return self::BLOCKED_STATUS;
            }

            OrderItem::query()->where('order_id', $orderId)->delete();

            return self::DELETED;
        });
    }

    public static function message(string $result): string
    {
        return match ($result) {
            self::BLOCKED_GATEWAY => 'This order contains gateway evidence and cannot be deleted.',
            self::BLOCKED_PAYMENT => 'This order contains payment evidence and cannot be deleted.',
            self::BLOCKED_STOCK => 'This order contains stock evidence and cannot be deleted.',
            self::BLOCKED_RECONCILIATION => 'This order contains reconciliation evidence and cannot be deleted.',
            self::BLOCKED_REFUND => 'This order contains refund evidence and cannot be deleted.',
            self::BLOCKED_CONFIRMATION => 'Enter the order number exactly to confirm deletion.',
            self::BLOCKED_PASSWORD => 'The password is incorrect.',
            self::BLOCKED_BUSY => 'This order is busy; try again.',
            default => 'Only a pending or cancelled order without payment evidence can be deleted.',
        };
    }

    private function blockReason(Order $order): ?string
    {
        if ($order->payment_id !== null) {
            return self::BLOCKED_PAYMENT;
        }

        if ($order->razorpay_order_id !== null) {
            return self::BLOCKED_GATEWAY;
        }

        if ($order->stock_deducted_at !== null) {
            return self::BLOCKED_STOCK;
        }

        if ($order->hasReconciliationEvidence()) {
            return self::BLOCKED_RECONCILIATION;
        }

        if (
            $this->paise($order->captured_amount_paise) > 0
            || $this->paise($order->refunded_amount_paise) > 0
            || $this->paise($order->refund_pending_amount_paise) > 0
        ) {
            return self::BLOCKED_PAYMENT;
        }

        $refundStatus = $order->refund_status;

        if ($refundStatus !== null && $refundStatus !== 'none') {
            return self::BLOCKED_REFUND;
        }

        if (! in_array($order->status, ['pending', 'cancelled'], true)) {
            return self::BLOCKED_STATUS;
        }

        return null;
    }

    private function paise(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) $amount;
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function ledgerExists(int $orderId, array $itemIds): bool
    {
        $refundIds = OrderRefund::query()
            ->where('order_id', $orderId)
            ->lockForUpdate()
            ->pluck('id');

        if ($refundIds->isNotEmpty()) {
            return true;
        }

        if ($itemIds !== [] && OrderRefundLine::query()->whereIn('order_item_id', $itemIds)->lockForUpdate()->exists()) {
            return true;
        }

        return OrderRefundEvent::query()
            ->whereIn('order_refund_id', OrderRefund::query()->select('id')->where('order_id', $orderId))
            ->lockForUpdate()
            ->exists();
    }
}
