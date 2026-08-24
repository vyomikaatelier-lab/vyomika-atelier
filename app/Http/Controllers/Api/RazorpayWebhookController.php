<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RazorpayReconciliationRequiredException;
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
            Log::warning('Razorpay webhook: order not found.', [
                'razorpay_order_id' => $razorpayOrderId,
            ]);

            return response()->json(['status' => 'order_not_found']);
        }

        if (in_array($order->status, ['paid', 'processing', 'shipped', 'delivered'], true)) {
            return response()->json(['status' => 'already_processed']);
        }

        try {
            $payments->completeFromGateway($order, $paymentId, $razorpayOrderId);
        } catch (RazorpayReconciliationRequiredException $e) {
            return response()->json(['status' => 'reconciliation_required']);
        } catch (RuntimeException $e) {
            Log::error('Razorpay webhook payment completion failed.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            $statusCode = (int) $e->getCode();
            if ($statusCode < 400 || $statusCode > 599) {
                $statusCode = 500;
            }

            return response()->json(['message' => $e->getMessage()], $statusCode);
        }

        return response()->json(['status' => 'ok']);
    }
}
