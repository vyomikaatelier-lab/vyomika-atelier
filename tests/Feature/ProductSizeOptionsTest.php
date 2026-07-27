<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSizeOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_size_options_and_syncs_lowest_price(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Brass Pull Handle',
            'slug' => 'brass-pull-handle',
            'price' => 1000,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Brass Pull Handle',
            'slug' => 'brass-pull-handle',
            'price' => 1000,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '1',
            'size_options' => [
                ['label' => '8"', 'price' => 800, 'size_inches' => 8, 'sku_suffix' => '8IN'],
                ['label' => '12"', 'price' => 1500, 'size_inches' => 12, 'sku_suffix' => '12IN'],
            ],
        ])->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh();
        $this->assertSame('800.00', (string) $product->price);
        $this->assertCount(2, $product->normalizedSizeOptions());
        $this->assertSame('8"', $product->normalizedSizeOptions()[0]['label']);
        $this->assertSame(1500.0, $product->normalizedSizeOptions()[1]['price']);
    }

    public function test_pdp_shows_size_selector_and_from_price(): void
    {
        $category = Category::factory()->create(['slug' => 'door-handles']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 800,
            'size_options' => [
                ['label' => '8"', 'price' => 800, 'size_inches' => 8],
                ['label' => '12"', 'price' => 1500, 'size_inches' => 12],
            ],
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('data-pdp-size', false)
            ->assertSee('data-size-option', false)
            ->assertSee('data-pdp-price-display', false)
            ->assertSee('From ₹800', false)
            ->assertSee('data-size-price="1500"', false);
    }

    public function test_add_to_cart_preserves_selected_size_and_price(): void
    {
        $category = Category::factory()->create(['slug' => 'door-handles']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 800,
            'size_options' => [
                ['label' => '8"', 'price' => 800],
                ['label' => '12"', 'price' => 1500],
            ],
        ]);

        $this->post(route('cart.add', $product), [
            'quantity' => 2,
            'size_label' => '12"',
        ])->assertRedirect();

        $cart = session('cart');
        $this->assertSame('12"', $cart[$product->id]['size_label']);
        $this->assertSame(1500.0, (float) $cart[$product->id]['unit_price']);

        $items = app(CartService::class)->all();
        $this->assertSame(3000.0, $items->first()['line_total']);
    }

    public function test_checkout_stores_size_on_order_item(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'api.razorpay.com/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'order_size_test',
                'amount' => 150000,
                'currency' => 'INR',
            ], 200),
        ]);

        $category = Category::factory()->create(['slug' => 'door-handles']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 800,
            'stock' => 5,
            'size_options' => [
                ['label' => '8"', 'price' => 800],
                ['label' => '12"', 'price' => 1500],
            ],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->withSession([
            'cart' => [
                $product->id => [
                    'quantity' => 1,
                    'finish_slug' => null,
                    'finish_name' => null,
                    'size_label' => '12"',
                    'unit_price' => 1500,
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
        ])->assertRedirect();

        $item = OrderItem::query()->first();
        $this->assertSame('12"', $item->size_label);
        $this->assertSame('1500.00', (string) $item->price);
        $this->assertSame('1500.00', (string) $item->total);
    }

    public function test_product_card_shows_from_price_for_multiple_sizes(): void
    {
        $category = Category::factory()->create(['slug' => 'door-handles', 'name' => 'Door Handles']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Slim Pull',
            'slug' => 'slim-pull',
            'price' => 800,
            'is_gallery_visible' => true,
            'size_options' => [
                ['label' => '8"', 'price' => 800],
                ['label' => '12"', 'price' => 1500],
            ],
        ]);

        $this->assertSame('From ₹800', $product->formattedListingPrice());

        $html = view('partials.am-product-card', ['product' => $product])->render();
        $this->assertStringContainsString('From ₹800', $html);
    }
}
