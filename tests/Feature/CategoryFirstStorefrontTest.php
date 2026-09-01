<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Support\CartGuard;
use App\Support\StorefrontPrice;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CategoryFirstStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function shopCategory(string $slug = 'coffee-tables'): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'section' => Product::SECTION_SHOP,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    private function shopProduct(Category $category, array $overrides = []): Product
    {
        return Product::factory()->shop()->create(array_merge([
            'category_id' => $category->id,
            'stock' => 5,
            'is_gallery_visible' => true,
        ], $overrides));
    }

    public function test_shop_index_permanently_redirects_to_primary_category(): void
    {
        $this->get(route('shop.index'))
            ->assertRedirect(StorefrontRoutes::primaryShopUrl())
            ->assertStatus(301);
    }

    public function test_generic_shop_page_does_not_render_as_http_200(): void
    {
        $this->get(route('shop.index'))->assertStatus(301);
        $this->get(route('shop.index'))->assertDontSee('All Products', false);
    }

    public function test_homepage_shop_links_target_category_pages(): void
    {
        $this->seedPublishedShopNavCategories();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(StorefrontRoutes::primaryShopUrl(), $html);
        $this->assertStringNotContainsString('>View All Products<', $html);
        $this->assertStringNotContainsString('All Products', $html);
    }

    public function test_shop_navigation_lists_category_links_without_all_products(): void
    {
        $this->seedPublishedShopNavCategories();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(route('shop.mirror-frames.index'), $html);
        $this->assertStringContainsString(route('shop.show', 'coffee-tables'), $html);
        $this->assertStringNotContainsString('All Products', $html);
    }

    public function test_search_form_uses_search_route(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('action="'.route('search').'"', false);
    }

    public function test_search_page_is_noindex(): void
    {
        $this->get(route('search', ['q' => 'table']))
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_active_shop_product_appears_on_its_category_page(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category, ['name' => 'Category Visible Table']);

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('Category Visible Table', false);
    }

    public function test_inactive_shop_product_does_not_appear_on_category_page(): void
    {
        $category = $this->shopCategory();
        $this->shopProduct($category, ['name' => 'Inactive Category Table', 'is_active' => false]);

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertDontSee('Inactive Category Table', false);
    }

    public function test_gallery_hidden_shop_product_does_not_appear_on_category_page(): void
    {
        $category = $this->shopCategory();
        $this->shopProduct($category, ['name' => 'Hidden Gallery Table', 'is_gallery_visible' => false]);

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertDontSee('Hidden Gallery Table', false);
    }

    public function test_fixed_price_formats_correctly(): void
    {
        $product = Product::factory()->shop()->make(['price' => 14000, 'compare_price' => null]);

        $this->assertSame('₹14,000', StorefrontPrice::listingLabel($product));
    }

    public function test_starting_price_formats_correctly_for_size_options(): void
    {
        $category = Category::factory()->create(['slug' => 'door-handles']);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'price' => 18000,
            'size_options' => [
                ['label' => 'Small', 'price' => 14000],
                ['label' => 'Large', 'price' => 18000],
            ],
        ]);

        $this->assertSame('From ₹14,000 / pc', StorefrontPrice::listingLabel($product));
    }

    public function test_missing_price_never_displays_zero(): void
    {
        $product = Product::factory()->shop()->make(['price' => 0]);

        $this->assertSame('Price on request', StorefrontPrice::listingLabel($product));
        $this->assertNull(StorefrontPrice::formatInr(0));
    }

    public function test_studio_square_foot_price_unit_formats_correctly(): void
    {
        $product = Product::factory()->studio()->make(['price' => 1250]);

        $this->assertSame('From ₹1,250 / sq ft', StorefrontPrice::listingLabel($product));
    }

    public function test_studio_panel_price_unit_formats_correctly(): void
    {
        $product = Product::factory()->studio()->create([
            'price' => 18000,
            'pricing_type' => 'panel',
        ]);

        $this->assertSame('₹18,000 / panel', StorefrontPrice::listingLabel($product));
    }

    public function test_studio_piece_price_unit_formats_correctly(): void
    {
        $product = Product::factory()->studio()->create([
            'price' => 7500,
            'pricing_type' => 'piece',
        ]);

        $this->assertSame('₹7,500 / pc', StorefrontPrice::listingLabel($product));
    }

    public function test_studio_missing_price_shows_price_on_request(): void
    {
        $product = Product::factory()->studio()->make([
            'price' => 0,
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
        ]);

        $this->assertSame('Price on request', StorefrontPrice::listingLabel($product));
    }

    public function test_studio_gallery_uses_request_quote_cta(): void
    {
        \App\Models\Service::query()->create([
            'name' => 'PVD Partitions',
            'slug' => 'partitions',
            'summary' => 'Precision stainless partitions.',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'PVD Partitions', 'section' => Product::SECTION_STUDIO, 'is_active' => true]
        );

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Studio Quote Partition',
            'is_gallery_visible' => true,
        ]);

        $this->get(route('studio.show', 'pvd-partitions'))
            ->assertOk()
            ->assertSee('Request Quote', false);
    }

    public function test_studio_product_cannot_enter_direct_checkout(): void
    {
        $category = Category::factory()->create(['slug' => 'partitions']);
        $studio = Product::factory()->studio()->create(['category_id' => $category->id]);

        $this->post(route('cart.add', $studio), ['quantity' => 1, 'buy_now' => 1])
            ->assertSessionHas('error', CartGuard::MSG_STUDIO);
    }

    public function test_logged_out_buy_now_redirects_to_account_continue(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category);

        $this->post(route('cart.add', $product), ['quantity' => 1, 'buy_now' => 1])
            ->assertRedirect(route('account.continue'));
    }

    public function test_login_restores_buy_now_checkout(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category, ['name' => 'Login Restore Table']);
        $user = User::factory()->unverified()->create([
            'email' => 'restore@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 1, 'buy_now' => 1])
            ->assertRedirect(route('account.continue'));

        $this->post(route('account.login.email'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('checkout.index'));

        $this->get(route('checkout.index'))->assertOk()->assertSee('Login Restore Table', false);
    }

    public function test_registration_restores_buy_now_checkout(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category, ['name' => 'Register Restore Lamp']);

        $this->post(route('cart.add', $product), ['quantity' => 1, 'buy_now' => 1])
            ->assertRedirect(route('account.continue'));

        $this->post(route('account.register.send'), [
            'name' => 'New Shopper',
            'email' => 'newshopper@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('checkout.index'));

        $this->get(route('checkout.index'))->assertOk()->assertSee('Register Restore Lamp', false);
    }

    public function test_external_return_destinations_are_rejected_after_login(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'safe@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->withSession(['url.intended' => 'https://evil.example/phish'])
            ->post(route('account.login.email'), [
                'email' => $user->email,
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('account'));
    }

    public function test_browser_supplied_product_price_is_ignored_for_buy_now(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category, ['price' => 5000]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cart.add', $product), [
            'quantity' => 1,
            'buy_now' => 1,
            'unit_price' => 1,
            'price' => 1,
            'total' => 1,
        ])->assertRedirect(route('checkout.index'));

        $items = app(CartService::class)->checkoutItems();
        $this->assertSame(5000.0, (float) $items->first()['unit_price']);
    }

    public function test_regular_cart_remains_operational(): void
    {
        $category = $this->shopCategory();
        $product = $this->shopProduct($category);

        $this->post(route('cart.add', $product), ['quantity' => 2])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, $this->sessionCartLine($product)['quantity'] ?? null);
    }

    public function test_sitemap_excludes_generic_shop_url(): void
    {
        $this->seedPublishedShopNavCategories();

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $genericShop = '<loc>'.route('shop.index').'</loc>';

        $this->assertStringNotContainsString($genericShop, $xml);
        $this->assertStringContainsString(route('shop.mirror-frames.index'), $xml);
        $this->assertStringContainsString(route('shop.show', 'coffee-tables'), $xml);
    }

    public function test_category_page_has_self_referencing_canonical(): void
    {
        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee(route('shop.show', 'coffee-tables'), false);
    }

    public function test_blog_tree_is_unchanged_against_approved_checkpoint(): void
    {
        $blogRoot = base_path('resources/views/blog');
        $this->assertDirectoryExists($blogRoot);

        $approved = trim((string) shell_exec('git diff b01aa8a -- resources/views/blog 2>&1'));

        $this->assertSame('', $approved, 'Blog tree must remain identical to approved checkpoint b01aa8a.');
    }

    private function seedPublishedShopNavCategories(): void
    {
        foreach (['mirror-frames', 'coffee-tables'] as $slug) {
            $category = $this->shopCategory($slug);
            $this->shopProduct($category, [
                'name' => ucwords(str_replace('-', ' ', $slug)).' Nav Product',
                'is_gallery_visible' => true,
            ]);
        }
    }
}
