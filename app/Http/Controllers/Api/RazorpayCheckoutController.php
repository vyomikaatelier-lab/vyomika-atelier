<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RazorpayReconciliationRequiredException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\RazorpayService;
use App\Support\CheckoutPayments;
use App\Support\OrderAccess;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RazorpayCheckoutController extends Controller
{
    public function createOrder(Request $request, RazorpayService $razorpay, OrderPaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'store_order_id' => 'required|integer',
        ]);

        return $this->createOrderForStoreOrder($validated['store_order_id'], $payments, $razorpay);
    }

    public function verifyPayment(Request $request, OrderPaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'store_order_id' => 'required|integer',
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $order = Order::query()->find($validated['store_order_id']);

        // Unknown and foreign order IDs answer identically so neither can be
        // used to enumerate order IDs.
        if (! $order || ! OrderAccess::canAccess($order)) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        try {
            $payments->verifyAndComplete(
                $order,
                $validated['razorpay_payment_id'],
                $validated['razorpay_order_id'],
                $validated['razorpay_signature'],
            );
        } catch (RazorpayReconciliationRequiredException $e) {
            return response()->json([
                'message' => 'Payment was received and your order is under review.',
                'redirect' => route('checkout.pay', $order),
            ], 409);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 400);
        }

        return response()->json([
            'success' => true,
            'redirect' => route('checkout.success', $order->fresh()),
        ]);
    }

    private function createOrderForStoreOrder(
        int $storeOrderId,
        OrderPaymentService $payments,
        RazorpayService $razorpay,
    ): JsonResponse {
        $order = Order::query()->find($storeOrderId);

        if (! $order || ! OrderAccess::canAccess($order)) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if (! CheckoutPayments::enabled()) {
            return response()->json(['message' => CheckoutPayments::UNAVAILABLE_MESSAGE], 503);
        }

        if ($order->isReconciliationRequired() || $order->needsPaymentReview()) {
            return response()->json(['message' => 'Payment was received and your order is under review.'], 422);
        }

        if ($order->payment_method !== 'razorpay' || $order->status !== 'pending') {
            return response()->json(['message' => 'This order is not awaiting payment.'], 422);
        }

        if ($order->isExpired()) {
            return response()->json(['message' => 'This order has expired. Please place a new order.'], 410);
        }

        if (! $razorpay->isConfigured()) {
            return response()->json(['message' => 'Razorpay is not configured.'], 401);
        }

        try {
            $payload = $payments->razorpayCheckoutPayload($order);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Payment is already being started. Please wait a moment.'], 409);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 500);
        }

        return response()->json([
            ...$payload,
            'key' => $razorpay->key(),
        ]);
    }
}
