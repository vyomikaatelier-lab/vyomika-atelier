<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use App\Support\CartGuard;
use App\Support\FinishSwatches;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartDrawerVariantTest extends TestCase
{
    use RefreshDatabase;

    private function shopCategory(string $slug = 'coffee-tables'): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('-', ' ', $slug)), 'section' => 'shop', 'is_active' => true]
        );
    }

    private function shopProduct(array $overrides = []): Product
    {
        $category = $this->shopCategory();

        return Product::factory()->shop()->create(array_merge([
            'category_id' => $category->id,
            'stock' => 10,
            'price' => 2500,
        ], $overrides));
    }

    private function sizedProduct(): Product
    {
        return Product::factory()->shop()->create([
            'category_id' => $this->shopCategory('door-handles')->id,
            'stock' => 10,
            'price' => 20000,
            'size_options' => [
                ['label' => 'Small', 'price' => 14000],
                ['label' => 'Large', 'price' => 18000],
            ],
        ]);
    }

    public function test_add_to_bag_returns_to_pdp_and_requests_drawer(): void
    {
        $product = $this->shopProduct();
        $origin = route('shop.show', $product->slug);

        $location = urldecode((string) $this->from($origin)
            ->post(route('cart.add', $product), $this->purchaseInput())
            ->assertSessionHas('success', 'Added to cart.')
            ->headers->get('Location'));

        $this->assertStringContainsString($product->slug, $location);
        $this->assertStringContainsString('cart=open', $location);
        $this->assertStringContainsString('#am-cart-drawer', $location);
    }

    public function test_buy_now_still_skips_cart_and_does_not_write_bag(): void
    {
        $product = $this->shopProduct();

        $this->post(route('cart.add', $product), $this->purchaseInput(['buy_now' => 1]))
            ->assertRedirect(route('account.continue'));

        $this->assertSame($product->id, session('buy_now')['product_id']);
        $this->assertFalse($this->sessionCartHasProduct($product));
    }

    public function test_same_variant_merges_quantity(): void
    {
        $product = $this->shopProduct();

        $this->post(route('cart.add', $product), [
            'quantity' => 1,
            'finish_slug' => 'black-mirror',
        ]);
        $this->post(route('cart.add', $product), [
            'quantity' => 2,
            'finish_slug' => 'black-mirror',
        ]);

        $this->assertSame(3, $this->sessionCartLine($product, null, 'black-mirror')['quantity'] ?? null);
        $this->assertCount(1, session('cart'));
    }

    public function test_different_sizes_remain_separate_lines(): void
    {
        $product = $this->sizedProduct();

        $this->post(route('cart.add', $product), ['quantity' => 1, 'size_label' => 'Small', 'finish_slug' => 'gold-mirror']);
        $this->post(route('cart.add', $product), ['quantity' => 1, 'size_label' => 'Large', 'finish_slug' => 'gold-mirror']);

        $this->assertSame(1, $this->sessionCartLine($product, 'Small', 'gold-mirror')['quantity'] ?? null);
        $this->assertSame(1, $this->sessionCartLine($product, 'Large', 'gold-mirror')['quantity'] ?? null);
        $this->assertCount(2, session('cart'));
    }

    public function test_different_finishes_remain_separate_lines(): void
    {
        $product = $this->shopProduct();

        $this->post(route('cart.add', $product), ['quantity' => 1, 'finish_slug' => 'black-mirror']);
        $this->post(route('cart.add', $product), ['quantity' => 1, 'finish_slug' => 'gold-brush']);

        $this->assertSame(1, $this->sessionCartLine($product, null, 'black-mirror')['quantity'] ?? null);
        $this->assertSame(1, $this->sessionCartLine($product, null, 'gold-brush')['quantity'] ?? null);
        $this->assertCount(2, session('cart'));
    }

    public function test_invalid_size_and_finish_are_rejected(): void
    {
        $product = $this->sizedProduct();

        $this->post(route('cart.add', $product), [
            'quantity' => 1,
            'size_label' => 'Not A Size',
        ])->assertSessionHas('error', CartGuard::MSG_VARIANT_REQUIRED);
        $this->assertEmpty(session('cart', []));

        $plain = $this->shopProduct();
        $this->post(route('cart.add', $plain), [
            'quantity' => 1,
            'finish_slug' => 'not-a-real-finish',
        ])->assertSessionHas('error', CartGuard::MSG_INVALID_FINISH);
        $this->assertEmpty(session('cart', []));
    }

    public function test_existing_plus_incoming_quantity_cannot_exceed_stock_or_ninety_nine(): void
    {
        $product = $this->shopProduct(['stock' => 5]);

        $this->post(route('cart.add', $product), $this->purchaseInput(['quantity' => 4]));
        $this->post(route('cart.add', $product), $this->purchaseInput(['quantity' => 4]))
            ->assertSessionHas('info');

        $this->assertSame(5, $this->sessionCartLine($product)['quantity'] ?? null);

        $highStock = $this->shopProduct(['stock' => 200, 'name' => 'Bulk Table']);
        $this->post(route('cart.add', $highStock), $this->purchaseInput(['quantity' => 80]));
        $this->post(route('cart.add', $highStock), $this->purchaseInput(['quantity' => 40]));

        $this->assertSame(99, $this->sessionCartLine($highStock)['quantity'] ?? null);
    }

    public function test_update_and_remove_affect_only_selected_variant_line(): void
    {
        $product = $this->shopProduct();

        $this->post(route('cart.add', $product), ['quantity' => 2, 'finish_slug' => 'black-mirror']);
        $this->post(route('cart.add', $product), ['quantity' => 3, 'finish_slug' => 'gold-brush']);

        $this->patch(route('cart.update', $product), [
            'quantity' => 1,
            'size_label' => '',
            'finish_slug' => 'black-mirror',
        ])->assertSessionHas('success');

        $this->assertSame(1, $this->sessionCartLine($product, null, 'black-mirror')['quantity'] ?? null);
        $this->assertSame(3, $this->sessionCartLine($product, null, 'gold-brush')['quantity'] ?? null);

        $this->delete(route('cart.remove', $product), [
            'finish_slug' => 'gold-brush',
        ])->assertSessionHas('success');

        $this->assertSame(1, $this->sessionCartLine($product, null, 'black-mirror')['quantity'] ?? null);
        $this->assertNull($this->sessionCartLine($product, null, 'gold-brush'));
    }

    public function test_cart_sanitation_updates_header_badge(): void
    {
        $live = $this->shopProduct(['name' => 'Live Badge Table']);
        $gone = $this->shopProduct(['name' => 'Hidden Badge Table']);

        $this->withSession([
            'cart' => [
                $live->id => ['quantity' => 2, 'finish_slug' => FinishSwatches::defaultSlug(), 'finish_name' => 'Champagne Mirror'],
                $gone->id => ['quantity' => 4, 'finish_slug' => FinishSwatches::defaultSlug(), 'finish_name' => 'Champagne Mirror'],
            ],
        ]);

        $gone->update(['is_active' => false]);

        $html = $this->get(route('shop.show', $live->slug))->assertOk()->getContent();

        $this->assertSame(2, app(CartService::class)->count());
        $this->assertStringContainsString('am-cart-count">2</span>', $html);
        $this->assertNotEmpty(session(CartService::NOTICE_KEY));
    }

    public function test_price_publication_and_stock_are_server_derived(): void
    {
        $product = $this->shopProduct(['price' => 4000, 'stock' => 2]);

        $this->post(route('cart.add', $product), $this->purchaseInput([
            'quantity' => 2,
            'price' => 1,
            'unit_price' => 1,
            'total' => 1,
        ]));

        $item = app(CartService::class)->all()->first();
        $this->assertSame(4000.0, $item['unit_price']);
        $this->assertSame(8000.0, $item['line_total']);

        $product->update(['price' => 5500, 'stock' => 1]);

        $refreshed = app(CartService::class)->all()->first();
        $this->assertSame(5500.0, $refreshed['unit_price']);
        $this->assertSame(1, $refreshed['quantity']);
        $this->assertNotEmpty(session(CartService::NOTICE_KEY));
    }

    public function test_drawer_shows_variant_details_trusted_total_and_checkout(): void
    {
        $product = $this->sizedProduct();
        $product->update(['name' => 'Drawer Handle']);

        $this->from(route('shop.show', $product->slug))
            ->post(route('cart.add', $product), [
                'quantity' => 2,
                'size_label' => 'Large',
                'finish_slug' => 'black-mirror',
            ]);

        $html = $this->get(route('shop.show', $product->slug))->assertOk()->getContent();

        $this->assertStringContainsString('id="am-cart-drawer"', $html);
        $this->assertStringContainsString('Drawer Handle', $html);
        $this->assertStringContainsString('Size: Large', $html);
        $this->assertStringContainsString('Finish: Black Mirror', $html);
        $this->assertStringContainsString('Qty 2', $html);
        $this->assertStringContainsString('₹36,000', $html);
        $this->assertStringContainsString('>Checkout</a>', $html);
        $this->assertStringContainsString('>View Cart</a>', $html);
        $this->assertStringContainsString('>Continue Shopping</a>', $html);
        $this->assertStringContainsString(route('checkout.index', [], false), $html);
    }

    public function test_legacy_session_cart_is_migrated_or_removed_with_notice(): void
    {
        $product = $this->shopProduct(['name' => 'Legacy Line Table']);

        $this->withSession([
            'cart' => [
                $product->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null],
            ],
        ]);

        $items = app(CartService::class)->all();
        $this->assertCount(1, $items);
        $this->assertSame(
            CartService::lineKey($product->id, null, FinishSwatches::defaultSlug()),
            $items->first()['line_key']
        );
        $this->assertArrayNotHasKey($product->id, session('cart'));
    }

    public function test_sized_gallery_buy_now_stays_hidden(): void
    {
        $product = $this->sizedProduct();

        $this->assertFalse(CartGuard::canDisplayBuyNow($product));
        $this->assertTrue(CartGuard::canDisplayChooseOptions($product));
        $this->get(route('shop.show', $product->category->slug))
            ->assertOk()
            ->assertDontSee('name="buy_now"', false)
            ->assertSee('Choose options', false);
    }

    public function test_finish_selectable_cards_link_to_pdp_instead_of_buy_now(): void
    {
        $product = $this->shopProduct([
            'name' => 'Finish Select Table',
            'slug' => 'finish-select-table',
            'is_gallery_visible' => true,
        ]);
        $pdp = StorefrontRoutes::productUrl($product);

        $this->assertTrue(CartGuard::exposesFinishSelector($product));
        $this->assertFalse(CartGuard::canDisplayBuyNow($product));
        $this->assertTrue(CartGuard::canDisplayChooseOptions($product));

        $card = view('partials.am-product-card', ['product' => $product])->render();
        $this->assertStringNotContainsString('name="buy_now"', $card);
        $this->assertStringContainsString('Choose options', $card);
        $this->assertStringContainsString($pdp, $card);

        $gallery = view('partials.am-design-gallery-card', [
            'showUrl' => $pdp,
            'title' => $product->name,
            'product' => $product,
        ])->render();
        $this->assertStringNotContainsString('name="buy_now"', $gallery);
        $this->assertStringContainsString('Choose options', $gallery);
        $this->assertStringContainsString($pdp, $gallery);

        $html = $this->get(route('shop.show', $product->category->slug))->assertOk()->getContent();
        $this->assertStringContainsString('Choose options', $html);
        $this->assertStringNotContainsString('name="buy_now"', $html);
    }

    public function test_add_to_bag_without_required_finish_is_rejected_and_preserves_input(): void
    {
        $product = $this->shopProduct();

        $this->from(route('shop.show', $product->slug))
            ->post(route('cart.add', $product), ['quantity' => 3])
            ->assertSessionHas('error', CartGuard::MSG_INVALID_FINISH)
            ->assertSessionHasInput('quantity', 3);

        $this->assertEmpty(session('cart', []));
    }

    public function test_buy_now_without_required_finish_creates_neither_cart_nor_buy_now(): void
    {
        $product = $this->shopProduct();

        $this->post(route('cart.add', $product), ['quantity' => 1, 'buy_now' => 1])
            ->assertSessionHas('error', CartGuard::MSG_INVALID_FINISH);

        $this->assertNull(session('buy_now'));
        $this->assertEmpty(session('cart', []));
    }

    public function test_pdp_submits_the_canonical_visible_finish(): void
    {
        $product = $this->shopProduct();
        $canonical = FinishSwatches::defaultSlug();

        $this->assertSame('gold-mirror', $canonical);
        $this->assertSame($canonical, config('finishes.swatches.0.slug'));

        $html = $this->get(route('shop.show', $product->slug))->assertOk()->getContent();

        $this->assertStringContainsString('name="finish_slug" value="'.$canonical.'"', $html);
        $this->assertStringContainsString('data-finish-slug="'.$canonical.'"', $html);
        $this->assertStringNotContainsString('name="finish_slug" value="champagne-mirror"', $html);

        $this->from(route('shop.show', $product->slug))
            ->post(route('cart.add', $product), $this->purchaseInput())
            ->assertSessionHas('success');

        $this->assertSame($canonical, $this->sessionCartLine($product)['finish_slug'] ?? null);
    }

    public function test_pipe_in_size_label_cannot_collide_with_another_tuple(): void
    {
        $product = Product::factory()->shop()->create([
            'category_id' => $this->shopCategory('door-handles')->id,
            'stock' => 10,
            'price' => 20000,
            'size_options' => [
                ['label' => 'Small|gold-mirror', 'price' => 14000],
                ['label' => 'Small', 'price' => 16000],
            ],
        ]);

        $this->assertNotSame(
            CartService::lineKey($product->id, 'Small|gold-mirror', 'black-brush'),
            CartService::lineKey($product->id, 'Small', 'gold-mirror|black-brush')
        );

        $this->post(route('cart.add', $product), [
            'quantity' => 1,
            'size_label' => 'Small|gold-mirror',
            'finish_slug' => 'black-brush',
        ])->assertSessionHas('success');
        $this->post(route('cart.add', $product), [
            'quantity' => 1,
            'size_label' => 'Small',
            'finish_slug' => 'gold-mirror',
        ])->assertSessionHas('success');

        $this->assertCount(2, session('cart'));
        $this->assertSame(1, $this->sessionCartLine($product, 'Small|gold-mirror', 'black-brush')['quantity'] ?? null);
        $this->assertSame(1, $this->sessionCartLine($product, 'Small', 'gold-mirror')['quantity'] ?? null);

        $this->patch(route('cart.update', $product), [
            'quantity' => 2,
            'size_label' => 'Small|gold-mirror',
            'finish_slug' => 'black-brush',
        ])->assertSessionHas('success');

        $this->assertSame(2, $this->sessionCartLine($product, 'Small|gold-mirror', 'black-brush')['quantity'] ?? null);
        $this->assertSame(1, $this->sessionCartLine($product, 'Small', 'gold-mirror')['quantity'] ?? null);

        $this->delete(route('cart.remove', $product), [
            'size_label' => 'Small|gold-mirror',
            'finish_slug' => 'black-brush',
        ])->assertSessionHas('success');

        $this->assertNull($this->sessionCartLine($product, 'Small|gold-mirror', 'black-brush'));
        $this->assertSame(1, $this->sessionCartLine($product, 'Small', 'gold-mirror')['quantity'] ?? null);
    }
}
