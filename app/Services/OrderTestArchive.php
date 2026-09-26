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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Records permanent archive metadata for one protected test order.
 * The default admin list omits that order only while it stays financially inert.
 *
 * Archive metadata is the only write. Financial, gateway, stock, refund,
 * customer, and fulfilment evidence is never changed or removed.
 * The Razorpay order cache lock is acquired before the database transaction.
 */
class OrderTestArchive
{
    public const ARCHIVED = 'archived';

    public const UNARCHIVED = 'unarchived';

    public const MISSING = 'missing';

    public const ALREADY_ARCHIVED = 'already_archived';

    public const NOT_ARCHIVED = 'not_archived';

    public const BLOCKED_PAYMENT = 'blocked_payment';

    public const BLOCKED_STOCK = 'blocked_stock';

    public const BLOCKED_RECONCILIATION = 'blocked_reconciliation';

    public const BLOCKED_REFUND = 'blocked_refund';

    public const BLOCKED_STATUS = 'blocked_status';

    public const BLOCKED_MALFORMED = 'blocked_malformed';

    public const BLOCKED_CONFIRMATION = 'blocked_confirmation';

    public const BLOCKED_PASSWORD = 'blocked_password';

    public const BLOCKED_BUSY = 'blocked_busy';

    public const REASON_ARCHIVE = 'protected_test_order';

    public const REASON_UNARCHIVE = 'owner_unarchive';

    public function archive(User $actor, int $orderId, string $typedOrderNumber, string $password): string
    {
        try {
            return PaymentAtomicLock::run(
                PaymentAtomicLock::forRazorpayOrder($orderId),
                PaymentAtomicLock::razorpayWaitSeconds(),
                fn (): string => $this->archiveAfterGatewayLock($actor, $orderId, $typedOrderNumber, $password),
            );
        } catch (LockTimeoutException) {
            return self::BLOCKED_BUSY;
        }
    }

    public function unarchive(User $actor, int $orderId, string $typedOrderNumber, string $password): string
    {
        try {
            return PaymentAtomicLock::run(
                PaymentAtomicLock::forRazorpayOrder($orderId),
                PaymentAtomicLock::razorpayWaitSeconds(),
                fn (): string => $this->unarchiveAfterGatewayLock($actor, $orderId, $typedOrderNumber, $password),
            );
        } catch (LockTimeoutException) {
            return self::BLOCKED_BUSY;
        }
    }

    public function appearsEligible(Order $order): bool
    {
        if ($this->blockReason($order) !== null) {
            return false;
        }

        $itemIds = OrderItem::query()
            ->where('order_id', $order->getKey())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return ! $this->ledgerExists((int) $order->getKey(), $itemIds, false);
    }

    public static function message(string $result, bool $unarchive = false): string
    {
        return match ($result) {
            self::ALREADY_ARCHIVED => 'This order is already archived.',
            self::NOT_ARCHIVED => 'This order is not archived.',
            self::BLOCKED_PAYMENT => 'This order contains payment evidence and cannot be archived.',
            self::BLOCKED_STOCK => 'This order contains stock evidence and cannot be archived.',
            self::BLOCKED_RECONCILIATION => 'This order contains reconciliation evidence and cannot be archived.',
            self::BLOCKED_REFUND => 'This order contains refund evidence and cannot be archived.',
            self::BLOCKED_CONFIRMATION => $unarchive
                ? 'Enter the order number exactly to confirm unarchiving.'
                : 'Enter the order number exactly to confirm archiving.',
            self::BLOCKED_PASSWORD => 'The password is incorrect.',
            self::BLOCKED_BUSY => 'This order is busy; try again.',
            self::BLOCKED_MALFORMED => 'This order cannot be archived.',
            default => $unarchive
                ? 'This order cannot be unarchived.'
                : 'Only a pending or cancelled order without captured payment evidence can be archived.',
        };
    }

    private function archiveAfterGatewayLock(User $actor, int $orderId, string $typedOrderNumber, string $password): string
    {
        $result = DB::transaction(function () use ($actor, $orderId, $typedOrderNumber, $password): string {
            $freshActor = $this->freshOwner($actor);

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

            /** @var list<int> $itemIds */
            $itemIds = OrderItem::query()
                ->where('order_id', $order->getKey())
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($this->ledgerExists((int) $order->getKey(), $itemIds, true)) {
                return self::BLOCKED_REFUND;
            }

            $update = DB::table('orders')
                ->where('id', $order->getKey())
                ->whereNull('admin_archived_at')
                ->whereNull('admin_archived_by_user_id')
                ->whereNull('admin_archive_reason');
            Order::constrainFinanciallyInert($update);
            $updated = $update->update([
                    'admin_archived_at' => now(),
                    'admin_archived_by_user_id' => $freshActor->getKey(),
                    'admin_archive_reason' => self::REASON_ARCHIVE,
                ]);

            if ($updated !== 1) {
                return self::BLOCKED_STATUS;
            }

            $this->audit((int) $order->getKey(), (int) $freshActor->getKey(), 'archive', self::REASON_ARCHIVE);

            return self::ARCHIVED;
        });

        return $result;
    }

