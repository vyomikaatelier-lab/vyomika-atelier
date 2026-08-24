<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RazorpayService
{
    public const MIN_AMOUNT_PAISE = 100;

    public function isConfigured(): bool
    {
        return filled($this->key()) && filled($this->secret());
    }

    public function key(): ?string
    {
        return config('services.razorpay.key') ?: null;
    }

    public function secret(): ?string
    {
        return config('services.razorpay.secret') ?: null;
    }

    public static function amountPaiseFromRupees(float|int|string $rupees): int
    {
        return (int) round(((float) $rupees) * 100);
    }

    public function createOrder(Order $order): ?array
    {
        $result = $this->createPaymentOrder(
            self::amountPaiseFromRupees($order->total),
            $order->order_number,
            [
                'order_id' => (string) $order->id,
                'customer_email' => $order->customer_email,
            ]
        );

        if (! $result['success']) {
            return null;
        }

        return [
            'id' => $result['data']['order_id'],
            'amount' => $result['data']['amount'],
            'currency' => $result['data']['currency'],
        ];
    }

    /**
     * @param  array<string, string>  $notes
     * @return array{success: bool, status: int, message: string, data?: array{order_id: string, amount: int, currency: string}}
     */
    public function createPaymentOrder(int $amountPaise, string $receipt, array $notes = []): array
    {
        if ($amountPaise < self::MIN_AMOUNT_PAISE) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Amount must be at least 100 paise.',
            ];
        }

        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'status' => 401,
                'message' => 'Razorpay is not configured.',
            ];
        }

        $response = $this->api()->post('https://api.razorpay.com/v1/orders', [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => $receipt,
            'notes' => $notes,
        ]);

        return $this->mapCreateOrderResponse($response);
    }

    /**
     * @return array{success: bool, status: int, message: string, data?: array{order_id: string, amount: int, currency: string}}
     */
    private function mapCreateOrderResponse(Response $response): array
    {
        if ($response->status() === 401) {
            return [
                'success' => false,
                'status' => 401,
                'message' => 'Razorpay authentication failed.',
            ];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Could not create Razorpay order.',
            ];
        }

        $data = $response->json();

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Order created.',
            'data' => [
                'order_id' => $data['id'],
                'amount' => (int) ($data['amount'] ?? 0),
                'currency' => $data['currency'] ?? 'INR',
            ],
        ];
    }

    public function verifySignature(string $razorpayOrderId, string $paymentId, string $signature): bool
    {
        $payload = $razorpayOrderId.'|'.$paymentId;
        $expected = hash_hmac('sha256', $payload, (string) $this->secret());

        return hash_equals($expected, $signature);
    }

    /**
     * Fetch a payment from Razorpay. Never returns raw gateway error bodies to callers.
     *
     * @return array<string, mixed>
     */
    public function fetchPayment(string $paymentId): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Payment confirmation is temporarily unavailable. Please wait a moment.', 503);
        }

        try {
            $response = $this->api()->get('https://api.razorpay.com/v1/payments/'.$paymentId);
        } catch (ConnectionException $e) {
            Log::error('Razorpay payment fetch failed.', [
                'payment_id' => $paymentId,
                'error' => 'connection_failed',
            ]);

            throw new RuntimeException('Payment confirmation is temporarily unavailable. Please wait a moment.', 503);
        }

        if (! $response->successful()) {
            Log::error('Razorpay payment fetch failed.', [
                'payment_id' => $paymentId,
                'http_status' => $response->status(),
            ]);

            throw new RuntimeException('Payment confirmation is temporarily unavailable. Please wait a moment.', 503);
        }

        $data = $response->json();

        if (! is_array($data) || ! filled($data['id'] ?? null)) {
            Log::error('Razorpay payment fetch returned an unexpected payload.', [
                'payment_id' => $paymentId,
            ]);

            throw new RuntimeException('Payment confirmation is temporarily unavailable. Please wait a moment.', 503);
        }

        return $data;
    }

    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        $secret = config('services.razorpay.webhook_secret');

        if (! filled($secret)) {
            return false;
        }

        $expected = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }

    private function api()
    {
        return Http::withBasicAuth($this->key(), $this->secret());
    }
}
