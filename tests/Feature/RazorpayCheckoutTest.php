<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderPaymentService;
use App\Services\RazorpayService;
use App\Support\OrderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RazorpayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);
    }

    private function actingForOrder(Order $order)
    {
        $user = User::factory()->create([
            'email' => $order->customer_email,
            'mobile' => preg_replace('/\D/', '', $order->customer_phone) ?: '9876543210',
        ]);

        return $this->actingAs($user)
            ->withSession([OrderAccess::SESSION_KEY => $order->id]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'status' => 'pending',
            'payment_method' => 'razorpay',
        ], $overrides));
    }

    public function test_create_order_api_requires_store_order_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.create-order'), [
            'amount' => 10000,
            'currency' => 'INR',
            'receipt' => 'TEST-1',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_order_api_for_store_order(): void
    {
        Http::fake([
            'api.razorpay.com/*' => Http::response([
                'id' => 'order_api_test',
                'amount' => 119900,
                'currency' => 'INR',
            ], 200),
        ]);

        $order = $this->makeOrder();

        $response = $this->actingForOrder($order)
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id]);

        $response->assertOk()
            ->assertJson([
                'order_id' => 'order_api_test',
                'amount' => 119900,
                'currency' => 'INR',
                'key' => 'rzp_test_key',
            ]);

        $this->assertSame('order_api_test', $order->fresh()->razorpay_order_id);
    }

    public function test_verify_payment_api_rejects_invalid_signature(): void
    {
        $order = $this->makeOrder(['razorpay_order_id' => 'order_verify_test']);

        $response = $this->actingForOrder($order)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => 'pay_test',
                'razorpay_order_id' => 'order_verify_test',
                'razorpay_signature' => 'invalid',
            ]);

        $response->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_verify_payment_api_marks_order_paid_with_valid_signature(): void
    {
        $order = $this->makeOrder(['razorpay_order_id' => 'order_paid_test']);
        $paymentId = 'pay_valid_test';
        $signature = hash_hmac('sha256', 'order_paid_test|'.$paymentId, 'rzp_test_secret');

        $response = $this->actingForOrder($order)
            ->postJson(route('api.verify-payment'), [
                'store_order_id' => $order->id,
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id' => 'order_paid_test',
                'razorpay_signature' => $signature,
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame($paymentId, $order->fresh()->payment_id);
    }

    public function test_razorpay_service_rejects_amount_below_minimum(): void
    {
        $service = app(RazorpayService::class);
        $result = $service->createPaymentOrder(50, 'TEST-LOW');

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['status']);
    }
}
