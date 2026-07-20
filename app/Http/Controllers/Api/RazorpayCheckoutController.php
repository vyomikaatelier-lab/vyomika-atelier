<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\RazorpayService;
use App\Support\OrderAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RazorpayCheckoutController extends Controller
{
    public function createOrder(Request $request, RazorpayService $razorpay, OrderPaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'store_order_id' => 'nullable|integer|exists:orders,id',
            'amount' => 'nullable|integer|min:'.RazorpayService::MIN_AMOUNT_PAISE,
            'currency' => 'nullable|string|in:INR',
            'receipt' => 'nullable|string|max:40',
        ]);

        if (isset($validated['store_order_id'])) {
            return $this->createOrderForStoreOrder($validated['store_order_id'], $payments, $razorpay);
        }

        $validated = $request->validate([
            'amount' => 'required|integer|min:'.RazorpayService::MIN_AMOUNT_PAISE,
            'currency' => 'required|string|in:INR',
            'receipt' => 'required|string|max:40',
        ]);

        $result = $razorpay->createPaymentOrder(
            $validated['amount'],
            $validated['receipt']
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            ...$result['data'],
            'key' => $razorpay->key(),
        ]);
    }

    public function verifyPayment(Request $request, OrderPaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'store_order_id' => 'required|integer|exists:orders,id',
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $order = Order::query()->findOrFail($validated['store_order_id']);

        if (! OrderAccess::canAccess($order)) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => true,
                'redirect' => route('checkout.success', $order),
            ]);
        }

        if ($order->isExpired()) {
            return response()->json(['message' => 'This order has expired. Please place a new order.'], 410);
        }

        try {
            $payments->verifyAndComplete(
                $order,
                $validated['razorpay_payment_id'],
                $validated['razorpay_order_id'],
                $validated['razorpay_signature'],
            );
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
        $order = Order::query()->findOrFail($storeOrderId);

        if (! OrderAccess::canAccess($order)) {
            return response()->json(['message' => 'Order not found.'], 404);
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
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 500);
        }

        return response()->json([
            ...$payload,
            'key' => $razorpay->key(),
        ]);
    }
}
