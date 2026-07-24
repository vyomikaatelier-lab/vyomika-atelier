<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\CartGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartQuantityTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_add_to_empty_cart_sets_quantity_one(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);

        $this->post(route('cart.add', $product), ['quantity' => 1])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, session('cart')[$product->id]['quantity']);
    }

    public function test_second_add_increments_quantity_to_two(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);

        $this->post(route('cart.add', $product), ['quantity' => 1]);
        $this->post(route('cart.add', $product), ['quantity' => 1]);

        $this->assertSame(2, session('cart')[$product->id]['quantity']);
    }

    public function test_buy_now_does_not_increment_unrelated_cart_line(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $productA = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);
        $productB = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);

        $this->post(route('cart.add', $productA), ['quantity' => 1]);

        $this->post(route('cart.add', $productB), ['quantity' => 1, 'buy_now' => 1])
            ->assertRedirect(route('checkout.index'));

        $this->assertSame(1, session('cart')[$productA->id]['quantity']);
        $this->assertSame(1, session('cart')[$productB->id]['quantity']);
    }

    public function test_studio_and_railings_products_remain_blocked(): void
    {
        $studioCategory = Category::factory()->create(['slug' => 'partitions']);
        $railingsCategory = Category::factory()->create(['slug' => 'railings']);
        $studio = Product::factory()->studio()->create(['category_id' => $studioCategory->id]);
        $railings = Product::factory()->railings()->create(['category_id' => $railingsCategory->id]);

        $this->post(route('cart.add', $studio), ['quantity' => 1])
            ->assertSessionHas('error', CartGuard::MSG_STUDIO);
        $this->post(route('cart.add', $railings), ['quantity' => 1])
            ->assertSessionHas('error', CartGuard::MSG_RAILINGS);

        $this->assertEmpty(session('cart', []));
    }

    public function test_inactive_product_cannot_be_added(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->inactive()->create(['category_id' => $category->id]);

        $this->post(route('cart.add', $product), ['quantity' => 1])
            ->assertSessionHas('error', CartGuard::MSG_INACTIVE);

        $this->assertEmpty(session('cart', []));
    }

    public function test_add_rejects_non_numeric_and_clamps_excessive_quantity(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 10]);

        $this->post(route('cart.add', $product), ['quantity' => 'abc']);
        $this->assertSame(1, session('cart')[$product->id]['quantity']);

        session()->forget('cart');

        $this->post(route('cart.add', $product), ['quantity' => -5]);
        $this->assertSame(1, session('cart')[$product->id]['quantity']);

        session()->forget('cart');

        $this->post(route('cart.add', $product), ['quantity' => 500]);
        $this->assertSame(10, session('cart')[$product->id]['quantity']);
    }

    public function test_update_to_zero_removes_line(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);

        $this->post(route('cart.add', $product), ['quantity' => 2]);
        $this->patch(route('cart.update', $product), ['quantity' => 0])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertArrayNotHasKey($product->id, session('cart', []));
    }

    public function test_checkout_totals_use_database_prices_not_browser_input(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'api.razorpay.com/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'order_price_test',
                'amount' => 100,
                'currency' => 'INR',
            ], 200),
        ]);

        $user = User::factory()->create();
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'price' => 2500,
            'stock' => 5,
        ]);

        $payload = [
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
            'subtotal' => 1,
            'total' => 1,
            'price' => 1,
        ];

        $this->actingAs($user)->withSession([
            'cart' => [$product->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]],
        ])->post(route('checkout.store'), $payload);

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(2500.0, (float) $order->subtotal);
        $this->assertSame(2699.0, (float) $order->total);
    }
}
