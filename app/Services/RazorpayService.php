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

        try {
            $response = $this->createOrderApi()->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $receipt,
                'notes' => $notes,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Razorpay order create connection failed.', [
                'event' => 'razorpay.create_connection_failed',
                'receipt' => $receipt,
                'error' => 'connection_failed',
            ]);

            return [
                'success' => false,
                'status' => 503,
                'message' => 'Could not create Razorpay order.',
            ];
        }

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
        $secret = (string) $this->secret();

        // An absent secret would otherwise HMAC with an empty key, which any
        // caller can reproduce. Never treat that as a verified signature.
        if ($secret === '') {
            return false;
        }

        $payload = $razorpayOrderId.'|'.$paymentId;
        $expected = hash_hmac('sha256', $payload, $secret);

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

    /**
     * @return array{outcome: string, http_status: int, failure_code?: string, refund?: array<string, mixed>}
     */
    public function createRefund(string $paymentId, string $idempotencyKey, string $body): array
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $paymentId) || ! preg_match('/^[A-Za-z0-9_-]{10,64}$/', $idempotencyKey)) {
            return [
                'outcome' => 'rejected',
                'http_status' => 422,
                'failure_code' => 'rejected',
            ];
        }

        try {
            $response = $this->api()
                ->withHeaders(['X-Refund-Idempotency' => $idempotencyKey])
                ->withBody($body, 'application/json')
                ->post('https://api.razorpay.com/v1/payments/'.$paymentId.'/refund');
        } catch (ConnectionException) {
            $this->logRefundTransport(0);

            return [
                'outcome' => 'uncertain',
                'http_status' => 0,
            ];
        }

        $status = $response->status();

        if (in_array($status, [408, 409, 429], true) || $status >= 500) {
            $this->logRefundTransport($status);

            return [
                'outcome' => 'uncertain',
                'http_status' => $status,
            ];
        }

        if (in_array($status, [400, 404, 422], true)) {
            $this->logRefundTransport($status);

            return [
                'outcome' => 'rejected',
                'http_status' => $status,
                'failure_code' => 'rejected',
            ];
        }

        if (! $response->successful()) {
            $this->logRefundTransport($status);

            return [
                'outcome' => 'uncertain',
                'http_status' => $status,
            ];
        }

        $data = $response->json();

        if (! is_array($data)) {
            $this->logRefundTransport($status);

            return [
                'outcome' => 'uncertain',
                'http_status' => $status,
            ];
        }

        return [
            'outcome' => 'reconcile',
            'http_status' => $status,
            'refund' => $this->projectRefund($data),
        ];
    }

    /**
     * Read Razorpay refund pages until the receipt is found or the scan must stop.
     * This does not create a refund.
     *
     * @return array{outcome: string, reason: string, pages: int, refund?: array<string, mixed>}
     */
    public function findRefundByReceipt(string $paymentId, string $receipt): array
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $paymentId) || $receipt === '') {
            return $this->recoveryScan('malformed', 'recovery_scan_malformed', 0);
        }

        $maxPages = $this->recoveryMaxPages();
        $skip = 0;
        $count = 100;
        $pages = 0;
        $seen = [];

        while ($pages < $maxPages) {
            $pages++;

            try {
                $response = $this->api()->get('https://api.razorpay.com/v1/payments/'.$paymentId.'/refunds', [
                    'count' => $count,
                    'skip' => $skip,
                ]);
            } catch (ConnectionException) {
                $this->logRefundTransport(0);

                return $this->recoveryScan('malformed', 'recovery_scan_malformed', $pages);
            }

            if (! $response->successful()) {
                $this->logRefundTransport($response->status());

                return $this->recoveryScan('malformed', 'recovery_scan_malformed', $pages);
            }

            $data = $response->json();
            $items = is_array($data) ? ($data['items'] ?? null) : null;

            if (! is_array($items) || ! array_is_list($items)) {
                return $this->recoveryScan('malformed', 'recovery_scan_malformed', $pages);
            }

            if ($items === []) {
                return $this->recoveryScan('absent', '', $pages);
            }

            $fingerprint = hash('sha256', json_encode(array_map(function ($item) {
                if (! is_array($item)) {
                    return 'invalid';
                }

                return (string) ($item['id'] ?? '').'|'.(string) ($item['receipt'] ?? '');
            }, $items)));

            if (isset($seen[$fingerprint])) {
                return $this->recoveryScan('repeated', 'recovery_page_repeated', $pages);
            }

            $seen[$fingerprint] = true;
            $projected = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    return $this->recoveryScan('malformed', 'recovery_scan_malformed', $pages);
                }

                $row = $this->projectRefund($item);
                $projected[] = $row;

                if ((string) ($row['receipt'] ?? '') === $receipt) {
                    return [
                        'outcome' => 'found',
                        'reason' => '',
                        'pages' => $pages,
                        'refund' => $row,
                    ];
                }
            }

            $received = count($projected);

            if ($received < $count) {
                return $this->recoveryScan('absent', '', $pages);
            }

            $nextSkip = $skip + $received;

            if ($nextSkip <= $skip) {
                return $this->recoveryScan('repeated', 'recovery_page_repeated', $pages);
            }

            $skip = $nextSkip;
        }

        return $this->recoveryScan('limited', 'recovery_scan_limit_reached', $pages);
    }

    public function recoveryMaxPages(): int
    {
        $configured = config('checkout.refund_recovery_max_pages', 100);
        $pages = is_int($configured)
            ? $configured
            : (is_string($configured) && preg_match('/^[1-9]\d*$/', $configured) === 1 ? (int) $configured : 0);

        if ($pages < 1 || $pages > 100) {
            return 100;
        }

        return $pages;
    }

    /**
     * @return array{outcome: string, reason: string, pages: int}
     */
    private function recoveryScan(string $outcome, string $reason, int $pages): array
    {
        return [
            'outcome' => $outcome,
            'reason' => $reason,
            'pages' => $pages,
        ];
    }

    /**
     * @param  array<string, mixed>  $refund
     * @return array<string, mixed>
     */
    public function projectRefund(array $refund): array
    {
        $notes = is_array($refund['notes'] ?? null) ? $refund['notes'] : [];

        return [
            'id' => isset($refund['id']) ? (string) $refund['id'] : '',
            'payment_id' => isset($refund['payment_id']) ? (string) $refund['payment_id'] : '',
            'amount' => (int) ($refund['amount'] ?? 0),
            'currency' => isset($refund['currency']) ? (string) $refund['currency'] : '',
            'status' => isset($refund['status']) ? (string) $refund['status'] : '',
            'receipt' => isset($refund['receipt']) ? (string) $refund['receipt'] : '',
            'notes' => [
                'order_number' => isset($notes['order_number']) ? (string) $notes['order_number'] : null,
                'refund_id' => isset($notes['refund_id']) ? (string) $notes['refund_id'] : null,
                'receipt' => isset($notes['receipt']) ? (string) $notes['receipt'] : null,
            ],
        ];
    }

    private function logRefundTransport(int $httpStatus): void
    {
        Log::warning('Razorpay refund request did not complete.', [
            'event' => 'razorpay.refund_transport',
            'http_status' => $httpStatus,
        ]);
    }

    private function api()
    {
        return Http::withBasicAuth($this->key(), $this->secret())
            ->connectTimeout((int) config('checkout.razorpay_connect_timeout', 5))
            ->timeout((int) config('checkout.razorpay_create_timeout', 15));
    }

    private function createOrderApi()
    {
        return Http::withBasicAuth($this->key(), $this->secret())
            ->connectTimeout((int) config('checkout.razorpay_connect_timeout', 5))
            ->timeout((int) config('checkout.razorpay_create_timeout', 15));
    }
}
