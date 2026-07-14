<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

class RazorpayService
{
    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key'))
            && filled(config('services.razorpay.secret'));
    }

    public function createOrder(Order $order): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = Http::withBasicAuth(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        )->post('https://api.razorpay.com/v1/orders', [
            'amount' => (int) round($order->total * 100),
            'currency' => 'INR',
            'receipt' => $order->order_number,
            'notes' => [
                'order_id' => (string) $order->id,
                'customer_email' => $order->customer_email,
            ],
        ]);

        return $response->successful() ? $response->json() : null;
    }

    public function verifySignature(string $razorpayOrderId, string $paymentId, string $signature): bool
    {
        $payload = $razorpayOrderId.'|'.$paymentId;
        $expected = hash_hmac('sha256', $payload, config('services.razorpay.secret'));

        return hash_equals($expected, $signature);
    }
}
