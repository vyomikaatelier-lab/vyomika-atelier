<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RazorpayReconciliationRequiredException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentWebhookReceipt;
use App\Services\OrderPaymentService;
use App\Services\OrderRefundService;
use App\Services\RazorpayService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpayService $razorpay, OrderPaymentService $payments, OrderRefundService $refunds): JsonResponse
    {
        $signature = $request->header('X-Razorpay-Signature', '');
        $body = $request->getContent();

        if (! $razorpay->verifyWebhookSignature($body, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? '';

        if (in_array($event, ['refund.created', 'refund.processed', 'refund.failed', 'refund.speed_changed'], true)) {
            return $this->refundWebhook($body, $payload, (string) $event, $refunds);
        }

        if (! in_array($event, ['payment.captured', 'order.paid'], true)) {
            return response()->json(['status' => 'ignored']);
        }

        $digest = hash('sha256', $body);
        $payment = data_get($payload, 'payload.payment.entity', []);
        $razorpayOrderId = $payment['order_id'] ?? data_get($payload, 'payload.order.entity.id');
        $paymentId = $payment['id'] ?? null;
        $status = $payment['status'] ?? null;

        if (! $razorpayOrderId || ! $paymentId) {
            $this->rememberReceipt($digest, (string) $event, null, is_string($razorpayOrderId) ? $razorpayOrderId : null, is_string($paymentId) ? $paymentId : null, 'missing_payment_data');

            return response()->json(['message' => 'Missing payment data.'], 422);
        }

        if ($status && $status !== 'captured') {
            $this->rememberReceipt($digest, (string) $event, null, (string) $razorpayOrderId, (string) $paymentId, 'ignored');

            return response()->json(['status' => 'ignored']);
        }

        $order = Order::query()->where('razorpay_order_id', $razorpayOrderId)->first();

        if (! $order) {
            Log::warning('Razorpay webhook: order not found.', [
                'razorpay_order_id' => $razorpayOrderId,
            ]);
            $this->rememberReceipt($digest, (string) $event, null, (string) $razorpayOrderId, (string) $paymentId, 'order_not_found');

            return response()->json(['status' => 'order_not_found']);
        }

        try {
            $result = $payments->completeFromGateway($order, $paymentId, $razorpayOrderId);
        } catch (RazorpayReconciliationRequiredException $e) {
            $this->rememberReceipt($digest, (string) $event, $order->id, (string) $razorpayOrderId, (string) $paymentId, 'reconciliation_required');

            return response()->json(['status' => 'reconciliation_required']);
        } catch (RuntimeException $e) {
            Log::error('Razorpay webhook payment completion failed.', [
                'event' => 'razorpay.webhook_completion_failed',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'reason' => 'completion_failed',
            ]);
            $this->rememberReceipt($digest, (string) $event, $order->id, (string) $razorpayOrderId, (string) $paymentId, 'completion_failed');

            $statusCode = (int) $e->getCode();
            if ($statusCode < 400 || $statusCode > 599) {
                $statusCode = 500;
            }

            return response()->json(['message' => 'Payment could not be confirmed.'], $statusCode);
        }

        $outcome = $result === 'paid' ? 'paid' : (string) $result;
        $this->rememberReceipt($digest, (string) $event, $order->id, (string) $razorpayOrderId, (string) $paymentId, $outcome);

        $status = $result === 'paid' ? 'ok' : $result;

        return response()->json(['status' => $status]);
    }

    private function rememberReceipt(
        string $digest,
        string $event,
        ?int $orderId,
        ?string $razorpayOrderId,
        ?string $paymentId,
        string $outcome,
    ): void {
        PaymentWebhookReceipt::record($digest, $event, $orderId, $razorpayOrderId, $paymentId, $outcome);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function refundWebhook(string $body, array $payload, string $event, OrderRefundService $refunds): JsonResponse
    {
        $entity = data_get($payload, 'payload.refund.entity');

        if (! is_array($entity)) {
            return response()->json(['message' => 'Refund could not be confirmed.'], 422);
        }

        try {
            $result = $refunds->applyWebhook($entity, $event, hash('sha256', $body));
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Refund could not be confirmed.'], 500);
        } catch (RuntimeException) {
            Log::error('Razorpay refund webhook could not be recorded.', [
                'event' => 'razorpay.refund_webhook_failed',
            ]);

            return response()->json(['message' => 'Refund could not be confirmed.'], 500);
        }

        return response()->json(['status' => $result]);
    }
}
