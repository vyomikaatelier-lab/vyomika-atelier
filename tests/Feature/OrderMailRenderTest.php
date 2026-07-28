<?php

namespace Tests\Feature;

use App\Mail\AdminNewOrderMail;
use App\Mail\AdminPaymentReceivedMail;
use App\Mail\OrderReceivedMail;
use App\Mail\PaymentSuccessfulMail;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The notification tests all use Mail::fake(), so a broken Blade template in
 * emails/orders/* never fails them. These render the mailables for real.
 */
class OrderMailRenderTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithItems(): Order
    {
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9876543210',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'subtotal' => 12000,
            'shipping_cost' => 199,
            'total' => 12199,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'payment_id' => 'pay_test_render',
        ]);

        // Finish and size are the optional columns the templates branch on.
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Aria Mirror Frame',
            'finish_name' => 'Brushed Brass',
            'size_label' => '36 x 24 in',
            'price' => 9000,
            'quantity' => 1,
            'total' => 9000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Slim Partition',
            'finish_name' => null,
            'size_label' => null,
            'price' => 3000,
            'quantity' => 1,
            'total' => 3000,
        ]);

        return $order->fresh('items');
    }

    public function test_order_received_mail_renders(): void
    {
        $order = $this->orderWithItems();

        $html = (new OrderReceivedMail($order))->render();

        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('Aria Mirror Frame', $html);
        $this->assertStringContainsString('Brushed Brass', $html);
        $this->assertStringContainsString('36 x 24 in', $html);
        $this->assertStringContainsString('Slim Partition', $html);
    }

    public function test_admin_new_order_mail_renders(): void
    {
        $order = $this->orderWithItems();

        $html = (new AdminNewOrderMail($order))->render();

        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('Aria Mirror Frame', $html);
    }

    public function test_payment_successful_mail_renders(): void
    {
        $order = $this->orderWithItems();

        $html = (new PaymentSuccessfulMail($order))->render();

        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('Slim Partition', $html);
    }

    public function test_admin_payment_received_mail_renders(): void
    {
        $order = $this->orderWithItems();

        $html = (new AdminPaymentReceivedMail($order))->render();

        $this->assertStringContainsString('pay_test_render', $html);
        $this->assertStringContainsString('Aria Mirror Frame', $html);
    }
}
