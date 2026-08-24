<?php

namespace App\Services;

use App\Exceptions\RazorpayReconciliationRequiredException;
use App\Models\Order;
use App\Services\StockAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderPaymentService
{
    public function __construct(
        private RazorpayService $razorpay,
        private OrderNotificationService $notifications,
        private CartService $cart,
    ) {}

    /**
     * @return array{order_id: string, amount: int, currency: string}
     */
    public function razorpayCheckoutPayload(Order $order): array
    {
        if ($order->razorpay_order_id) {
            return $this->buildPayload($order->razorpay_order_id, $order);
        }

        $result = $this->razorpay->createPaymentOrder(
            RazorpayService::amountPaiseFromRupees($order->total),
            $order->order_number,
            [
                'order_id' => (string) $order->id,
                'customer_email' => $order->customer_email,
            ]
        );

        if (! $result['success']) {
            throw new RuntimeException($result['message'], $result['status']);
        }

        $order->update(['razorpay_order_id' => $result['data']['order_id']]);

        return $this->buildPayload($result['data']['order_id'], $order);
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

        $this->confirmCapturedPaymentThenComplete($order, $razorpayPaymentId);
    }

    public function completeFromGateway(
        Order $order,
        string $razorpayPaymentId,
        string $razorpayOrderId,
    ): void {
        $storedOrderId = $this->requireStoredRazorpayOrderId($order);

        if ($razorpayOrderId !== $storedOrderId) {
            throw new RuntimeException('Payment does not match this order.', 400);
        }

        $this->confirmCapturedPaymentThenComplete($order, $razorpayPaymentId);
    }

    private function confirmCapturedPaymentThenComplete(Order $order, string $razorpayPaymentId): void
    {
        $order->refresh();

        if (in_array($order->status, ['paid', 'processing', 'shipped', 'delivered'], true)) {
            return;
        }

        $this->assertCapturedPaymentMatchesOrder($order, $razorpayPaymentId);

        $order->refresh();

        if ($order->status === 'cancelled' || $order->isExpired()) {
            $this->logReconciliationRequired($order, $razorpayPaymentId);

            throw new RazorpayReconciliationRequiredException(
                'Payment was received but this order needs review. Please contact the studio with your order number.',
                409
            );
        }

        if ($order->status !== 'pending') {
            return;
        }

        $this->fulfilPaidOrder($order, $razorpayPaymentId);
    }

    private function requireStoredRazorpayOrderId(Order $order): string
    {
        $stored = (string) $order->razorpay_order_id;

        if ($stored === '') {
            throw new RuntimeException('Payment verification failed.', 400);
        }

        return $stored;
    }

    private function assertCapturedPaymentMatchesOrder(Order $order, string $razorpayPaymentId): void
    {
        $payment = $this->razorpay->fetchPayment($razorpayPaymentId);

        $remotePaymentId = (string) ($payment['id'] ?? '');
        $remoteOrderId = (string) ($payment['order_id'] ?? '');
        $remoteStatus = (string) ($payment['status'] ?? '');
        $remoteAmount = (int) ($payment['amount'] ?? 0);
        $remoteCurrency = strtoupper((string) ($payment['currency'] ?? ''));
        $expectedAmount = RazorpayService::amountPaiseFromRupees($order->total);
        $storedOrderId = (string) $order->razorpay_order_id;

        $idOk = hash_equals($remotePaymentId, $razorpayPaymentId);
        $orderOk = hash_equals($remoteOrderId, $storedOrderId);
        $amountOk = $remoteAmount === $expectedAmount;
        $currencyOk = $remoteCurrency === 'INR';
        $captured = $remoteStatus === 'captured';

        if ($idOk && $orderOk && $amountOk && $currencyOk && $captured) {
            return;
        }

        Log::warning('Razorpay payment did not match the local order.', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_id' => $razorpayPaymentId,
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

    private function fulfilPaidOrder(Order $order, string $razorpayPaymentId): void
    {
        try {
            DB::transaction(function () use ($order, $razorpayPaymentId) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

                if (! $locked || $locked->status !== 'pending') {
                    return;
                }

                $locked->update([
                    'status' => 'paid',
                    'payment_id' => $razorpayPaymentId,
                    'expires_at' => null,
                ]);

                StockAvailability::deductForPaidOrder($locked->fresh('items.product'));
            });
        } catch (RuntimeException $e) {
            Log::error('Payment confirmed but stock deduction failed.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Payment was received but stock is no longer available. Please contact us.',
                409
            );
        }

        $order->refresh();

        if ($order->status === 'paid') {
            if (session(CartService::CHECKOUT_SOURCE_KEY) === 'buy_now') {
                $this->cart->clearBuyNow();
                session()->forget(CartService::CHECKOUT_SOURCE_KEY);
            } else {
                $this->cart->clear();
            }
            $this->notifications->sendPaymentConfirmed($order);
        }
    }

    private function logReconciliationRequired(Order $order, string $razorpayPaymentId): void
    {
        Log::warning('Razorpay captured payment requires reconciliation.', [
            'event' => 'razorpay.reconciliation_required',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'local_status' => $order->status,
            'expired' => $order->isExpired(),
            'razorpay_order_id' => $order->razorpay_order_id,
            'payment_id' => $razorpayPaymentId,
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
