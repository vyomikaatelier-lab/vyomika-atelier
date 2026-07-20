<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\FinishSwatches;
use App\Support\OrderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinishCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_to_cart_persists_finish_selection(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->post(route('cart.add', $product), [
            'quantity' => 1,
            'finish_slug' => 'black-mirror',
        ]);

        $response->assertRedirect();
        $cart = session('cart');
        $this->assertSame('black-mirror', $cart[$product->id]['finish_slug']);
        $this->assertSame('Black Mirror', $cart[$product->id]['finish_name']);
    }

    public function test_checkout_stores_finish_on_order_item(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'api.razorpay.com/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'order_finish_test',
                'amount' => 119900,
                'currency' => 'INR',
            ], 200),
        ]);

        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1000,
            'stock' => 5,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->withSession([
            'cart' => [
                $product->id => [
                    'quantity' => 1,
                    'finish_slug' => 'rose-gold-brush',
                    'finish_name' => 'Rose Gold Brush',
                ],
            ],
        ])->post(route('checkout.store'), [
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
        ]);

        $response->assertRedirect();
        $item = OrderItem::query()->first();
        $this->assertSame('rose-gold-brush', $item->finish_slug);
        $this->assertSame('Rose Gold Brush', $item->finish_name);
    }

    public function test_invalid_finish_slug_falls_back_to_default(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->post(route('cart.add', $product), [
            'quantity' => 1,
            'finish_slug' => 'not-a-real-finish',
        ]);

        $cart = session('cart');
        $this->assertSame(FinishSwatches::defaultSlug(), $cart[$product->id]['finish_slug']);
    }
}
