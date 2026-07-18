<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Support\OrderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAccessTest extends TestCase
{
    use RefreshDatabase;

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
            'razorpay_order_id' => 'order_test123',
        ], $overrides));
    }

    public function test_guest_cannot_view_checkout_success_for_foreign_order(): void
    {
        $order = $this->makeOrder(['status' => 'paid']);

        $response = $this->get(route('checkout.success', $order));

        $response->assertRedirect(route('shop.index'));
        $response->assertSessionHas('error');
    }

    public function test_guest_can_view_checkout_success_for_session_order(): void
    {
        $order = $this->makeOrder(['status' => 'paid']);

        $response = $this->withSession([OrderAccess::SESSION_KEY => $order->id])
            ->get(route('checkout.success', $order));

        $response->assertOk();
        $response->assertSee($order->order_number);
    }

    public function test_guest_cannot_open_payment_page_for_foreign_order(): void
    {
        $order = $this->makeOrder();

        $response = $this->get(route('checkout.pay', $order));

        $response->assertRedirect(route('shop.index'));
        $response->assertSessionHas('error');
    }

    public function test_payment_verify_rejects_mismatched_razorpay_order_id(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        Product::factory()->shop()->create(['category_id' => $category->id]);
        $order = $this->makeOrder();

        $response = $this->withSession([OrderAccess::SESSION_KEY => $order->id])
            ->post(route('checkout.pay.verify', $order), [
                'razorpay_payment_id' => 'pay_test',
                'razorpay_order_id' => 'order_wrong',
                'razorpay_signature' => 'invalid',
            ]);

        $response->assertRedirect(route('checkout.pay', $order));
        $response->assertSessionHas('error');
        $this->assertSame('pending', $order->fresh()->status);
    }
}
