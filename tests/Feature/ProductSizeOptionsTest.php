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
            'compare_price' => 4000,
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
            'compare_price' => 4000,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '1',
            'size_options' => [
                ['label' => '8"', 'price' => 800, 'compare_price' => 3200, 'size_inches' => 8, 'sku_suffix' => '8IN'],
                ['label' => '12"', 'price' => 1500, 'compare_price' => 4500, 'size_inches' => 12, 'sku_suffix' => '12IN'],
            ],
        ])->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh()->load('category');
        $this->assertSame('800.00', (string) $product->price);
        $this->assertNull($product->compare_price);
        $this->assertCount(2, $product->normalizedSizeOptions());
        $this->assertSame('8"', $product->normalizedSizeOptions()[0]['label']);
        $this->assertSame(3200.0, $product->normalizedSizeOptions()[0]['compare_price']);
        $this->assertSame(75, $product->normalizedSizeOptions()[0]['discount_percent']);
        $this->assertSame(1500.0, $product->normalizedSizeOptions()[1]['price']);
        $this->assertSame(4500.0, $product->normalizedSizeOptions()[1]['compare_price']);
        $this->assertSame(67, $product->normalizedSizeOptions()[1]['discount_percent']);
    }

    public function test_admin_can_save_size_option_discount_percent_without_compare_price(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Discount Pct Handle',
            'slug' => 'discount-pct-handle',
            'price' => 1500,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Discount Pct Handle',
            'slug' => 'discount-pct-handle',
            'price' => 1500,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '1',
            'size_options' => [
                ['label' => '8"', 'price' => 800, 'discount_percent' => 75, 'size_inches' => 8],
                ['label' => '12"', 'price' => 1500, 'discount_percent' => 60, 'size_inches' => 12],
            ],
        ])->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh()->load('category');
        $options = $product->normalizedSizeOptions();
        $this->assertSame(3200.0, $options[0]['compare_price']);
        $this->assertSame(75, $options[0]['discount_percent']);
        $this->assertSame(3750.0, $options[1]['compare_price']);
        $this->assertSame(60, $options[1]['discount_percent']);
    }

    public function test_admin_ignores_blank_size_option_rows_and_still_saves_filled_ones(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Blank Row Handle',
            'slug' => 'blank-row-handle',
            'price' => 1000,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        // Admin form always posts at least one empty size_options[0] template row.
        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Blank Row Handle',
            'slug' => 'blank-row-handle',
            'price' => 1000,
            'stock' => 10,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '1',
            'size_options' => [
                ['label' => '', 'price' => '', 'size_inches' => '', 'sku_suffix' => ''],
                ['label' => '8"', 'price' => 800, 'size_inches' => 8, 'sku_suffix' => '8IN'],
                ['label' => '12"', 'price' => 1500, 'size_inches' => 12, 'sku_suffix' => '12IN'],
            ],
        ])->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh()->load('category');
        $this->assertTrue($product->hasSizeOptions());
        $this->assertCount(2, $product->normalizedSizeOptions());
        $this->assertSame('800.00', (string) $product->price);
    }

    public function test_admin_can_save_door_handle_when_only_blank_size_row_is_posted(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'No Sizes Handle',
            'slug' => 'no-sizes-handle',
            'price' => 1200,
            'stock' => 4,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'size_options' => [
                ['label' => '8"', 'price' => 800],
            ],
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'No Sizes Handle',
            'slug' => 'no-sizes-handle',
            'price' => 1200,
            'stock' => 4,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '1',
            'size_options' => [
                ['label' => '', 'price' => '', 'size_inches' => '', 'sku_suffix' => ''],
            ],
        ])->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh()->load('category');
        $this->assertNull($product->size_options);
        $this->assertFalse($product->hasSizeOptions());
        $this->assertSame('1200.00', (string) $product->price);
    }

    public function test_admin_rejects_partially_filled_size_option_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Partial Size Handle',
            'slug' => 'partial-size-handle',
            'price' => 1000,
            'stock' => 2,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), [
                'category_id' => $category->id,
                'name' => 'Partial Size Handle',
                'slug' => 'partial-size-handle',
                'price' => 1000,
                'stock' => 2,
                'section' => Product::SECTION_SHOP,
                'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
                'pricing_type' => Product::PRICING_FIXED,
                'is_active' => '1',
                'size_options' => [
                    ['label' => '8"', 'price' => '', 'size_inches' => 8, 'sku_suffix' => ''],
                ],
            ])->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('size_options.0.price');
    }

    public function test_pdp_shows_size_selector_and_from_price(): void
    {
        $category = Category::factory()->create(['slug' => 'door-handles']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 800,
            'compare_price' => 3200,
            'size_options' => [
                ['label' => '8"', 'price' => 800, 'compare_price' => 3200, 'size_inches' => 8],
                ['label' => '12"', 'price' => 1500, 'compare_price' => 4500, 'size_inches' => 12],
            ],
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('data-pdp-size', false)
            ->assertSee('data-size-option', false)
            ->assertSee('data-pdp-price-display', false)
            ->assertSee('am-pdp-buy__row--with-size', false)
            ->assertSee('am-size-options--compact', false)
            ->assertSee('am-size-opt--pill', false)
            ->assertSee('data-size-price="800"', false)
            ->assertSee('data-size-price="1500"', false)
            ->assertSee('₹800', false)
            ->assertDontSee('am-featured__price-old', false)
            ->assertDontSee('From ₹800', false)
            ->assertSeeInOrder([
                'am-featured__price',
                'data-pdp-size',
            ], false);
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

    public function test_admin_rejects_size_options_for_non_door_handle_shop_products(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Wall Mirror',
            'slug' => 'wall-mirror-no-sizes',
            'price' => 18500,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'size_options' => [
                ['label' => '8"', 'price' => 800],
                ['label' => '12"', 'price' => 1500],
            ],
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Wall Mirror',
            'slug' => 'wall-mirror-no-sizes',
            'price' => 18500,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '1',
            'size_options' => [
                ['label' => '8"', 'price' => 800],
                ['label' => '12"', 'price' => 1500],
            ],
        ])->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh();
        $this->assertNull($product->size_options);
        $this->assertFalse($product->hasSizeOptions());
        $this->assertSame('18500.00', (string) $product->price);
        $this->assertSame('₹18,500', $product->formattedListingPrice());
    }

    public function test_admin_clears_size_options_when_category_leaves_door_handles(): void
    {
        $admin = User::factory()->admin()->create();
        $handles = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );
        $mirrors = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $handles->id,
            'name' => 'Pull Then Mirror',
            'slug' => 'pull-then-mirror',
            'price' => 800,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'size_options' => [
                ['label' => '8"', 'price' => 800],
                ['label' => '12"', 'price' => 1500],
            ],
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            'category_id' => $mirrors->id,
            'name' => 'Pull Then Mirror',
            'slug' => 'pull-then-mirror',
            'price' => 18500,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '1',
            'dim_width_cm' => '61',
            'dim_height_cm' => '91',
            'size_options' => [
                ['label' => '8"', 'price' => 800],
            ],
        ])->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $product->refresh()->load('category');
        $this->assertNull($product->size_options);
        $this->assertFalse($product->hasSizeOptions());
        $this->assertSame('61.00', $product->dim_width_cm);
        $this->assertTrue($product->hasMirrorDimensions());
    }

    public function test_mirror_pdp_shows_single_price_not_from_or_size_selector(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Single Price Mirror',
            'slug' => 'arched-wall-mirror',
            'price' => 18500,
            'stock' => 5,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'dim_width_cm' => 60.96,
            'dim_height_cm' => 91.44,
            // Stale leaked size_options must not affect storefront.
            'size_options' => [
                ['label' => '8"', 'price' => 800],
                ['label' => '12"', 'price' => 1500],
            ],
        ]);

        $this->assertFalse($product->fresh()->load('category')->hasSizeOptions());
        $this->assertSame('₹18,500', $product->formattedListingPrice());

        $this->get(route('shop.mirror-frames.show', 'arched-wall-mirror'))
            ->assertOk()
            ->assertSee('₹18,500', false)
            ->assertDontSee('From ₹', false)
            ->assertDontSee('data-pdp-size', false)
            ->assertSee('2 × 3 ft', false)
            ->assertSee('am-pdp__dimensions', false)
            ->assertDontSee('900 × 1200 mm standard', false)
            ->assertSee('Toughened mirror glass', false);
    }

    public function test_admin_form_shows_handle_and_mirror_labels(): void
    {
        $admin = User::factory()->admin()->create();
        $handles = Category::query()->firstOrCreate(
            ['slug' => 'door-handles'],
            ['name' => 'Door Handles', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $handles->id,
            'name' => 'Label Check Handle',
            'slug' => 'label-check-handle',
            'price' => 800,
            'stock' => 1,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Size &amp; price options (door handles — each size has its own price &amp; discount)', false)
            ->assertSee('id="size-options-section"', false)
            ->assertDontSee('id="size-options-section" class="rounded-lg border-2 border-amber-200 bg-amber-50 p-4 space-y-3 hidden"', false)
            ->assertSee('Mirror dimensions (single size — enter ft + in)', false)
            ->assertSee("selected && selected.dataset.slug === 'door-handles'", false)
            ->assertSee("selected && selected.dataset.slug === 'mirror-frames'", false);
    }

    public function test_admin_mirror_product_form_hides_door_handle_size_editor(): void
    {
        $admin = User::factory()->admin()->create();
        $mirrors = Category::query()->firstOrCreate(
            ['slug' => 'mirror-frames'],
            ['name' => 'Mirror Frames', 'section' => 'shop', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $mirrors->id,
            'name' => 'Arched Wall Mirror',
            'slug' => 'arched-wall-mirror-admin',
            'price' => 18500,
            'stock' => 2,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => true,
            'size_options' => [
                ['label' => '8"', 'price' => 800],
            ],
        ]);

        $html = $this->actingAsAdmin($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('id="size-options-section"', false)
            ->assertSee('hidden', false)
            ->assertSee('Mirror dimensions (single size — enter ft + in)', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="size-options-section"[^>]*\bhidden\b/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="mirror-dimensions-section"[^>]*\bhidden\b/',
            $html
        );
    }
}
