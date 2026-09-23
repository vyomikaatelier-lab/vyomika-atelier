<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Support\CheckoutCustomer;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regression coverage for the canonical cart through guest authentication,
 * Buy Now, Details, and the global flash notification.
 *
 * These tests use the real customer login/register endpoints so session
 * regeneration is not skipped.
 */
class CartCheckoutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::preventStrayRequests();
    }

    private function shopCategory(): Category
    {
        return Category::factory()->create(['slug' => 'coffee-tables']);
    }

    private function shopProduct(array $overrides = []): Product
    {
        return Product::factory()->shop()->create(array_merge([
            'category_id' => $this->shopCategory()->id,
            'stock' => 10,
            'price' => 5000,
        ], $overrides));
    }

    private function sizedProduct(): Product
    {
        return Product::factory()->shop()->create([
            'category_id' => Category::factory()->create(['slug' => 'door-handles'])->id,
            'stock' => 10,
            'price' => 20000,
            'size_options' => [
                ['label' => 'Small', 'price' => 14000],
                ['label' => 'Large', 'price' => 18000],
            ],
        ]);
    }

    private function customer(array $overrides = []): User
    {
        return User::factory()->unverified()->create(array_merge([
            'email' => 'buyer@example.com',
            'password' => Hash::make('secret-password'),
            'is_admin' => false,
            'is_active' => true,
        ], $overrides));
    }

    private function loginAsCustomer(User $user, string $password = 'secret-password')
    {
        return $this->post(route('account.login.email'), [
            'email' => $user->email,
            'password' => $password,
        ]);
    }

    public function test_guest_add_to_bag_populates_drawer_and_cart_page(): void
    {
        $product = $this->shopProduct(['name' => 'Aperture Panel Gold PVD Round Wall Mirror']);

        $this->from(route('shop.show', $product->slug))
            ->post(route('cart.add', $product), $this->purchaseInput(['quantity' => 1]))
            ->assertSessionHas('success');

        $this->assertTrue($this->sessionCartHasProduct($product));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Aperture Panel Gold PVD Round Wall Mirror', false);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Aperture Panel Gold PVD Round Wall Mirror', false)
            ->assertSee('am-cart-drawer', false);
    }

    public function test_guest_cart_remains_present_while_redirected_to_login(): void
    {
        $product = $this->shopProduct(['name' => 'Guest Cart Mirror']);

        $this->post(route('cart.add', $product), $this->purchaseInput())
            ->assertRedirect();

        $this->get(route('checkout.index'))
            ->assertRedirect(route('account.login'))
            ->assertSessionHas('info', CheckoutCustomer::MSG_SIGN_IN);

        $this->assertTrue($this->sessionCartHasProduct($product));
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Guest Cart Mirror', false);
    }

    public function test_guest_add_to_bag_then_real_login_preserves_cart(): void
    {
        $product = $this->shopProduct(['name' => 'Login Preserve Mirror']);
        $user = $this->customer();

        $this->post(route('cart.add', $product), $this->purchaseInput(['quantity' => 2]));
        $this->get(route('checkout.index'))->assertRedirect(route('account.login'));

        $this->loginAsCustomer($user)->assertRedirect(route('checkout.index'));

        $this->assertTrue($this->sessionCartHasProduct($product));
        $this->assertSame(2, $this->sessionCartLine($product)['quantity'] ?? null);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Login Preserve Mirror', false)
            ->assertSee('Shipping details', false);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Login Preserve Mirror', false);
    }

    public function test_guest_buy_now_login_returns_to_checkout_with_canonical_cart_item(): void
    {
        $product = $this->shopProduct(['name' => 'Buy Now Gold Mirror']);
        $user = $this->customer(['email' => 'buynow@example.com']);

        $this->post(route('cart.add', $product), $this->purchaseInput([
            'quantity' => 1,
            'buy_now' => 1,
        ]))->assertRedirect(route('account.continue'));

        $this->assertTrue($this->sessionCartHasProduct($product));

        $this->loginAsCustomer($user)->assertRedirect(route('checkout.index'));

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Buy Now Gold Mirror', false);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Buy Now Gold Mirror', false);

        $items = app(CartService::class)->checkoutItems();
        $this->assertCount(1, $items);
        $this->assertSame($product->id, $items->first()['product']->id);
    }

    public function test_guest_registration_preserves_originating_cart(): void
    {
        $product = $this->shopProduct(['name' => 'Register Preserve Lamp']);

        $this->post(route('cart.add', $product), $this->purchaseInput([
            'buy_now' => 1,
        ]))->assertRedirect(route('account.continue'));

        $this->post(route('account.register.send'), [
            'name' => 'New Shopper',
            'email' => 'new-cart@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('checkout.index'));

        $this->assertTrue($this->sessionCartHasProduct($product));
        $this->get(route('checkout.index'))->assertOk()->assertSee('Register Preserve Lamp', false);
        $this->get(route('cart.index'))->assertOk()->assertSee('Register Preserve Lamp', false);
    }

    public function test_signed_in_customer_add_to_bag(): void
    {
        $product = $this->shopProduct(['name' => 'Signed In Bag Chair']);
        $user = $this->customer(['email' => 'signed-bag@example.com']);

        $this->actingAs($user)
            ->from(route('shop.show', $product->slug))
            ->post(route('cart.add', $product), $this->purchaseInput())
            ->assertSessionHas('success');

        $this->assertTrue($this->sessionCartHasProduct($product));
        $this->get(route('cart.index'))->assertOk()->assertSee('Signed In Bag Chair', false);
    }

    public function test_signed_in_customer_buy_now_uses_canonical_cart(): void
    {
        $product = $this->shopProduct(['name' => 'Signed In Buy Now Table']);
        $user = $this->customer(['email' => 'signed-buy@example.com']);

        $this->actingAs($user)
            ->post(route('cart.add', $product), $this->purchaseInput(['buy_now' => 1]))
            ->assertRedirect(route('checkout.index'));

        $this->assertTrue($this->sessionCartHasProduct($product));
        $this->get(route('checkout.index'))->assertOk()->assertSee('Signed In Buy Now Table', false);
        $this->get(route('cart.index'))->assertOk()->assertSee('Signed In Buy Now Table', false);
    }

    public function test_finish_size_quantity_and_variant_are_preserved(): void
    {
        $product = $this->sizedProduct();
        $product->update(['name' => 'Sized Handle']);
        $finish = 'black-mirror';
        $user = $this->customer(['email' => 'variant@example.com']);

        $this->post(route('cart.add', $product), [
            'quantity' => 2,
            'finish_slug' => $finish,
            'size_label' => 'Large',
            'buy_now' => 1,
        ])->assertRedirect(route('account.continue'));

        $line = $this->sessionCartLine($product, 'Large', $finish);
        $this->assertNotNull($line);
        $this->assertSame(2, $line['quantity']);
        $this->assertSame($finish, $line['finish_slug']);
        $this->assertSame('Large', $line['size_label']);

        $this->loginAsCustomer($user)->assertRedirect(route('checkout.index'));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Sized Handle', false)
            ->assertSee('Large', false)
            ->assertSee('Black Mirror', false);

        $preserved = $this->sessionCartLine($product, 'Large', $finish);
        $this->assertSame(2, $preserved['quantity'] ?? null);
        $this->assertSame(18000.0, (float) app(CartService::class)->all()->first()['unit_price']);
    }

    public function test_existing_cart_and_buy_now_merge_without_losing_or_duplicating_lines(): void
    {
        $category = $this->shopCategory();
        $existing = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Existing Cart Chair',
            'stock' => 10,
            'price' => 3000,
        ]);
        $buyNow = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Buy Now Mirror',
            'stock' => 10,
            'price' => 7000,
        ]);
        $user = $this->customer(['email' => 'merge@example.com']);

        $this->post(route('cart.add', $existing), $this->purchaseInput(['quantity' => 1]));
        $this->post(route('cart.add', $buyNow), $this->purchaseInput([
            'quantity' => 1,
            'buy_now' => 1,
        ]));

        $this->assertTrue($this->sessionCartHasProduct($existing));
        $this->assertTrue($this->sessionCartHasProduct($buyNow));
        $this->assertCount(2, session('cart', []));

        $this->loginAsCustomer($user)->assertRedirect(route('checkout.index'));

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Existing Cart Chair', false)
            ->assertSee('Buy Now Mirror', false);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Existing Cart Chair', false)
            ->assertSee('Buy Now Mirror', false);

        $this->post(route('cart.add', $buyNow), $this->purchaseInput([
            'quantity' => 1,
            'buy_now' => 1,
        ]));

        $this->assertSame(2, $this->sessionCartLine($buyNow)['quantity'] ?? null);
        $this->assertSame(1, $this->sessionCartLine($existing)['quantity'] ?? null);
        $this->assertCount(2, session('cart', []));
    }

    public function test_continue_shopping_does_not_clear_cart(): void
    {
        $product = $this->shopProduct(['name' => 'Continue Shopping Mirror']);
        $user = $this->customer(['email' => 'continue@example.com']);

        $this->actingAs($user)
            ->post(route('cart.add', $product), $this->purchaseInput());

        $this->get(StorefrontRoutes::primaryShopUrl())->assertOk();
        $this->assertTrue($this->sessionCartHasProduct($product));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Continue Shopping', false)
            ->assertSee('Continue Shopping Mirror', false);
    }

    public function test_refresh_and_navigation_do_not_clear_cart(): void
    {
        $product = $this->shopProduct(['name' => 'Refresh Mirror']);
        $user = $this->customer(['email' => 'refresh@example.com']);

        $this->actingAs($user)
            ->post(route('cart.add', $product), $this->purchaseInput());

        $this->get(route('cart.index'))->assertOk()->assertSee('Refresh Mirror', false);
        $this->get(route('shop.show', $product->slug))->assertOk();
        $this->get(route('checkout.index'))->assertOk()->assertSee('Refresh Mirror', false);
        $this->get(route('cart.index'))->assertOk()->assertSee('Refresh Mirror', false);

        $this->assertTrue($this->sessionCartHasProduct($product));
    }

    public function test_empty_checkout_redirects_to_cart_with_notice(): void
    {
        $user = $this->customer(['email' => 'empty-checkout@example.com']);

        $this->actingAs($user)
            ->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', 'Your cart is empty.');
    }

    public function test_admin_checkout_remains_blocked(): void
    {
        $product = $this->shopProduct();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('cart.add', $product), $this->purchaseInput())
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', CheckoutCustomer::MSG_ADMIN);
    }

    public function test_submitted_unit_price_cannot_override_server_price(): void
    {
        $product = $this->shopProduct(['price' => 5000, 'name' => 'Trusted Price Mirror']);
        $user = $this->customer(['email' => 'price@example.com']);

        $this->actingAs($user)->post(route('cart.add', $product), $this->purchaseInput([
            'quantity' => 1,
            'unit_price' => 1,
            'price' => 1,
            'total' => 1,
            'buy_now' => 1,
        ]))->assertRedirect(route('checkout.index'));

        $item = app(CartService::class)->all()->first();
        $this->assertSame(5000.0, $item['unit_price']);
        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Trusted Price Mirror', false)
            ->assertDontSee('₹1 each', false);
    }

    public function test_repeat_buy_now_does_not_create_broken_or_duplicated_state(): void
    {
        $product = $this->shopProduct(['name' => 'Repeat Buy Now Mirror']);
        $user = $this->customer(['email' => 'repeat@example.com']);

        $this->actingAs($user)->post(route('cart.add', $product), $this->purchaseInput([
            'quantity' => 1,
            'buy_now' => 1,
        ]))->assertRedirect(route('checkout.index'));

        $this->actingAs($user)->post(route('cart.add', $product), $this->purchaseInput([
            'quantity' => 1,
            'buy_now' => 1,
        ]))->assertRedirect(route('checkout.index'));

        $this->assertCount(1, session('cart', []));
        $this->assertSame(2, $this->sessionCartLine($product)['quantity'] ?? null);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Repeat Buy Now Mirror', false);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Repeat Buy Now Mirror', false);
    }

    public function test_pending_order_retry_remains_idempotent_after_unified_cart(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);
        Http::fake([
            'api.razorpay.com/*' => Http::response([
                'id' => 'order_retry_unified',
                'amount' => 519900,
                'currency' => 'INR',
            ], 200),
        ]);

        $product = $this->shopProduct(['price' => 5000]);
        $user = $this->customer(['email' => 'retry@example.com']);

        $this->actingAs($user)->post(route('cart.add', $product), $this->purchaseInput());

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
        ];

        $this->actingAs($user)->post(route('checkout.store'), $payload);
        $order = Order::query()->first();
        $this->assertNotNull($order);

        $this->actingAs($user)->post(route('checkout.store'), $payload)
            ->assertRedirect(route('checkout.pay', $order));

        $this->assertSame(1, Order::query()->count());
        $this->assertFalse($this->sessionCartHasProduct($product));
    }
}
