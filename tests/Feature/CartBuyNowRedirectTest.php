<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Support\CartGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CartBuyNowRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function shopCategory(): Category
    {
        return Category::factory()->create(['slug' => 'coffee-tables']);
    }

    private function shopProduct(Category $category, array $overrides = []): Product
    {
        return Product::factory()->shop()->create(array_merge([
            'category_id' => $category->id,
            'stock' => 5,
        ], $overrides));
    }

    public function test_add_to_cart_returns_to_product_page_and_opens_drawer(): void
    {
        $product = $this->shopProduct($this->shopCategory());
        $origin = route('shop.show', $product->slug);

        $location = urldecode((string) $this->from($origin)
            ->post(route('cart.add', $product), $this->purchaseInput())
            ->assertSessionHas('success')
            ->headers->get('Location'));

        $this->assertStringContainsString($product->slug, $location);
        $this->assertStringContainsString('cart=open', $location);
        $this->assertStringContainsString('#am-cart-drawer', $location);
        $this->assertStringNotContainsString('/cart', parse_url($location, PHP_URL_PATH) ?? '');
    }

    public function test_add_to_cart_modifies_cart_but_not_buy_now(): void
    {
        $product = $this->shopProduct($this->shopCategory());

        $this->post(route('cart.add', $product), $this->purchaseInput(['quantity' => 2]))
            ->assertRedirect();

        $this->assertSame(2, $this->sessionCartLine($product)['quantity'] ?? null);
        $this->assertNull(session('buy_now'));
    }

    public function test_guest_buy_now_redirects_to_account_continue(): void
    {
        $product = $this->shopProduct($this->shopCategory());

        $this->post(route('cart.add', $product), $this->purchaseInput(['buy_now' => 1]))
            ->assertRedirect(route('account.continue'))
            ->assertSessionHas('info');

        $this->assertSame($product->id, session('buy_now')['product_id']);
        $this->assertArrayNotHasKey($product->id, session('cart', []));
    }

    public function test_login_after_buy_now_redirects_to_checkout(): void
    {
        $product = $this->shopProduct($this->shopCategory(), ['name' => 'Login Buy Now Table']);
        $user = User::factory()->unverified()->create([
            'email' => 'buynow-login@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post(route('cart.add', $product), $this->purchaseInput(['buy_now' => 1]))
            ->assertRedirect(route('account.continue'));

        $this->post(route('account.login.email'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('checkout.index'));

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Login Buy Now Table');
    }

    public function test_registration_after_buy_now_redirects_to_checkout(): void
    {
        $product = $this->shopProduct($this->shopCategory(), ['name' => 'Register Buy Now Lamp']);

        $this->post(route('cart.add', $product), $this->purchaseInput(['buy_now' => 1]))
            ->assertRedirect(route('account.continue'));

        $this->post(route('account.register.send'), [
            'name' => 'New Shopper',
            'email' => 'new-buynow@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('checkout.index'));

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Register Buy Now Lamp');
    }

    public function test_authenticated_buy_now_redirects_directly_to_checkout(): void
    {
        $product = $this->shopProduct($this->shopCategory());
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('cart.add', $product), $this->purchaseInput([
            'buy_now' => 1,
        ]))->assertRedirect(route('checkout.index'));
    }

    public function test_authenticated_customer_with_valid_buy_now_on_continue_redirects_to_checkout(): void
    {
        $product = $this->shopProduct($this->shopCategory(), ['name' => 'Continue Redirect Table']);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->withSession([
            'buy_now' => [
                'product_id' => $product->id,
                'quantity' => 1,
                'finish_slug' => null,
                'finish_name' => null,
                'size_label' => null,
                'created_at' => now()->timestamp,
            ],
        ])->get(route('account.continue'))
            ->assertRedirect(route('checkout.index'));
    }

    public function test_authenticated_customer_with_valid_buy_now_on_login_redirects_to_checkout(): void
    {
        $product = $this->shopProduct($this->shopCategory());
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->withSession([
            'buy_now' => [
                'product_id' => $product->id,
                'quantity' => 1,
                'finish_slug' => null,
                'finish_name' => null,
                'size_label' => null,
                'created_at' => now()->timestamp,
            ],
        ])->get(route('account.login'))
            ->assertRedirect(route('checkout.index'));
    }

    public function test_authenticated_customer_without_buy_now_redirects_to_account(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('account.continue'))
            ->assertRedirect(route('account'));

        $this->actingAs($user)->get(route('account.login'))
            ->assertRedirect(route('account'));
    }

    public function test_expired_buy_now_redirects_to_account_not_checkout(): void
    {
        $product = $this->shopProduct($this->shopCategory());
        $user = User::factory()->unverified()->create();
        $expiredAt = now()->subMinutes(config('shop.buy_now_ttl_minutes', 120) + 5)->timestamp;

        $this->actingAs($user)->withSession([
            'buy_now' => [
                'product_id' => $product->id,
                'quantity' => 1,
                'finish_slug' => null,
                'finish_name' => null,
                'size_label' => null,
                'created_at' => $expiredAt,
            ],
        ])->get(route('account.login'))
            ->assertRedirect(route('account'));

        $this->assertNull(session('buy_now'));
    }

    public function test_ineligible_buy_now_redirects_to_account_not_checkout(): void
    {
        $category = Category::factory()->create(['slug' => 'partitions']);
        $studio = Product::factory()->studio()->create(['category_id' => $category->id]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->withSession([
            'buy_now' => [
                'product_id' => $studio->id,
                'quantity' => 1,
                'finish_slug' => null,
                'finish_name' => null,
                'size_label' => null,
                'created_at' => now()->timestamp,
            ],
        ])->get(route('account.continue'))
            ->assertRedirect(route('account'));
    }

    public function test_studio_and_invalid_price_products_remain_blocked_from_buy_now(): void
    {
        $studioCategory = Category::factory()->create(['slug' => 'partitions']);
        $studio = Product::factory()->studio()->create(['category_id' => $studioCategory->id]);

        $this->post(route('cart.add', $studio), $this->purchaseInput(['buy_now' => 1]))
            ->assertSessionHas('error', CartGuard::MSG_STUDIO);
        $this->assertNull(session('buy_now'));

        $shopCategory = $this->shopCategory();
        $noPrice = Product::factory()->shop()->create([
            'category_id' => $shopCategory->id,
            'price' => 0,
            'stock' => 5,
        ]);

        $this->post(route('cart.add', $noPrice), $this->purchaseInput(['buy_now' => 1]))
            ->assertSessionHas('error', CartGuard::MSG_NO_PRICE);
        $this->assertNull(session('buy_now'));
    }

    public function test_browser_supplied_price_is_ignored_for_buy_now(): void
    {
        $product = $this->shopProduct($this->shopCategory(), ['price' => 5000]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cart.add', $product), $this->purchaseInput([
            'buy_now' => 1,
            'unit_price' => 1,
            'price' => 1,
            'total' => 1,
        ]))->assertRedirect(route('checkout.index'));

        $items = app(CartService::class)->checkoutItems();
        $this->assertSame(5000.0, $items->first()['unit_price']);
    }

    public function test_checkout_displays_trusted_product_summary_and_address_form(): void
    {
        $product = $this->shopProduct($this->shopCategory(), ['name' => 'Trusted Summary Chair']);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('cart.add', $product), $this->purchaseInput([
            'buy_now' => 1,
        ]))->assertRedirect(route('checkout.index'));

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Trusted Summary Chair')
            ->assertSee('Shipping details');
    }
}
