<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_pending_order_is_cancelled_and_releases_reservation(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 2,
        ]);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => $product->price,
            'shipping_cost' => 0,
            'total' => $product->price,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'expires_at' => now()->subMinute(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => 2,
            'total' => $product->price * 2,
        ]);

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(2, $product->fresh()->stock);

        $response = $this->post(route('cart.add', $product), ['quantity' => 2]);
        $response->assertRedirect();
        $this->assertSame(2, session('cart')[$product->id]['quantity'] ?? null);
    }
}
