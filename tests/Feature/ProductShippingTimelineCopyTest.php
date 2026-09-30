<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\User;
use App\Support\IndiaDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductShippingTimelineCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_page_keeps_a_product_specific_estimate_and_states_included_india_shipping(): void
    {
        $estimate = "Production estimate: 4–6 weeks.\nCrate packing is included.";
        $product = $this->product(Product::SECTION_SHOP, $estimate);

        $response = $this->get(route('shop.show', $product->slug));

        $response->assertOk();
        $response->assertSee('Production estimate: 4–6 weeks.', false);
        $response->assertSee('Crate packing is included.', false);
        $response->assertSee(IndiaDelivery::SHOP_INDIA_SHIPPING, false);
        $response->assertSee(IndiaDelivery::SHOP_INTERNATIONAL_SHIPPING, false);
        $response->assertSee(IndiaDelivery::TIMELINE_CONFIRMATION, false);
        $response->assertDontSee(IndiaDelivery::STUDIO_SHIPPING_NOTE, false);
        $response->assertDontSee('15–35', false);
        $response->assertDontSee('5–12', false);
        $this->assertSame($estimate, $product->fresh()->tab_shipping);
    }

    public function test_studio_page_does_not_promise_included_shipping_and_keeps_its_production_estimate(): void
    {
        $estimate = 'Production estimate: 3–4 weeks.';
        $product = $this->product(Product::SECTION_STUDIO, $estimate);

        $response = $this->get(route('shop.show', $product->slug));

        $response->assertOk();
        $response->assertSee($estimate, false);
        $response->assertSee(IndiaDelivery::STUDIO_SHIPPING_NOTE, false);
        $response->assertSee(IndiaDelivery::TIMELINE_CONFIRMATION, false);
        $response->assertDontSee(IndiaDelivery::SHOP_INDIA_SHIPPING, false);
        $response->assertDontSee('included in the displayed price', false);
        $response->assertDontSee('Pan-India shipping', false);
        $response->assertDontSee('15–35', false);
        $response->assertDontSee('5–12', false);
        $this->assertSame($estimate, $product->fresh()->tab_shipping);
    }

    public function test_empty_shipping_tabs_use_section_wording_without_a_shared_day_count(): void
    {
        $shop = $this->product(Product::SECTION_SHOP, null);
        $studio = $this->product(Product::SECTION_STUDIO, null);

        $shopPage = $this->get(route('shop.show', $shop->slug));
        $shopPage->assertOk();
        $shopPage->assertSee(IndiaDelivery::SHOP_INDIA_SHIPPING, false);
        $shopPage->assertSee(IndiaDelivery::SHOP_INTERNATIONAL_SHIPPING, false);
        $shopPage->assertSee(IndiaDelivery::TIMELINE_CONFIRMATION, false);
        $shopPage->assertDontSee('15–35', false);
        $shopPage->assertDontSee('5–12', false);

        $studioPage = $this->get(route('shop.show', $studio->slug));
        $studioPage->assertOk();
        $studioPage->assertSee(IndiaDelivery::STUDIO_SHIPPING_NOTE, false);
        $studioPage->assertDontSee(IndiaDelivery::SHOP_INDIA_SHIPPING, false);
        $studioPage->assertDontSee('included in the displayed price', false);
        $studioPage->assertDontSee('Pan-India shipping', false);
        $studioPage->assertDontSee('15–35', false);
    }

    public function test_collection_page_uses_the_timeline_confirmation_and_keeps_the_saved_estimate(): void
    {
        $estimate = 'Production estimate: 4–6 weeks.';
        $product = $this->product(Product::SECTION_SHOP, $estimate, 'timeline-copy-mirror');

        $response = $this->get(route('shop.mirror-frames.show', $product->slug));

        $response->assertOk();
        $response->assertSee($estimate, false);
        $response->assertSee(IndiaDelivery::TIMELINE_CONFIRMATION, false);
        $response->assertSee(IndiaDelivery::SHOP_INDIA_SHIPPING, false);
        $response->assertDontSee('15–35', false);
        $this->assertSame($estimate, $product->fresh()->tab_shipping);
    }

    public function test_cart_and_checkout_do_not_promise_a_shared_15_to_35_day_window(): void
    {
        $user = User::factory()->create();
        $product = $this->product(Product::SECTION_SHOP, 'Production estimate: 4–6 weeks.');

        $cart = $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->get(route('cart.index'));
        $cart->assertOk();
        $cart->assertSee(IndiaDelivery::SHOP_INDIA_SHIPPING, false);
        $cart->assertSee(IndiaDelivery::SHOP_INTERNATIONAL_SHIPPING, false);
        $cart->assertDontSee(IndiaDelivery::STUDIO_SHIPPING_NOTE, false);
        $cart->assertDontSee('15–35', false);

        $india = $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->get(route('checkout.index'));
        $india->assertOk();
        $india->assertSee(IndiaDelivery::SHOP_INDIA_SHIPPING, false);
        $india->assertSee(IndiaDelivery::TIMELINE_CONFIRMATION, false);
        $india->assertDontSee('15–35', false);
        $india->assertDontSee('5–12', false);
    }

    public function test_international_checkout_confirms_shipping_before_payment_without_a_day_count(): void
    {
        $user = User::factory()->create();
        $product = $this->product(Product::SECTION_SHOP, 'Production estimate: 4–6 weeks.');
        CustomerAddress::query()->create([
            'user_id' => $user->id,
            'name' => 'Jane Doe',
            'phone' => '02079460958',
            'email' => $user->email,
            'address_line1' => '10 King Street',
            'city' => 'London',
            'state' => 'England',
            'pincode' => 'SW1A 1AA',
            'country' => 'United Kingdom',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee('data-destination-hint>'.e(IndiaDelivery::ENQUIRY_HINT), false);
        $response->assertSee('data-shipping-trust>'.e(IndiaDelivery::ENQUIRY_HINT), false);
        $response->assertSee(IndiaDelivery::TIMELINE_CONFIRMATION, false);
        $response->assertSee('before payment', false);
        $response->assertDontSee('15–35', false);
        $response->assertDontSee('5–12', false);
        $this->assertSame('Production estimate: 4–6 weeks.', $product->fresh()->tab_shipping);
    }

    private function product(string $section, ?string $shipping, ?string $slug = null): Product
    {
        $category = Category::factory()->create([
            'section' => $section,
        ]);

        $attributes = [
            'category_id' => $category->id,
            'section' => $section,
            'purchase_mode' => $section === Product::SECTION_STUDIO
                ? Product::PURCHASE_MODE_ENQUIRY
                : Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => $section === Product::SECTION_STUDIO
                ? Product::PRICING_SQUARE_FOOT
                : Product::PRICING_FIXED,
            'tab_shipping' => $shipping,
            'is_active' => true,
            'is_gallery_visible' => true,
        ];
        if ($slug !== null) {
            $attributes['slug'] = $slug;
        }

        return Product::factory()->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function cartSession(Product $product): array
    {
        return [
            'cart' => [
                $product->id => [
                    'quantity' => 1,
                    'finish_slug' => null,
                    'finish_name' => null,
                ],
            ],
        ];
    }
}
