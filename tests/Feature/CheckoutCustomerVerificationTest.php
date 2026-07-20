<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\CheckoutCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->post(route('cart.add', $product), ['quantity' => 1]);

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

    public function test_unverified_customer_cannot_access_checkout(): void
    {
        [, $session] = $this->shopCart();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->withSession($session)->get(route('checkout.index'));

        $response->assertRedirect(route('account.verify'));
        $response->assertSessionHas('info', CheckoutCustomer::MSG_VERIFY);
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
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'password' => bcrypt('secret-password'),
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

    public function test_buy_now_redirects_to_checkout(): void
    {
        [$product] = $this->shopCart();

        $response = $this->post(route('cart.add', $product), [
            'quantity' => 1,
            'buy_now' => 1,
        ]);

        $response->assertRedirect(route('checkout.index'));
    }
}
