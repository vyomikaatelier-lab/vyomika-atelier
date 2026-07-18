<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_add_rate_limit_triggers(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 100]);

        for ($i = 0; $i < 30; $i++) {
            $this->post(route('cart.add', $product))->assertRedirect();
        }

        $response = $this->post(route('cart.add', $product));
        $response->assertStatus(429);
    }

    public function test_contact_form_rate_limit_triggers(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '9999999999',
            'subject' => 'Test inquiry',
            'message' => 'Hello there',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.store'), $payload);
        }

        $response = $this->post(route('contact.store'), $payload);
        $response->assertStatus(429);
    }
}
