<?php

namespace App\Services;

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
        if ($order->razorpay_order_id && $razorpayOrderId !== $order->razorpay_order_id) {
            throw new RuntimeException('Payment does not match this order.', 400);
        }

        if (! $this->razorpay->verifySignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature)) {
            throw new RuntimeException('Payment verification failed.', 400);
        }

        try {
            DB::transaction(function () use ($order, $razorpayPaymentId, $razorpayOrderId) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

                if ($locked->status !== 'pending') {
                    return;
                }

                $locked->update([
                    'status' => 'paid',
                    'payment_id' => $razorpayPaymentId,
                    'razorpay_order_id' => $razorpayOrderId,
                    'expires_at' => null,
                ]);

                StockAvailability::deductForPaidOrder($locked->fresh('items.product'));
            });
        } catch (RuntimeException $e) {
            Log::error('Payment verified but stock deduction failed.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Payment was received but stock is no longer available. Please contact us.',
                409
            );
        }

        $order->refresh();

        if ($order->status === 'paid') {
            $this->notifications->sendPaymentConfirmed($order);
        }
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
