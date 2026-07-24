<?php

namespace Tests\Feature;

use App\Mail\AdminNewOrderMail;
use App\Mail\AdminPaymentReceivedMail;
use App\Mail\OrderReceivedMail;
use App\Mail\PaymentSuccessfulMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderNotificationService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'services.admin_email' => 'admin@example.com',
            'mail.default' => 'array',
            'mail.from.address' => 'shop@example.com',
            'mail.from.name' => 'Test Shop',
            'queue.default' => 'sync',
        ]);
    }

    private function verifiedCustomer(): User
    {
        return User::factory()->create([
            'is_admin' => false,
            'phone_verified_at' => now(),
        ]);
    }

    private function shopCartSession(Product $product): array
    {
        return [
            'cart' => [
                $product->id => [
                    'quantity' => 1,
                    'finish_slug' => null,
                    'finish_name' => null,
                ],
            ],
        ];
    }

    private function checkoutPayload(): array
    {
        return [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9876543210',
            'house_building' => '123 Test Building',
            'street' => 'Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'country' => 'India',
            'payment_method' => 'razorpay',
            'billing_same_as_shipping' => '1',
        ];
    }

    public function test_order_created_sends_customer_and_admin_emails(): void
    {
        Mail::fake();

        Http::fake([
            'api.razorpay.com/*' => Http::response(['id' => 'order_test_abc', 'amount' => 100, 'currency' => 'INR'], 200),
        ]);

        $user = $this->verifiedCustomer();
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 10,
        ]);

        $response = $this->actingAs($user)
            ->withSession($this->shopCartSession($product))
            ->post(route('checkout.store'), $this->checkoutPayload());

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('checkout.pay', $order));

        Mail::assertQueued(OrderReceivedMail::class, fn ($mail) => $mail->hasTo('jane@example.com'));
        Mail::assertQueued(AdminNewOrderMail::class, fn ($mail) => $mail->hasTo('admin@example.com'));
        $this->assertNotNull($order->fresh()->order_received_email_sent_at);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_verified_payment_sends_payment_emails_once(): void
    {
        Mail::fake();

        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 5,
        ]);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9876543210',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => $product->price,
            'shipping_cost' => 199,
            'total' => $product->price + 199,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'razorpay_order_id' => 'order_pay_1',
            'expires_at' => now()->addDay(),
            'order_received_email_sent_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => 1,
            'total' => $product->price,
        ]);

        $paymentId = 'pay_test_1';
        $signature = hash_hmac('sha256', 'order_pay_1|'.$paymentId, 'rzp_test_secret');

        $payments = app(OrderPaymentService::class);
        $payments->verifyAndComplete($order, $paymentId, 'order_pay_1', $signature);
        $payments->verifyAndComplete($order->fresh(), $paymentId, 'order_pay_1', $signature);

        Mail::assertQueued(PaymentSuccessfulMail::class, 1);
        Mail::assertQueued(AdminPaymentReceivedMail::class, 1);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
    }

    public function test_mail_failure_does_not_block_order_creation(): void
    {
        Http::fake([
            'api.razorpay.com/*' => Http::response(['id' => 'order_test_fail', 'amount' => 100, 'currency' => 'INR'], 200),
        ]);

        $this->mock(OrderNotificationService::class, function ($mock): void {
            $mock->shouldReceive('sendOrderReceived')->andReturn(false);
        });

        $user = $this->verifiedCustomer();
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 3]);

        $response = $this->actingAs($user)
            ->withSession($this->shopCartSession($product))
            ->post(route('checkout.store'), $this->checkoutPayload());

        $this->assertSame(1, Order::query()->count());
        $response->assertRedirect();
    }

    public function test_unconfigured_mail_skips_send_without_breaking_checkout(): void
    {
        config(['mail.from.address' => null]);
        Mail::fake();

        Http::fake([
            'api.razorpay.com/*' => Http::response(['id' => 'order_test_nomail', 'amount' => 100, 'currency' => 'INR'], 200),
        ]);

        $user = $this->verifiedCustomer();
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 3]);

        $this->actingAs($user)
            ->withSession($this->shopCartSession($product))
            ->post(route('checkout.store'), $this->checkoutPayload());

        Mail::assertNothingSent();
        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->order_received_email_sent_at);
        $this->assertSame(1, Order::query()->count());
    }
}
