<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\OrderRefundEvent;
use App\Models\OrderRefundLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderAdminUpdate
{
    public const STATUS_LOCKED = 'status_locked';

    public const REFUND_REQUIRED = 'refund_required';

    public const CAPTURE_REQUIRED = 'capture_required';

    public const INVESTIGATION_REQUIRED = 'investigation_required';

    public const NOTES_SAVED = 'notes_saved';

    public const UPDATED = 'updated';

    /**
     * Decide the write only after the order row is locked.
     * A stale form cannot overwrite reconciliation evidence.
     *
     * @return self::STATUS_LOCKED|self::REFUND_REQUIRED|self::CAPTURE_REQUIRED|self::INVESTIGATION_REQUIRED|self::NOTES_SAVED|self::UPDATED
     */
    public static function apply(int $orderId, mixed $status, mixed $adminNotes, bool $statusFieldPresent): string
    {
        $notes = Validator::make(
            ['admin_notes' => $adminNotes],
            ['admin_notes' => 'nullable|string|max:5000'],
        )->validate();

        return DB::transaction(function () use ($orderId, $status, $notes, $statusFieldPresent) {
            $locked = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            if ($locked->needsPaymentReview()) {
                if ($statusFieldPresent && (string) $status !== $locked->status) {
                    return self::STATUS_LOCKED;
                }

                $locked->forceFill([
                    'admin_notes' => $notes['admin_notes'] ?? null,
                ])->save();

                return self::NOTES_SAVED;
            }

            $statusValidated = Validator::make(
                ['status' => $status],
                ['status' => 'required|in:pending,paid,processing,shipped,delivered,cancelled'],
            )->validate();

            $newStatus = (string) $statusValidated['status'];

            if (self::razorpayStatusNeedsCapture($locked, $newStatus)) {
                return self::CAPTURE_REQUIRED;
            }

            if (self::refundFlowOwnsStatus($locked, $newStatus)) {
                return self::REFUND_REQUIRED;
            }

            if (self::inconsistentRazorpayRecoveryIsBlocked($locked, $newStatus)) {
                return self::INVESTIGATION_REQUIRED;
            }

            $locked->forceFill([
                'status' => $newStatus,
                'admin_notes' => $notes['admin_notes'] ?? null,
            ])->save();

            return self::UPDATED;
        });
    }

    /**
     * A Razorpay order without a stored payment id cannot move into a
     * fulfilment status. Fulfilment status alone is not captured payment.
     * Other payment_method values are historical records.
     */
    private static function razorpayStatusNeedsCapture(Order $order, string $newStatus): bool
    {
        if ($order->payment_method !== 'razorpay' || $order->hasDurableCapturedPaymentEvidence()) {
            return false;
        }

        if ($newStatus === (string) $order->status) {
            return false;
        }

        return in_array($newStatus, ['paid', 'processing', 'shipped', 'delivered'], true);
    }

    private static function refundFlowOwnsStatus(Order $order, string $newStatus): bool
    {
        if ($newStatus === (string) $order->status) {
            return false;
        }

        if ($order->refund_status === 'refunded') {
            return true;
        }

        if ($order->payment_method === 'razorpay' && ! $order->hasDurableCapturedPaymentEvidence()) {
            return false;
        }

        if (! $order->hasCapturedPayment()) {
            return false;
        }

        return in_array($newStatus, ['cancelled', 'pending'], true);
    }

    /**
     * An inconsistent Razorpay row may return to pending or cancelled only
     * when stock, reconciliation, refund, and captured-payment evidence are absent.
     */
    private static function inconsistentRazorpayRecoveryIsBlocked(Order $order, string $newStatus): bool
    {
        if ($order->payment_method !== 'razorpay' || $order->hasDurableCapturedPaymentEvidence()) {
            return false;
        }

        if ($newStatus === (string) $order->status) {
            return false;
        }

        if (! in_array($newStatus, ['pending', 'cancelled'], true)) {
            return false;
        }

        if ($order->hasReconciliationEvidence() || $order->stock_deducted_at !== null) {
            return true;
        }

        if (self::paise($order->captured_amount_paise) > 0
            || self::paise($order->refunded_amount_paise) > 0
            || self::paise($order->refund_pending_amount_paise) > 0) {
            return true;
        }

        $refundStatus = $order->refund_status;

        if ($refundStatus !== null && $refundStatus !== 'none') {
            return true;
        }

        return self::refundLedgerExists($order);
    }

    private static function paise(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) $amount;
    }

    private static function refundLedgerExists(Order $order): bool
    {
        $refundIds = OrderRefund::query()
            ->where('order_id', $order->getKey())
            ->lockForUpdate()
            ->pluck('id');

        if ($refundIds->isNotEmpty()) {
            return true;
        }

        $itemIds = OrderItem::query()
            ->where('order_id', $order->getKey())
            ->lockForUpdate()
            ->pluck('id');

        if ($itemIds->isNotEmpty() && OrderRefundLine::query()->whereIn('order_item_id', $itemIds)->lockForUpdate()->exists()) {
            return true;
        }

        return OrderRefundEvent::query()
            ->whereIn('order_refund_id', OrderRefund::query()->select('id')->where('order_id', $order->getKey()))
            ->lockForUpdate()
            ->exists();
    }
}
