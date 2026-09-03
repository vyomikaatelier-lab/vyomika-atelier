<?php

namespace Tests\Feature;

use App\Services\RazorpayService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentLockTimingTest extends TestCase
{
    public function test_razorpay_create_http_timeout_is_explicitly_bounded(): void
    {
        $this->assertSame(15, (int) config('checkout.razorpay_create_timeout'));
        $this->assertSame(5, (int) config('checkout.razorpay_connect_timeout'));
        $this->assertLessThanOrEqual(15, (int) config('checkout.razorpay_create_timeout'));
        $this->assertLessThanOrEqual(5, (int) config('checkout.razorpay_connect_timeout'));
    }

    public function test_lock_leases_exceed_razorpay_http_timeout_with_thirty_second_margin(): void
    {
        $httpTimeout = (int) config('checkout.razorpay_create_timeout');
        $customerLease = (int) config('checkout.customer_lock_seconds');
        $razorpayLease = (int) config('checkout.razorpay_lock_seconds');

        $this->assertGreaterThanOrEqual(60, $customerLease);
        $this->assertGreaterThanOrEqual(60, $razorpayLease);
        $this->assertGreaterThanOrEqual(30, $customerLease - $httpTimeout);
        $this->assertGreaterThanOrEqual(30, $razorpayLease - $httpTimeout);
    }

    public function test_lock_wait_seconds_remain_separate_from_lock_leases(): void
    {
        $this->assertLessThan(
            (int) config('checkout.customer_lock_seconds'),
            (int) config('checkout.customer_lock_wait') + 1
        );
        $this->assertLessThan(
            (int) config('checkout.razorpay_lock_seconds'),
            (int) config('checkout.razorpay_lock_wait') + 1
        );
        $this->assertSame(10, (int) config('checkout.customer_lock_wait'));
        $this->assertSame(10, (int) config('checkout.razorpay_lock_wait'));
    }

    public function test_razorpay_create_order_honours_configured_http_timeouts(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'checkout.razorpay_create_timeout' => 12,
            'checkout.razorpay_connect_timeout' => 4,
        ]);

        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_timeout_cfg',
                'amount' => 10000,
                'currency' => 'INR',
            ], 200),
        ]);

        $result = app(RazorpayService::class)->createPaymentOrder(10000, 'VA-TIMEOUT', []);

        $this->assertTrue($result['success']);
        Http::assertSentCount(1);
    }
}
