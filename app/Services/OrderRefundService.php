<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\OrderRefundEvent;
use App\Models\OrderRefundLine;
use App\Models\User;
use App\Support\PaymentAtomicLock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderRefundService
{
    public function __construct(
        private RazorpayService $razorpay,
        private StockRestoration $stock,
    ) {}

    public function start(int $orderId, User $actor, RefundIntent $intent): OrderRefund
    {
        $this->assertKey($intent->idempotencyKey);

        return PaymentAtomicLock::run(
            PaymentAtomicLock::forRazorpayOrder($orderId),
            PaymentAtomicLock::razorpayWaitSeconds(),
            function () use ($orderId, $actor, $intent) {
                $existing = OrderRefund::query()->where('idempotency_key', $intent->idempotencyKey)->first();

                if ($existing) {
                    if ((int) $existing->order_id !== $orderId) {
                        throw new RuntimeException('This refund could not be submitted.', 409);
                    }

                    return $this->submitExisting($existing);
                }

                $order = Order::query()->find($orderId);

                if (! $order) {
                    throw new RuntimeException('This order cannot be refunded.', 404);
                }

                $this->assertStartable($order);

                if ($intent->kind === 'full') {
                    $this->assertConfirmation($order, $intent, 'full');
                }

                $payment = $this->razorpay->fetchPayment((string) $order->payment_id);
                $this->assertCapturedPayment($order, $payment);

                $refund = $this->transaction(function () use ($orderId, $actor, $intent, $payment) {
                    return $this->insertReserved($orderId, $actor, $intent, $payment);
                });

                return $this->submitExisting($refund);
            }
        );
    }

    public function resume(int $orderId, int $refundId): OrderRefund
    {
        $refund = OrderRefund::query()->find($refundId);

        if (! $refund || (int) $refund->order_id !== $orderId) {
            throw new RuntimeException('This refund could not be submitted.', 404);
        }

        return PaymentAtomicLock::run(
            PaymentAtomicLock::forRazorpayOrder($orderId),
            PaymentAtomicLock::razorpayWaitSeconds(),
            fn () => $this->submitExisting($refund->fresh() ?? $refund)
        );
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function applyWebhook(array $entity, string $event, ?string $payloadSha256 = null): string
    {
        $refund = $this->findRefund($entity);

        if (! $refund) {
            return 'ignored';
        }

        return PaymentAtomicLock::run(
            PaymentAtomicLock::forRazorpayOrder((int) $refund->order_id),
            PaymentAtomicLock::razorpayWaitSeconds(),
            function () use ($refund, $entity, $event, $payloadSha256) {
                $projected = $this->razorpay->projectRefund($entity);

                if ($event === 'refund.speed_changed' && ! in_array((string) ($projected['status'] ?? ''), ['pending', 'processed', 'failed'], true)) {
                    $this->transaction(function () use ($refund, $projected, $payloadSha256) {
                        $this->recordEvent($refund->fresh() ?? $refund, 'speed_changed', $payloadSha256, $projected['id'] ?? null);
                    });

                    return 'recorded';
                }

                $this->applyRemote((int) $refund->getKey(), $projected, $event, $payloadSha256);

                return (string) (($refund->fresh()->status) ?? 'recorded');
            }
        );
    }

    public function reconcileFromGateway(OrderRefund $refund): string
    {
        $fresh = $refund->fresh() ?? $refund;

        if (! in_array($fresh->status, OrderRefund::OPEN_STATUSES, true)) {
            return 'unchanged';
        }

        $scan = $this->razorpay->findRefundByReceipt((string) $fresh->payment_id, (string) $fresh->receipt);

        if (($scan['outcome'] ?? '') !== 'found') {
            $this->logRecoveryScan($fresh, $scan);

            return match ($scan['outcome'] ?? '') {
                'limited' => 'scan_limited',
                'repeated' => 'scan_repeated',
                'malformed' => 'scan_malformed',
                default => 'unchanged',
            };
        }

        return PaymentAtomicLock::run(
            PaymentAtomicLock::forRazorpayOrder((int) $fresh->order_id),
            PaymentAtomicLock::razorpayWaitSeconds(),
            function () use ($fresh, $scan) {
                $current = OrderRefund::query()->find($fresh->getKey());

                if (! $current || ! in_array($current->status, OrderRefund::OPEN_STATUSES, true)) {
                    return 'unchanged';
                }

                $this->applyRemote((int) $current->getKey(), $scan['refund'] ?? [], 'recovery', null);

                return 'updated';
            }
        );
    }

    private function submitExisting(OrderRefund $refund): OrderRefund
    {
        $decision = $this->transaction(function () use ($refund) {
            return $this->prepareSubmission((int) $refund->getKey());
        });

        if ($decision['action'] !== 'send') {
            return OrderRefund::query()->findOrFail($refund->getKey());
        }

        $result = $this->razorpay->createRefund(
            $decision['payment_id'],
            $decision['idempotency_key'],
            $decision['body'],
        );

        $this->transaction(function () use ($refund, $result) {
            $this->applyApiResult((int) $refund->getKey(), $result);
        });

        return OrderRefund::query()->findOrFail($refund->getKey());
    }

    /**
     * @return array{action: string, payment_id?: string, idempotency_key?: string, body?: string}
     */
    private function prepareSubmission(int $refundId): array
    {
        $refund = OrderRefund::query()->whereKey($refundId)->firstOrFail();
        $order = Order::query()->whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
        $refund = OrderRefund::query()->whereKey($refundId)->lockForUpdate()->firstOrFail();

        if (in_array($refund->status, [OrderRefund::STATUS_PROCESSED, OrderRefund::STATUS_FAILED, OrderRefund::STATUS_PENDING], true)) {
            return ['action' => 'stop'];
        }

        $body = RazorpayRefundRequest::body($refund, (string) $order->order_number);
        $hash = RazorpayRefundRequest::hash($body);

        if (filled($refund->request_body_sha256) && ! hash_equals((string) $refund->request_body_sha256, $hash)) {
            throw new RuntimeException('Refund request no longer matches the original submission.', 409);
        }

        $refund->forceFill([
            'request_body_sha256' => $hash,
            'status' => OrderRefund::STATUS_SUBMIT_UNCERTAIN,
            'submitted_at' => $refund->submitted_at ?? now(),
            'submit_attempts' => (int) $refund->submit_attempts + 1,
        ])->save();

        $this->summarize($order);
        $this->recordEvent($refund, 'submit_uncertain', $hash, null, null);

        return [
            'action' => 'send',
            'payment_id' => (string) $refund->payment_id,
            'idempotency_key' => (string) $refund->idempotency_key,
            'body' => $body,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function applyApiResult(int $refundId, array $result): void
    {
        $outcome = (string) ($result['outcome'] ?? 'uncertain');

        if ($outcome === 'reconcile') {
            $this->applyRemote($refundId, $result['refund'] ?? [], 'gateway_response', null);

            return;
        }

        $refund = OrderRefund::query()->whereKey($refundId)->firstOrFail();
        $order = Order::query()->whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
        $refund = OrderRefund::query()->whereKey($refundId)->lockForUpdate()->firstOrFail();

        if ($outcome === 'rejected') {
            $this->transition($order, $refund, OrderRefund::STATUS_FAILED, [], 'gateway_rejected', null);
            $refund->forceFill([
                'failure_code' => 'rejected',
                'failed_at' => now(),
            ])->save();
            $this->summarize($order->fresh() ?? $order);

            return;
        }

        $this->recordEvent($refund, 'gateway_uncertain', null, null, null);
        $this->logRefund('razorpay.refund_uncertain', $order, $refund);
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function applyRemote(int $refundId, array $remote, string $event, ?string $payloadSha256): void
    {
        $this->transaction(function () use ($refundId, $remote, $event, $payloadSha256) {
            $this->applyRemoteInsideTransaction($refundId, $remote, $event, $payloadSha256);
        });
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function applyRemoteInsideTransaction(int $refundId, array $remote, string $event, ?string $payloadSha256): void
    {
        $refund = OrderRefund::query()->whereKey($refundId)->firstOrFail();
        $order = Order::query()->whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
        $refund = OrderRefund::query()->whereKey($refundId)->lockForUpdate()->firstOrFail();
        $remoteStatus = (string) ($remote['status'] ?? '');

        if (! $this->remoteMatches($order, $refund, $remote)) {
            $this->recordEvent($refund, 'mismatch', $payloadSha256, $remote['id'] ?? null);
            $this->logRefund('razorpay.refund_mismatch', $order, $refund);

            return;
        }

        $target = match ($remoteStatus) {
            'pending' => OrderRefund::STATUS_PENDING,
            'processed' => OrderRefund::STATUS_PROCESSED,
            'failed' => OrderRefund::STATUS_FAILED,
            default => null,
        };

        if ($target === null) {
            $this->recordEvent($refund, $event, $payloadSha256, $remote['id'] ?? null);

            return;
        }

        if ($refund->status === $target) {
            $refund->forceFill([
                'gateway_status' => $remoteStatus,
                'reconciled_at' => now(),
            ])->save();
            $this->recordEvent($refund, $event, $payloadSha256, $remote['id'] ?? null);

            return;
        }

        if (! $this->canTransition((string) $refund->status, $target)) {
            $this->recordEvent($refund, 'transition_ignored', $payloadSha256, $remote['id'] ?? null);

            return;
        }

        $this->transition($order, $refund, $target, $remote, $event, $payloadSha256);
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function transition(Order $order, OrderRefund $refund, string $target, array $remote, string $event, ?string $payloadSha256): void
    {
        $from = (string) $refund->status;
        $attributes = [
            'status' => $target,
            'gateway_status' => $remote['status'] ?? $refund->gateway_status,
            'reconciled_at' => now(),
        ];

        if (filled($remote['id'] ?? null) && blank($refund->gateway_refund_id)) {
            $attributes['gateway_refund_id'] = (string) $remote['id'];
        }

        if ($target === OrderRefund::STATUS_PROCESSED) {
            $attributes['processed_at'] = now();
            $attributes['failure_code'] = null;
        }

        if ($target === OrderRefund::STATUS_FAILED) {
            $attributes['failed_at'] = now();
            $attributes['failure_code'] = $refund->failure_code ?: 'failed';
        }

        $refund->forceFill($attributes)->save();

        if ($target === OrderRefund::STATUS_PROCESSED) {
            $this->stock->restore($order, $refund->fresh() ?? $refund);
        }

        $this->summarize($order->fresh() ?? $order);

        if ($target === OrderRefund::STATUS_PROCESSED) {
            $this->cancelIfFullyRefunded((int) $order->id);
        }

        $this->recordEvent($refund->fresh() ?? $refund, $event, $payloadSha256, $attributes['gateway_refund_id'] ?? $refund->gateway_refund_id, $from);
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function insertReserved(int $orderId, User $actor, RefundIntent $intent, array $payment): OrderRefund
    {
        $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
        $this->assertStartable($order);

        if ((string) $order->payment_id === '' || ! hash_equals((string) $order->payment_id, (string) ($payment['id'] ?? ''))) {
            throw new RuntimeException('This order cannot be refunded.', 422);
        }

        $captured = (int) ($payment['amount'] ?? 0);
        $local = RefundMoney::paiseFromDecimal((string) $order->total);

        if ($captured !== $local || strtoupper((string) ($payment['currency'] ?? '')) !== 'INR') {
            throw new RuntimeException('The captured amount does not match this order. Refunds are unavailable until the payment is reconciled.', 422);
        }

        if ($order->captured_amount_paise === null) {
            $order->forceFill(['captured_amount_paise' => $captured])->save();
        } elseif ((int) $order->captured_amount_paise !== $captured) {
            throw new RuntimeException('The captured amount does not match this order. Refunds are unavailable until the payment is reconciled.', 422);
        }

        $items = OrderItem::query()->where('order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
        $allocation = $this->allocate($order, $items, $intent);
        $this->assertConfirmation($order, $intent, $allocation['kind']);

        $refund = new OrderRefund;
        $refund->forceFill([
            'order_id' => $order->id,
            'payment_id' => (string) $order->payment_id,
            'idempotency_key' => $intent->idempotencyKey,
            'receipt' => $this->newReceipt(),
            'amount_paise' => $allocation['amount'],
            'currency' => 'INR',
            'kind' => $allocation['kind'],
            'reason_code' => $intent->reasonCode,
            'status' => OrderRefund::STATUS_RESERVED,
            'includes_shipping' => $allocation['shipping'] > 0,
            'shipping_amount_paise' => $allocation['shipping'],
            'actor_user_id' => $actor->id,
            'actor_staff_id' => $actor->staff_id,
            'internal_note' => $intent->internalNote,
            'requested_at' => now(),
        ])->save();

        foreach ($allocation['lines'] as $line) {
            $row = new OrderRefundLine;
            $row->forceFill([
                'order_refund_id' => $refund->id,
                'order_item_id' => $line['order_item_id'],
                'quantity' => $line['quantity'],
                'amount_paise' => $line['amount_paise'],
                'stock_restoration' => OrderRefundLine::STOCK_PENDING,
            ])->save();
        }

        $this->summarize($order->fresh() ?? $order);
        $this->recordEvent($refund, 'reserved', null, null);

        return $refund->fresh() ?? $refund;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OrderItem>  $items
     * @return array{kind: string, amount: int, shipping: int, lines: list<array{order_item_id: int, quantity: int, amount_paise: int}>}
     */
    private function allocate(Order $order, $items, RefundIntent $intent): array
    {
        $captured = (int) $order->captured_amount_paise;
        $committed = $this->committedPaise((int) $order->id);
        $remaining = $captured - $committed;

        if ($remaining < RefundMoney::MINIMUM_PAISE) {
            throw ValidationException::withMessages([
                'refund' => 'Nothing remains on this payment that can be refunded.',
            ]);
        }

        $shippingRemaining = $this->remainingShippingPaise($order);
        $lines = [];
        $linePaise = 0;

        if ($intent->kind === 'full') {
            foreach ($items as $item) {
                $quantity = $this->remainingQuantity($item);
                if ($quantity < 1) {
                    continue;
                }
                $amount = $this->remainingLinePaise($item);
                $lines[] = [
                    'order_item_id' => (int) $item->id,
                    'quantity' => $quantity,
                    'amount_paise' => $amount,
                ];
                $linePaise += $amount;
            }
            $shipping = $shippingRemaining;
            $kind = 'full';
        } else {
            foreach ($intent->lineQuantities as $itemId => $quantity) {
                $quantity = (int) $quantity;
                if ($quantity < 1) {
                    continue;
                }
                $item = $items->firstWhere('id', (int) $itemId);
                if (! $item) {
                    throw ValidationException::withMessages([
                        'lines' => 'One or more items are not on this order.',
                    ]);
                }
                $remainingQuantity = $this->remainingQuantity($item);
                if ($quantity > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        'lines' => 'Refund quantity is higher than the remaining quantity.',
                    ]);
                }
                $amount = RefundMoney::linePaise(
                    RefundMoney::paiseFromDecimal((string) $item->price),
                    RefundMoney::paiseFromDecimal((string) $item->total),
                    $this->refundedLinePaise($item),
                    $quantity,
                    $remainingQuantity,
                );
                $lines[] = [
                    'order_item_id' => (int) $item->id,
                    'quantity' => $quantity,
                    'amount_paise' => $amount,
                ];
                $linePaise += $amount;
            }

            $shipping = $intent->includeShipping ? $shippingRemaining : 0;
            $consumesLines = $this->consumesEveryRemainingLine($items, $lines);
            $consumesShipping = $shipping === $shippingRemaining;
            $kind = ($linePaise + $shipping) === $remaining && $consumesLines && $consumesShipping ? 'full' : 'partial';
        }

        $amount = $linePaise + $shipping;

        if ($amount < RefundMoney::MINIMUM_PAISE) {
            throw ValidationException::withMessages([
                'refund' => 'A refund must be at least 100 paise.',
            ]);
        }

        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'refund' => 'The refund is higher than the amount still captured.',
            ]);
        }

        if ($intent->kind === 'full' && $amount !== $remaining) {
            throw ValidationException::withMessages([
                'refund' => 'The full refund does not match the amount still captured.',
            ]);
        }

        return [
            'kind' => $kind,
            'amount' => $amount,
            'shipping' => $shipping,
            'lines' => $lines,
        ];
    }

    private function assertConfirmation(Order $order, RefundIntent $intent, string $kind): void
    {
        if ($kind !== 'full') {
            return;
        }

        $typed = (string) $intent->orderNumberConfirmation;

        if ($typed === '' || ! hash_equals((string) $order->order_number, $typed)) {
            throw ValidationException::withMessages([
                'order_number_confirmation' => 'Enter the order number exactly to confirm this refund.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function assertCapturedPayment(Order $order, array $payment): void
    {
        $remoteId = (string) ($payment['id'] ?? '');
        $remoteOrderId = (string) ($payment['order_id'] ?? '');
        $storedOrderId = (string) $order->razorpay_order_id;
        $idOk = $remoteId !== '' && hash_equals($remoteId, (string) $order->payment_id);
        $orderOk = $storedOrderId !== '' && hash_equals($remoteOrderId, $storedOrderId);
        $currencyOk = strtoupper((string) ($payment['currency'] ?? '')) === 'INR';
        $captured = (string) ($payment['status'] ?? '') === 'captured';
        $amountOk = (int) ($payment['amount'] ?? 0) === RefundMoney::paiseFromDecimal((string) $order->total);

        if ($idOk && $orderOk && $currencyOk && $captured && $amountOk) {
            return;
        }

        $this->logRefund('razorpay.refund_payment_mismatch', $order, null);

        if (! $captured && $idOk && $orderOk && $currencyOk && $amountOk) {
            throw new RuntimeException('This order cannot be refunded.', 422);
        }

        if (! $amountOk || ! $currencyOk) {
            throw new RuntimeException('The captured amount does not match this order. Refunds are unavailable until the payment is reconciled.', 422);
        }

        throw new RuntimeException('This order cannot be refunded.', 422);
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function remoteMatches(Order $order, OrderRefund $refund, array $remote): bool
    {
        $remoteId = (string) ($remote['id'] ?? '');
        $paymentId = (string) ($remote['payment_id'] ?? '');
        $receipt = (string) ($remote['receipt'] ?? '');
        $currency = strtoupper((string) ($remote['currency'] ?? ''));
        $status = (string) ($remote['status'] ?? '');
        $amount = (int) ($remote['amount'] ?? -1);
        $notes = is_array($remote['notes'] ?? null) ? $remote['notes'] : [];

        if ($remoteId === '' || ! in_array($status, ['pending', 'processed', 'failed'], true)) {
            return false;
        }

        if (! hash_equals((string) $refund->payment_id, $paymentId)) {
            return false;
        }

        if ($amount !== (int) $refund->amount_paise || $currency !== 'INR' || $receipt !== (string) $refund->receipt) {
            return false;
        }

        if (filled($refund->gateway_refund_id) && ! hash_equals((string) $refund->gateway_refund_id, $remoteId)) {
            return false;
        }

        if (isset($notes['receipt']) && (string) $notes['receipt'] !== (string) $refund->receipt) {
            return false;
        }

        if (isset($notes['refund_id']) && (string) $notes['refund_id'] !== (string) $refund->getKey()) {
            return false;
        }

        if (isset($notes['order_number']) && (string) $notes['order_number'] !== (string) $order->order_number) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function findRefund(array $entity): ?OrderRefund
    {
        $gatewayId = (string) ($entity['id'] ?? '');
        $receipt = (string) ($entity['receipt'] ?? '');

        if ($gatewayId !== '') {
            $byGateway = OrderRefund::query()->where('gateway_refund_id', $gatewayId)->first();
            if ($byGateway) {
                return $byGateway;
            }
        }

        if ($receipt !== '') {
            return OrderRefund::query()->where('receipt', $receipt)->first();
        }

        return null;
    }

    private function assertStartable(Order $order): void
    {
        if ($order->needsPaymentReview()) {
            throw new RuntimeException('This order is awaiting payment reconciliation and cannot be refunded here.', 422);
        }

        if (! $order->canOfferRefund()) {
            throw new RuntimeException('This order cannot be refunded.', 422);
        }
    }

    private function assertKey(string $key): void
    {
        if (! RazorpayRefundRequest::validIdempotencyKey($key)) {
            throw new RuntimeException('This refund could not be submitted.', 422);
        }
    }

    private function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return match ($from) {
            OrderRefund::STATUS_RESERVED => in_array($to, [
                OrderRefund::STATUS_SUBMIT_UNCERTAIN,
                OrderRefund::STATUS_PENDING,
                OrderRefund::STATUS_PROCESSED,
                OrderRefund::STATUS_FAILED,
            ], true),
            OrderRefund::STATUS_SUBMIT_UNCERTAIN => in_array($to, [
                OrderRefund::STATUS_PENDING,
                OrderRefund::STATUS_PROCESSED,
                OrderRefund::STATUS_FAILED,
            ], true),
            OrderRefund::STATUS_PENDING => in_array($to, [
                OrderRefund::STATUS_PROCESSED,
                OrderRefund::STATUS_FAILED,
            ], true),
            default => false,
        };
    }

    private function cancelIfFullyRefunded(int $orderId): void
    {
        $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

        if (! $order || ! $order->isFulfilled() || $order->needsPaymentReview() || $order->status === 'cancelled') {
            return;
        }

        if ((string) $order->refund_status !== 'refunded' || (int) $order->refund_pending_amount_paise !== 0) {
            return;
        }

        $captured = (int) ($order->captured_amount_paise ?? 0);

        if ($captured < RefundMoney::MINIMUM_PAISE || (int) $order->refunded_amount_paise !== $captured) {
            return;
        }

        $open = OrderRefund::query()
            ->where('order_id', $order->id)
            ->whereIn('status', OrderRefund::OPEN_STATUSES)
            ->exists();

        if ($open) {
            return;
        }

        $order->forceFill(['status' => 'cancelled'])->save();
    }

    /**
     * @param  array{outcome?: string, reason?: string, pages?: int}  $scan
     */
    private function logRecoveryScan(OrderRefund $refund, array $scan): void
    {
        $reason = (string) ($scan['reason'] ?? '');

        if (! in_array($reason, ['recovery_scan_limit_reached', 'recovery_page_repeated', 'recovery_scan_malformed'], true)) {
            return;
        }

        Log::warning('Razorpay refund recovery scan stopped.', [
            'event' => 'razorpay.refund_recovery_scan',
            'order_id' => $refund->order_id,
            'refund_id' => $refund->getKey(),
            'pages' => (int) ($scan['pages'] ?? 0),
            'reason' => $reason,
        ]);
    }

    private function summarize(Order $order): void
    {
        $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

        if (! $locked) {
            return;
        }

        $processed = (int) OrderRefund::query()
            ->where('order_id', $locked->id)
            ->where('status', OrderRefund::STATUS_PROCESSED)
            ->sum('amount_paise');
        $inflight = (int) OrderRefund::query()
            ->where('order_id', $locked->id)
            ->whereIn('status', OrderRefund::OPEN_STATUSES)
            ->sum('amount_paise');
        $captured = (int) ($locked->captured_amount_paise ?? 0);
        $failedOnly = $processed === 0
            && $inflight === 0
            && OrderRefund::query()->where('order_id', $locked->id)->where('status', OrderRefund::STATUS_FAILED)->exists();

        $status = 'none';
        if ($inflight > 0) {
            $status = 'pending';
        } elseif ($captured > 0 && $processed >= $captured) {
            $status = 'refunded';
        } elseif ($processed > 0) {
            $status = 'partial';
        } elseif ($failedOnly) {
            $status = 'failed';
        }

        $locked->forceFill([
            'refunded_amount_paise' => $processed,
            'refund_pending_amount_paise' => $inflight,
            'refund_status' => $status,
        ])->save();
    }

    private function committedPaise(int $orderId): int
    {
        return (int) OrderRefund::query()
            ->where('order_id', $orderId)
            ->whereIn('status', OrderRefund::COMMITTED_STATUSES)
            ->sum('amount_paise');
    }

    private function remainingShippingPaise(Order $order): int
    {
        $shipping = RefundMoney::paiseFromDecimal((string) $order->shipping_cost);
        $used = (int) OrderRefund::query()
            ->where('order_id', $order->id)
            ->whereIn('status', OrderRefund::COMMITTED_STATUSES)
            ->sum('shipping_amount_paise');

        return max(0, $shipping - $used);
    }

    private function remainingQuantity(OrderItem $item): int
    {
        $used = (int) OrderRefundLine::query()
            ->where('order_item_id', $item->id)
            ->whereHas('refund', function ($query) {
                $query->whereIn('status', OrderRefund::COMMITTED_STATUSES);
            })
            ->sum('quantity');

        return max(0, (int) $item->quantity - $used);
    }

    private function refundedLinePaise(OrderItem $item): int
    {
        return (int) OrderRefundLine::query()
            ->where('order_item_id', $item->id)
            ->whereHas('refund', function ($query) {
                $query->whereIn('status', OrderRefund::COMMITTED_STATUSES);
            })
            ->sum('amount_paise');
    }

    private function remainingLinePaise(OrderItem $item): int
    {
        return max(0, RefundMoney::paiseFromDecimal((string) $item->total) - $this->refundedLinePaise($item));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OrderItem>  $items
     * @param  list<array{order_item_id: int, quantity: int, amount_paise: int}>  $lines
     */
    private function consumesEveryRemainingLine($items, array $lines): bool
    {
        $allocated = [];
        foreach ($lines as $line) {
            $allocated[$line['order_item_id']] = $line['quantity'];
        }

        foreach ($items as $item) {
            $remaining = $this->remainingQuantity($item);
            if ($remaining < 1) {
                continue;
            }
            if (($allocated[$item->id] ?? 0) !== $remaining) {
                return false;
            }
        }

        return true;
    }

    private function newReceipt(): string
    {
        return 'rf'.bin2hex(random_bytes(16));
    }

    private function recordEvent(OrderRefund $refund, string $event, ?string $payloadSha256, mixed $gatewayRefundId, ?string $from = null): void
    {
        $current = $refund->fresh();

        OrderRefundEvent::query()->create([
            'order_refund_id' => $refund->getKey(),
            'event' => $event,
            'from_status' => $from ?? $refund->status,
            'to_status' => $current?->status ?? $refund->status,
            'gateway_refund_id' => filled($gatewayRefundId) ? (string) $gatewayRefundId : $refund->gateway_refund_id,
            'amount_paise' => (int) $refund->amount_paise,
            'payload_sha256' => $payloadSha256,
            'created_at' => now(),
        ]);
    }

    private function logRefund(string $event, Order $order, ?OrderRefund $refund): void
    {
        Log::warning('Razorpay refund requires review.', [
            'event' => $event,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'refund_id' => $refund?->getKey(),
            'amount_paise' => $refund?->amount_paise,
            'status' => $refund?->status,
            'reason_code' => $refund?->reason_code,
        ]);
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    private function transaction(callable $callback): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                return DB::transaction($callback);
            } catch (QueryException $exception) {
                if (! $this->isDeadlock($exception) || $attempt >= 1) {
                    throw $exception;
                }

                $attempt++;
            }
        }
    }

    private function isDeadlock(QueryException $exception): bool
    {
        $state = (string) ($exception->errorInfo[0] ?? '');
        $code = (int) ($exception->errorInfo[1] ?? 0);

        return $state === '40001'
            || $code === 1213
            || str_contains(strtolower($exception->getMessage()), 'deadlock');
    }
}
