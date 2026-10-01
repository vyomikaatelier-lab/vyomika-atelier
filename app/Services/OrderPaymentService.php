<?php

namespace App\Services;

use App\Exceptions\RazorpayReconciliationRequiredException;
use App\Models\Order;
use App\Services\StockAvailability;
use App\Support\CartGuard;
use App\Support\CheckoutPayments;
use App\Support\IndiaDelivery;
use App\Support\PaymentAtomicLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderPaymentService
{
    public function __construct(
        private RazorpayService $razorpay,
        private OrderNotificationService $notifications,
    ) {}

    /**
     * @return array{order_id: string, amount: int, currency: string}
     */
    public function razorpayCheckoutPayload(Order $order): array
    {
        if ($message = CartGuard::orderItemsEligible($order)) {
            throw new RuntimeException($message, 422);
        }

        $razorpayOrderId = $this->ensureRazorpayOrderId($order);

        return $this->buildPayload($razorpayOrderId, $order->fresh() ?? $order);
    }

    /**
     * Read-only eligibility check. Does not cancel the order or change totals.
     * An existing gateway id is not returned until the order is eligible.
     */
    public function assertInitiationAllowed(Order $order): void
    {
        $this->underInitiationLock($order, function (array $decision): void {
            $this->throwIfInitiationRefused($decision);
        });
    }

    /**
     * Cancel one untouched obsolete Indian order. Caller is the authenticated
     * checkout POST, which already holds the customer lock when required.
     */
    public function retireUntouchedObsoleteShipping(Order $order): void
    {
        $this->underInitiationLock($order, function (array $decision): void {
            $this->throwIfInitiationRefused($decision);
        }, true);
    }

    /**
     * One local order may receive only one Razorpay order ID.
     * Concurrent callers wait, re-read, and reuse the persisted ID.
     */
    public function ensureRazorpayOrderId(Order $order): string
    {
        return $this->underInitiationLock($order, function (array $decision) use ($order): string {
            $this->throwIfInitiationRefused($decision);

            if ($decision['type'] === 'reuse') {
                return (string) $decision['id'];
            }

            return $this->createGatewayOrder($order);
        });
    }

    /**
     * @param  callable(array{type: string, id?: string}): mixed  $afterDecision
     */
    private function underInitiationLock(Order $order, callable $afterDecision, bool $retireUntouched = false): mixed
    {
        $this->refuseWhenCheckoutDisabled();

        return PaymentAtomicLock::run(
            PaymentAtomicLock::forRazorpayOrder((int) $order->id),
            PaymentAtomicLock::razorpayWaitSeconds(),
            function () use ($order, $afterDecision, $retireUntouched) {
                $decision = DB::transaction(function () use ($order, $retireUntouched) {
                    $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

                    if (! $locked) {
                        throw new RuntimeException('Order not found.', 404);
                    }

                    if (! IndiaDelivery::isIndia($locked->country)) {
                        return ['type' => 'blocked'];
                    }

                    if (IndiaDelivery::hasObsoleteShippingCharge($locked)) {
                        if ($retireUntouched && PendingOrderExpiry::cancelUntouchedObsoleteShipping($locked)) {
                            return ['type' => 'retired'];
                        }

                        if ($locked->isPending() && $locked->lacksPaymentEvidence()) {
                            return ['type' => 'refresh'];
                        }

                        return ['type' => 'support'];
                    }

                    if (filled($locked->razorpay_order_id)) {
                        return ['type' => 'reuse', 'id' => (string) $locked->razorpay_order_id];
                    }

                    return ['type' => 'create'];
                });

                return $afterDecision($decision);
            }
        );
    }

    /**
     * Cancel one untouched pending order whose fulfilment terms no longer match.
     * Gateway-backed and financially evidenced orders are left unchanged.
     */
    public function retireUntouchedStaleTerms(Order $order): bool
    {
        $this->refuseWhenCheckoutDisabled();

        return PaymentAtomicLock::run(
            PaymentAtomicLock::forRazorpayOrder((int) $order->id),
            PaymentAtomicLock::razorpayWaitSeconds(),
            function () use ($order): bool {
                return DB::transaction(function () use ($order): bool {
                    $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

                    if (! $locked || ! $locked->isPending() || ! $locked->lacksPaymentEvidence()) {
                        return false;
                    }

                    $affected = Order::query()
                        ->whereKey($locked->id)
                        ->where('status', 'pending')
                        ->whereNull('payment_id')
                        ->whereNull('razorpay_order_id')
                        ->whereNull('stock_deducted_at')
                        ->update([
                            'status' => 'cancelled',
                            'expires_at' => null,
                        ]);

                    return $affected === 1;
                });
            }
        );
    }

    /**
     * Customer initiation only. Settlement, refunds, and expiry do not call this.
     */
    private function refuseWhenCheckoutDisabled(): void
    {
        if (! CheckoutPayments::enabled()) {
            throw new RuntimeException(CheckoutPayments::UNAVAILABLE_MESSAGE, 503);
        }
    }

    /**
     * @param  array{type: string, id?: string}  $decision
     */
    private function throwIfInitiationRefused(array $decision): void
    {
        if ($decision['type'] === 'blocked') {
            throw new RuntimeException(IndiaDelivery::PAYMENT_BLOCKED, 422);
        }

        if ($decision['type'] === 'refresh') {
            throw new RuntimeException(IndiaDelivery::STALE_ORDER_REFRESH, 422);
        }

        if ($decision['type'] === 'retired') {
            throw new RuntimeException(IndiaDelivery::STALE_ORDER_RETIRED, 422);
        }

        if ($decision['type'] === 'support') {
            throw new RuntimeException(IndiaDelivery::STALE_SHIPPING_SUPPORT, 422);
        }
    }

    private function createGatewayOrder(Order $order): string
    {
        $locked = Order::query()->find($order->id);

        if (! $locked) {
            throw new RuntimeException('Order not found.', 404);
        }

        $this->refuseIneligibleGatewayReuse($locked);

        if (filled($locked->razorpay_order_id)) {
            return (string) $locked->razorpay_order_id;
        }

        $result = $this->razorpay->createPaymentOrder(
            RefundMoney::paiseFromDecimal((string) $locked->total),
            $locked->order_number,
            [
                'order_id' => (string) $locked->id,
                'customer_email' => $locked->customer_email,
            ]
        );

        if (! $result['success']) {
            throw new RuntimeException($result['message'], $result['status']);
        }

        $locked->refresh();
        $this->refuseIneligibleGatewayReuse($locked);

        if (filled($locked->razorpay_order_id)) {
            return (string) $locked->razorpay_order_id;
        }

        $newId = (string) ($result['data']['order_id'] ?? '');

        if ($newId === '') {
            throw new RuntimeException('Could not create Razorpay order.', 500);
        }

        try {
            $locked->update(['razorpay_order_id' => $newId]);
        } catch (UniqueConstraintViolationException $e) {
            $locked->refresh();
            $this->refuseIneligibleGatewayReuse($locked);

            if (filled($locked->razorpay_order_id)) {
                return (string) $locked->razorpay_order_id;
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->logCreatePersistFailed($locked, $newId, $e);

            throw new RuntimeException(
                'Could not start payment. Please try again.',
                500,
                $e
            );
        }

        $persisted = (string) ($locked->fresh()->razorpay_order_id ?? '');

        if ($persisted === '') {
            $this->logCreatePersistFailed($locked, $newId, new RuntimeException('razorpay_order_id missing after update'));

            throw new RuntimeException('Could not start payment. Please try again.', 500);
        }

        return $persisted;
    }

    /**
     * A stored gateway id is returned only after the order is still eligible.
     */
    private function refuseIneligibleGatewayReuse(Order $locked): void
    {
        if (IndiaDelivery::canInitiateSelfServicePayment($locked)) {
            return;
        }

        throw new RuntimeException(
            IndiaDelivery::isIndia($locked->country)
                ? IndiaDelivery::STALE_SHIPPING_SUPPORT
                : IndiaDelivery::PAYMENT_BLOCKED,
            422
        );
    }

    public function verifyAndComplete(
        Order $order,
        string $razorpayPaymentId,
        string $razorpayOrderId,
        string $razorpaySignature,
    ): void {
        $storedOrderId = $this->requireStoredRazorpayOrderId($order);

        if ($razorpayOrderId !== $storedOrderId) {
            throw new RuntimeException('Payment does not match this order.', 400);
        }

        if (! $this->razorpay->verifySignature($storedOrderId, $razorpayPaymentId, $razorpaySignature)) {
            throw new RuntimeException('Payment verification failed.', 400);
        }

        $result = $this->settleAfterCapture($order, $razorpayPaymentId);
        $fresh = $order->fresh();

        if ($fresh && $fresh->needsPaymentReview()) {
            throw new RazorpayReconciliationRequiredException(
                'Payment was received and your order is under review.',
                409
            );
        }

        $this->throwIfReviewRequired($result);
    }

    public function completeFromGateway(
        Order $order,
        string $razorpayPaymentId,
        string $razorpayOrderId,
    ): string {
        $storedOrderId = $this->requireStoredRazorpayOrderId($order);

        if ($razorpayOrderId !== $storedOrderId) {
            throw new RuntimeException('Payment does not match this order.', 400);
        }

        return $this->settleAfterCapture($order, $razorpayPaymentId);
    }

    private function throwIfReviewRequired(string $result): void
    {
        if (in_array($result, ['reconciliation_required', 'duplicate_capture_flagged'], true)) {
            throw new RazorpayReconciliationRequiredException(
                'Payment was received and your order is under review.',
                409
            );
        }
    }

    /**
     * @return 'paid'|'already_processed'|'reconciliation_required'|'duplicate_capture_flagged'
     */
    private function settleAfterCapture(Order $order, string $razorpayPaymentId): string
    {
        $order->refresh();
        $payment = $this->capturedPayment($order, $razorpayPaymentId);

        try {
            $result = PaymentAtomicLock::run(
                PaymentAtomicLock::forRazorpayOrder((int) $order->id),
                PaymentAtomicLock::razorpayWaitSeconds(),
                fn () => DB::transaction(
                    fn () => $this->settleLockedOrder((int) $order->id, $razorpayPaymentId, $payment)
                )
            );
        } catch (LockTimeoutException) {
            throw new RuntimeException('Payment is already being confirmed. Please wait a moment.', 409);
        }

        $fresh = $order->fresh();
        if (
            $fresh
            && $fresh->isFulfilled()
            && ! $fresh->needsPaymentReview()
            && in_array($result, ['paid', 'already_processed'], true)
        ) {
            $this->notifications->sendPaymentConfirmed($fresh);
        }

        return $result;
    }

    private function requireStoredRazorpayOrderId(Order $order): string
    {
        $stored = (string) $order->razorpay_order_id;

        if ($stored === '') {
            throw new RuntimeException('Payment verification failed.', 400);
        }

        return $stored;
    }

    /**
     * @return array<string, mixed>
     */
    private function capturedPayment(Order $order, string $razorpayPaymentId): array
    {
        $payment = $this->razorpay->fetchPayment($razorpayPaymentId);
        $this->assertPaymentFacts($order, $razorpayPaymentId, $payment);

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function assertPaymentFacts(Order $order, string $razorpayPaymentId, array $payment): void
    {
        $remotePaymentId = (string) ($payment['id'] ?? '');
        $remoteOrderId = (string) ($payment['order_id'] ?? '');
        $remoteStatus = (string) ($payment['status'] ?? '');
        $remoteAmount = (int) ($payment['amount'] ?? 0);
        $remoteCurrency = strtoupper((string) ($payment['currency'] ?? ''));
        $expectedAmount = RazorpayService::amountPaiseFromRupees($order->total);
        $storedOrderId = (string) $order->razorpay_order_id;

        $idOk = $remotePaymentId !== '' && hash_equals($remotePaymentId, $razorpayPaymentId);
        $orderOk = $storedOrderId !== '' && hash_equals($remoteOrderId, $storedOrderId);
        $amountOk = $remoteAmount === $expectedAmount;
        $currencyOk = $remoteCurrency === 'INR';
        $captured = $remoteStatus === 'captured';

        if ($idOk && $orderOk && $amountOk && $currencyOk && $captured) {
            return;
        }

        Log::warning('Razorpay payment did not match the local order.', [
            'event' => 'razorpay.payment_mismatch',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'reason' => 'payment_mismatch',
            'remote_status' => $remoteStatus,
            'id_ok' => $idOk,
            'order_ok' => $orderOk,
            'amount_ok' => $amountOk,
            'currency_ok' => $currencyOk,
        ]);

        if ($remoteStatus === 'authorized' && $idOk && $orderOk && $amountOk && $currencyOk) {
            throw new RuntimeException('Payment is not yet complete. Please wait for confirmation or try again.', 409);
        }

        throw new RuntimeException('Payment verification failed.', 400);
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return 'paid'|'already_processed'|'reconciliation_required'|'duplicate_capture_flagged'
     */
    private function settleLockedOrder(int $orderId, string $paymentId, array $payment): string
    {
        $locked = Order::query()->whereKey($orderId)->lockForUpdate()->first();

        if (! $locked) {
            throw new RuntimeException('Payment verification failed.', 400);
        }

        $this->assertPaymentFacts($locked, $paymentId, $payment);

        if ($this->paymentReferencedByOtherOrder($locked, $paymentId)) {
            if ($this->alreadyRecordedConflict($locked, $paymentId)) {
                return 'reconciliation_required';
            }

            $this->persistReconciliation($locked, null, 'payment_id_conflict', $paymentId);

            return 'reconciliation_required';
        }

        $existing = (string) ($locked->payment_id ?? '');

        if ($existing !== '' && hash_equals($existing, $paymentId)) {
            if ($locked->isReconciliationRequired()) {
                return 'reconciliation_required';
            }

            if ($locked->isFulfilled()) {
                return 'already_processed';
            }
        } elseif ($existing !== '') {
            $this->flagExtraCapture($locked, $paymentId);

            return 'duplicate_capture_flagged';
        }

        if ($locked->isFulfilled()) {
            $locked->update([
                'payment_id' => $paymentId,
                'expires_at' => null,
            ]);

            return 'already_processed';
        }

        if ($locked->isCancelled() || $locked->isExpired() || ! $locked->isPending()) {
            $reason = match (true) {
                $locked->isCancelled() => 'captured_after_cancel',
                $locked->isExpired() => 'captured_after_expiry',
                default => 'captured_after_close',
            };
            $this->persistReconciliation($locked, $paymentId, $reason);

            return 'reconciliation_required';
        }

        $blocker = StockAvailability::deductIfAvailable($locked);

        if ($blocker !== null) {
            $this->persistReconciliation($locked, $paymentId, $blocker);

            return 'reconciliation_required';
        }

        $locked->update([
            'status' => 'paid',
            'payment_id' => $paymentId,
            'expires_at' => null,
        ]);

        return 'paid';
    }

    private function paymentReferencedByOtherOrder(Order $order, string $paymentId): bool
    {
        if (Order::query()->where('payment_id', $paymentId)->whereKeyNot($order->id)->exists()) {
            return true;
        }

        return Order::query()
            ->whereKeyNot($order->id)
            ->where(function ($query) use ($paymentId) {
                $query->whereJsonContains('reconciliation_meta->extra_payment_ids', $paymentId)
                    ->orWhereJsonContains('reconciliation_meta->conflicting_payment_ids', $paymentId);
            })
            ->exists();
    }

    private function alreadyRecordedConflict(Order $order, string $paymentId): bool
    {
        $conflicts = $order->reconciliation_meta['conflicting_payment_ids'] ?? [];

        return $order->isReconciliationRequired()
            && in_array($paymentId, $conflicts, true);
    }

    private function flagExtraCapture(Order $order, string $paymentId): void
    {
        $meta = $order->reconciliation_meta ?? [];
        $extra = array_values($meta['extra_payment_ids'] ?? []);

        if (in_array($paymentId, $extra, true)) {
            return;
        }

        $extra[] = $paymentId;
        $meta['extra_payment_ids'] = $extra;

        $order->update([
            'reconciliation_reason' => $order->reconciliation_reason ?: 'duplicate_capture',
            'reconciliation_meta' => $meta,
            'expires_at' => null,
        ]);

        $this->logReconciliationRequired($order, $paymentId, 'duplicate_capture');
    }

    private function persistReconciliation(Order $order, ?string $paymentId, string $reason, ?string $conflictPaymentId = null): void
    {
        $meta = $order->reconciliation_meta ?? [];

        if ($conflictPaymentId !== null && $conflictPaymentId !== '') {
            $conflicts = array_values($meta['conflicting_payment_ids'] ?? []);
            if (! in_array($conflictPaymentId, $conflicts, true)) {
                $conflicts[] = $conflictPaymentId;
            }
            $meta['conflicting_payment_ids'] = $conflicts;
        }

        $attributes = [
            'status' => Order::STATUS_RECONCILIATION_REQUIRED,
            'reconciliation_reason' => $reason,
            'reconciliation_meta' => $meta === [] ? null : $meta,
            'expires_at' => null,
        ];

        if ($paymentId !== null && $paymentId !== '') {
            $attributes['payment_id'] = $paymentId;
        }

        $order->update($attributes);
        $this->logReconciliationRequired($order, $paymentId ?: (string) $conflictPaymentId, $reason);
    }

    private function logReconciliationRequired(Order $order, string $paymentId, string $reason): void
    {
        Log::warning('Razorpay captured payment requires reconciliation.', [
            'event' => 'razorpay.reconciliation_required',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'reason' => $reason,
            'payment_id' => $paymentId,
        ]);
    }

    private function logCreatePersistFailed(Order $order, string $razorpayOrderId, \Throwable $error): void
    {
        Log::warning('Razorpay order created but local persistence failed.', [
            'event' => 'razorpay.create_persist_failed',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'receipt' => $order->order_number,
            'razorpay_order_id' => $razorpayOrderId,
            'local_status' => $order->status,
            'error' => $error->getMessage(),
        ]);
    }

    /**
     * @return array{order_id: string, amount: int, currency: string}
     */
    private function buildPayload(string $razorpayOrderId, Order $order): array
    {
        return [
            'order_id' => $razorpayOrderId,
            'amount' => RazorpayService::amountPaiseFromRupees($order->total),
            'currency' => 'INR',
        ];
    }
}