    private function unarchiveAfterGatewayLock(User $actor, int $orderId, string $typedOrderNumber, string $password): string
    {
        return DB::transaction(function () use ($actor, $orderId, $typedOrderNumber, $password): string {
            $freshActor = $this->freshOwner($actor);

            /** @var Order|null $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                return self::MISSING;
            }

            if (! Hash::check($password, (string) $freshActor->password)) {
                return self::BLOCKED_PASSWORD;
            }

            if (! $this->hasArchiveEvidence($order)) {
                return self::NOT_ARCHIVED;
            }

            $expected = (string) $order->order_number;

            if ($expected === '' || ! hash_equals($expected, $typedOrderNumber)) {
                return self::BLOCKED_CONFIRMATION;
            }

            $updated = DB::table('orders')
                ->where('id', $order->getKey())
                ->where(function ($query): void {
                    $query->whereNotNull('admin_archived_at')
                        ->orWhereNotNull('admin_archived_by_user_id')
                        ->orWhereNotNull('admin_archive_reason');
                })
                ->update([
                    'admin_archived_at' => null,
                    'admin_archived_by_user_id' => null,
                    'admin_archive_reason' => null,
                ]);

            if ($updated !== 1) {
                return self::NOT_ARCHIVED;
            }

            $this->audit((int) $order->getKey(), (int) $freshActor->getKey(), 'unarchive', self::REASON_UNARCHIVE);

            return self::UNARCHIVED;
        });
    }

    private function freshOwner(User $actor): User
    {
        $freshActor = User::query()->find($actor->getKey());

        if (
            $freshActor === null
            || ! $freshActor->is_active
            || ! $freshActor->isAdmin()
            || ! $freshActor->isOwner()
            || ! $freshActor->hasAdminPermission(AdminRole::ORDERS_ARCHIVE_TEST)
        ) {
            abort(403);
        }

        return $freshActor;
    }

    private function blockReason(Order $order): ?string
    {
        if ($this->hasArchiveEvidence($order)) {
            return self::ALREADY_ARCHIVED;
        }

        if ($this->gatewayReferenceIsMalformed($order)) {
            return self::BLOCKED_MALFORMED;
        }

        if ($order->getRawOriginal('payment_id') !== null) {
            return self::BLOCKED_PAYMENT;
        }

        if ($order->stock_deducted_at !== null) {
            return self::BLOCKED_STOCK;
        }

        if ($order->hasReconciliationEvidence()) {
            return self::BLOCKED_RECONCILIATION;
        }

        foreach (['captured_amount_paise', 'refunded_amount_paise', 'refund_pending_amount_paise'] as $column) {
            $state = $this->amountState($order, $column);

            if ($state === 'malformed') {
                return self::BLOCKED_MALFORMED;
            }

            if ($state === 'nonzero') {
                return self::BLOCKED_PAYMENT;
            }
        }

        $refundStatus = $order->getRawOriginal('refund_status');

        if ($refundStatus !== null && $refundStatus !== 'none') {
            return self::BLOCKED_REFUND;
        }

        if (! in_array($order->getRawOriginal('status'), ['pending', 'cancelled'], true)) {
            return self::BLOCKED_STATUS;
        }

        return null;
    }

    private function hasArchiveEvidence(Order $order): bool
    {
        return $order->getRawOriginal('admin_archived_at') !== null
            || $order->getRawOriginal('admin_archived_by_user_id') !== null
            || $order->getRawOriginal('admin_archive_reason') !== null;
    }

    private function gatewayReferenceIsMalformed(Order $order): bool
    {
        $reference = $order->getRawOriginal('razorpay_order_id');

        if ($reference === null) {
            return false;
        }

        if (! is_string($reference)) {
            return true;
        }

        return trim($reference) === '';
    }

    private function amountState(Order $order, string $column): string
    {
        $raw = $order->getRawOriginal($column);

        if ($raw === null || $raw === '') {
            return 'clear';
        }

        if (is_int($raw)) {
            return $raw === 0 ? 'clear' : 'nonzero';
        }

        if (is_float($raw)) {
            return $raw === 0.0 ? 'clear' : 'nonzero';
        }

        if (is_string($raw)) {
            if (preg_match('/^0+$/', $raw) === 1) {
                return 'clear';
            }

            if (preg_match('/^[1-9]\d*$/', $raw) === 1) {
                return 'nonzero';
            }
        }

        return 'malformed';
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function ledgerExists(int $orderId, array $itemIds, bool $lock): bool
    {
        $refunds = OrderRefund::query()->where('order_id', $orderId);

        if ($lock) {
            $refunds->lockForUpdate();
        }

        if ($refunds->exists()) {
            return true;
        }

        if ($itemIds !== []) {
            $lines = OrderRefundLine::query()->whereIn('order_item_id', $itemIds);

            if ($lock) {
                $lines->lockForUpdate();
            }

            if ($lines->exists()) {
                return true;
            }
        }

        $events = OrderRefundEvent::query()
            ->whereIn('order_refund_id', OrderRefund::query()->select('id')->where('order_id', $orderId));

        if ($lock) {
            $events->lockForUpdate();
        }

        return $events->exists();
    }

    private function audit(int $orderId, int $actorId, string $action, string $reasonCode): void
    {
        Log::info('Protected test order visibility changed.', [
            'order_id' => $orderId,
            'actor_admin_id' => $actorId,
            'action' => $action,
            'reason_code' => $reasonCode,
        ]);
    }
}
