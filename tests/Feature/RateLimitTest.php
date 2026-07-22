<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'form_protection.turnstile.testing_bypass_token' => 'test-turnstile-pass',
        ]);
    }

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
        $protection = app(\App\Services\FormProtectionService::class);
        $turnstile = app(\App\Services\TurnstileService::class);

        $payload = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '9999999999',
            'subject' => 'Test inquiry',
            'message' => 'Hello there with enough words to look genuine for scoring purposes today.',
            'enquiry_intent' => 'general_enquiry',
            'form_loaded_at' => Crypt::encryptString(json_encode([
                'form' => 'contact',
                'loaded_at' => now()->subSeconds(10)->timestamp,
            ])),
            'turnstile_fallback_token' => $turnstile->fallbackToken('contact'),
            'turnstile_unavailable' => '0',
            'cf-turnstile-response' => 'test-turnstile-pass',
            'human_confirmation' => '1',
        ];

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('contact.store'), $payload);
        }

        $response = $this->post(route('contact.store'), $payload);
        $response->assertStatus(429);
    }
}
