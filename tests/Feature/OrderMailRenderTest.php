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
        $this->assertStringContainsString('Shipping:', $html);
        $this->assertStringContainsString('₹199', $html);
        $this->assertStringContainsString('Total:', $html);
        $this->assertStringContainsString('₹12,199', $html);
        $this->assertMailAlternatives($order, '₹199', '₹12,199');

        $order->forceFill(['shipping_cost' => 0, 'total' => 12000])->save();
        $included = $order->fresh('items');
        $includedHtml = (new OrderReceivedMail($included))->render();
        $this->assertStringContainsString('Shipping:', $includedHtml);
        $this->assertStringContainsString('Included', $includedHtml);
        $this->assertStringContainsString('Total:', $includedHtml);
        $this->assertStringContainsString('₹12,000', $includedHtml);
        $this->assertStringNotContainsString('₹199', $includedHtml);
        $this->assertMailAlternatives($included, 'Included', '₹12,000');
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
        $this->assertStringContainsString('Shipping:', $html);
        $this->assertStringContainsString('₹199', $html);
        $this->assertStringContainsString('Total:', $html);
        $this->assertStringContainsString('₹12,199', $html);
        $this->assertMailAlternatives($order, '₹199', '₹12,199', 'emails.orders.payment-successful');
    }

    private function assertMailAlternatives(Order $order, string $shipping, string $total, string $view = 'emails.orders.received'): void
    {
        $text = (string) app(\Illuminate\Mail\Markdown::class)->renderText($view, [
            'order' => $order,
            'supportEmail' => 'studio@example.com',
        ]);

        $this->assertStringContainsString('**Shipping:** '.$shipping, $text);
        $this->assertStringContainsString('**Total:** '.$total, $text);
        $this->assertStringNotContainsString('\\', $text);
        $this->assertDoesNotMatchRegularExpression('/Shipping:\*\* '.$this->quote($shipping).'[ \t]+\n/', $text);
    }

    private function quote(string $value): string
    {
        return preg_quote($value, '/');
    }

    public function test_admin_payment_received_mail_renders(): void
    {
        $order = $this->orderWithItems();

        $html = (new AdminPaymentReceivedMail($order))->render();

        $this->assertStringNotContainsString('pay_test_render', $html);
        $this->assertStringContainsString('Aria Mirror Frame', $html);
        $this->assertStringContainsString('Payment received', $html);
    }

    public function test_inconsistent_razorpay_mail_does_not_claim_payment_was_received(): void
    {
        foreach (['paid', 'processing', 'shipped', 'delivered'] as $status) {
            $order = $this->orderWithItems();
            $order->forceFill([
                'status' => $status,
                'payment_id' => null,
                'razorpay_order_id' => 'order_hidden_'.$status,
            ])->save();
            $order = $order->fresh('items');

            $adminNew = new AdminNewOrderMail($order);
            $adminPaid = new AdminPaymentReceivedMail($order);
            $customerPaid = new PaymentSuccessfulMail($order);

            foreach ([
                $adminNew->render(),
                $adminPaid->render(),
                $customerPaid->render(),
                $adminNew->envelope()->subject,
                $adminPaid->envelope()->subject,
                $customerPaid->envelope()->subject,
            ] as $rendered) {
                $this->assertStringNotContainsString('Payment received', $rendered);
                $this->assertStringNotContainsString('Paid', $rendered);
                $this->assertStringNotContainsString('order_hidden_'.$status, $rendered);
            }

            $this->assertStringContainsString('Payment not confirmed', $adminNew->render());
            $this->assertSame('Payment not confirmed', $order->paymentAwareStatusLabel());
        }
    }
}
