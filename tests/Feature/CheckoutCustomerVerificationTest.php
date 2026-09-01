<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\CheckoutCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CheckoutCustomerVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function shopCart(): array
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);

        return [$product, ['cart' => [$product->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]]]];
    }

    public function test_guest_can_add_shop_items_to_cart(): void
    {
        [$product] = $this->shopCart();

        $response = $this->post(route('cart.add', $product), $this->purchaseInput());

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNotEmpty(session('cart', []));
    }

    public function test_guest_cannot_access_checkout(): void
    {
        [, $session] = $this->shopCart();

        $response = $this->withSession($session)->get(route('checkout.index'));

        $response->assertRedirect(route('account.login'));
        $response->assertSessionHas('info', CheckoutCustomer::MSG_SIGN_IN);
    }

    public function test_unverified_customer_can_access_checkout(): void
    {
        [, $session] = $this->shopCart();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->withSession($session)->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee('Shipping details');
    }

    public function test_unverified_customer_can_access_account(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('account'))
            ->assertOk();
    }

    public function test_disabled_customer_cannot_access_checkout(): void
    {
        [, $session] = $this->shopCart();
        $user = User::factory()->disabled()->create();

        $response = $this->actingAs($user)->withSession($session)->get(route('checkout.index'));

        $response->assertRedirect(route('account.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_verified_customer_can_access_checkout(): void
    {
        [, $session] = $this->shopCart();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->withSession($session)->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee('Shipping details');
    }

    public function test_cart_survives_login_and_redirects_to_checkout(): void
    {
        [, $session] = $this->shopCart();
        $user = User::factory()->unverified()->create([
            'email' => 'buyer@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->withSession($session)->get(route('checkout.index'))
            ->assertRedirect(route('account.login'));

        $this->withSession($session)
            ->post(route('account.login.email'), [
                'email' => 'buyer@example.com',
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('checkout.index'));

        $this->assertNotEmpty(session('cart', []));
    }

    public function test_buy_now_returns_to_checkout_after_login(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $buyNow = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Buy Now Table',
            'stock' => 5,
        ]);
        $other = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Unrelated Cart Chair',
            'stock' => 5,
        ]);
        $user = User::factory()->unverified()->create([
            'email' => 'buynow@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->withSession([
            'cart' => [$other->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]],
        ])->post(route('cart.add', $buyNow), $this->purchaseInput([
            'quantity' => 2,
            'buy_now' => 1,
        ]))->assertRedirect(route('account.continue'));

        $this->assertSame($buyNow->id, session('buy_now')['product_id']);
        $this->assertSame(2, session('buy_now')['quantity']);
        $this->assertTrue($this->sessionCartHasProduct($other));

        $this->post(route('account.login.email'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('checkout.index'));

        $checkout = $this->get(route('checkout.index'));
        $checkout->assertOk()->assertSee('Buy Now Table');

        $items = app(\App\Services\CartService::class)->checkoutItems();
        $this->assertCount(1, $items);
        $this->assertSame($buyNow->id, $items->first()['product']->id);
        $this->assertSame(2, $items->first()['quantity']);
        $this->assertTrue($this->sessionCartHasProduct($other));
    }

    public function test_buy_now_returns_to_checkout_after_registration(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Register Buy Now Lamp',
            'stock' => 4,
        ]);

        $this->post(route('cart.add', $product), $this->purchaseInput([
            'buy_now' => 1,
        ]))->assertRedirect(route('account.continue'));

        $this->post(route('account.register.send'), [
            'name' => 'New Shopper',
            'email' => 'newshopper@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('checkout.index'));

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Register Buy Now Lamp');
    }

    public function test_authenticated_buy_now_redirects_to_checkout(): void
    {
        [$product] = $this->shopCart();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('cart.add', $product), $this->purchaseInput([
            'buy_now' => 1,
        ]))->assertRedirect(route('checkout.index'));
    }

    public function test_checkout_requires_a_valid_delivery_mobile_number(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);

        [, $session] = $this->shopCart();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->withSession($session)
            ->post(route('checkout.store'), [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'customer_email' => 'jane@example.com',
                'customer_phone' => '123',
                'house_building' => '123 Test Building',
                'street' => 'Test Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
                'country' => 'India',
                'payment_method' => 'razorpay',
            ])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors('phone');
    }

    public function test_admin_cannot_checkout_and_stays_on_storefront(): void
    {
        [$product, $session] = $this->shopCart();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withSession($session)->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', CheckoutCustomer::MSG_ADMIN);

        $this->actingAs($admin)->post(route('cart.add', $product), $this->purchaseInput([
            'buy_now' => 1,
        ]))->assertRedirect(route('checkout.index'));

        $this->actingAs($admin)->followingRedirects()
            ->post(route('cart.add', $product), $this->purchaseInput([
                'buy_now' => 1,
            ]))
            ->assertOk()
            ->assertSee(CheckoutCustomer::MSG_ADMIN);
    }
}
