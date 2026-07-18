<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\CartGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_shop_checkout_product_can_be_added_to_cart(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);

        $response = $this->post(route('cart.add', $product), ['quantity' => 1]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');
        $this->assertEquals(1, session('cart')[$product->id] ?? null);
    }

    public function test_shop_buy_now_redirects_to_cart(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);

        $response = $this->post(route('cart.add', $product), [
            'quantity' => 1,
            'buy_now' => 1,
        ]);

        $response->assertRedirect(route('cart.index'));
        $this->assertEquals(1, session('cart')[$product->id] ?? null);
    }

    public function test_studio_product_direct_post_is_rejected_with_enquiry_message(): void
    {
        $category = Category::factory()->create(['slug' => 'partitions']);
        $studioProduct = Product::factory()->studio()->create(['category_id' => $category->id]);

        $response = $this->post(route('cart.add', $studioProduct), ['quantity' => 1]);

        $response->assertRedirect();
        $response->assertSessionHas('error', CartGuard::MSG_STUDIO);
        $this->assertEmpty(session('cart', []));
    }

    public function test_railings_product_direct_post_is_rejected_with_quotation_message(): void
    {
        $category = Category::factory()->create(['slug' => 'railings']);
        $railingProduct = Product::factory()->railings()->create(['category_id' => $category->id]);

        $response = $this->post(route('cart.add', $railingProduct), ['quantity' => 1]);

        $response->assertRedirect();
        $response->assertSessionHas('error', CartGuard::MSG_RAILINGS);
        $this->assertEmpty(session('cart', []));
    }

    public function test_inactive_shop_product_cannot_be_added_to_cart(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->inactive()->create(['category_id' => $category->id]);

        $response = $this->post(route('cart.add', $product), ['quantity' => 1]);

        $response->assertRedirect();
        $response->assertSessionHas('error', CartGuard::MSG_INACTIVE);
        $this->assertEmpty(session('cart', []));
    }

    public function test_studio_product_cannot_be_added_via_cart_update_either(): void
    {
        $category = Category::factory()->create(['slug' => 'partitions']);
        $studioProduct = Product::factory()->studio()->create(['category_id' => $category->id]);

        // Simulate a legacy/forged session that already holds an ineligible item.
        $this->withSession(['cart' => [$studioProduct->id => 1]]);

        $response = $this->patch(route('cart.update', $studioProduct), ['quantity' => 2]);

        $response->assertRedirect();
        $response->assertSessionHas('error', CartGuard::MSG_STUDIO);
        $this->assertEmpty(session('cart', []));
    }
}
