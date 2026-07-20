<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpayService $razorpay, OrderPaymentService $payments): JsonResponse
    {
        $signature = $request->header('X-Razorpay-Signature', '');
        $body = $request->getContent();

        if (! $razorpay->verifyWebhookSignature($body, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? '';

        if (! in_array($event, ['payment.captured', 'order.paid'], true)) {
            return response()->json(['status' => 'ignored']);
        }

        $payment = data_get($payload, 'payload.payment.entity', []);
        $razorpayOrderId = $payment['order_id'] ?? data_get($payload, 'payload.order.entity.id');
        $paymentId = $payment['id'] ?? null;
        $status = $payment['status'] ?? null;

        if (! $razorpayOrderId || ! $paymentId) {
            return response()->json(['message' => 'Missing payment data.'], 422);
        }

        if ($status && $status !== 'captured') {
            return response()->json(['status' => 'ignored']);
        }

        $order = Order::query()->where('razorpay_order_id', $razorpayOrderId)->first();

        if (! $order) {
            Log::warning('Razorpay webhook: order not found.', ['razorpay_order_id' => $razorpayOrderId]);

            return response()->json(['status' => 'order_not_found']);
        }

        if ($order->status !== 'pending') {
            return response()->json(['status' => 'already_processed']);
        }

        try {
            $payments->completeFromGateway($order, $paymentId, $razorpayOrderId);
        } catch (RuntimeException $e) {
            Log::error('Razorpay webhook payment completion failed.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 500);
        }

        return response()->json(['status' => 'ok']);
    }
}
