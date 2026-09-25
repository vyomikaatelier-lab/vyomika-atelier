<?php

namespace App\Services;

use App\Models\OrderRefund;

final class RazorpayRefundRequest
{
    public static function body(OrderRefund $refund, string $orderNumber): string
    {
        $payload = [
            'amount' => (int) $refund->amount_paise,
            'speed' => 'normal',
            'receipt' => (string) $refund->receipt,
            'notes' => [
                'order_number' => $orderNumber,
                'refund_id' => (string) $refund->getKey(),
                'receipt' => (string) $refund->receipt,
            ],
        ];

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function hash(string $body): string
    {
        return hash('sha256', $body);
    }

    public static function validIdempotencyKey(string $key): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{10,64}$/', $key);
    }
}
