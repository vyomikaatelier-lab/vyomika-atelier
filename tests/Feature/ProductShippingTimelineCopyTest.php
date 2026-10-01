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

    public function test_railings_and_unknown_classifications_use_quotation_wording_without_included_shipping(): void
    {
        $estimate = 'Production estimate: confirmed after the site measure.';
        $railings = $this->product(Product::SECTION_RAILINGS, $estimate, 'timeline-copy-railing');
        $railings->forceFill(['sku' => 'RL-200', 'headline_text' => null])->save();

        $this->assertFalse($railings->canEnterCart());
        $this->assertSame(Product::PURCHASE_MODE_QUOTE, $railings->purchase_mode);

        $page = $this->get(route('shop.show', $railings->slug));
        $page->assertOk();
        $page->assertSee($estimate, false);
        $page->assertSee('SKU: RL-200', false);
        $page->assertSee(IndiaDelivery::QUOTATION_SHIPPING_NOTE, false);
        $page->assertDontSee(IndiaDelivery::SHOP_INDIA_SHIPPING, false);
        $page->assertDontSee(IndiaDelivery::STUDIO_SHIPPING_NOTE, false);
        $page->assertDontSee('included in the displayed price', false);
        $page->assertDontSee('Pan-India shipping', false);
        $page->assertDontSee('during Pan-India shipping', false);
        $this->assertSame($estimate, $railings->fresh()->tab_shipping);
        $this->assertSame(Product::PURCHASE_MODE_QUOTE, $railings->fresh()->purchase_mode);
        $this->assertFalse($railings->fresh()->canEnterCart());

        $unknown = Product::factory()->create([
            'category_id' => Category::factory()->create([
                'slug' => 'unclassified-fixture-cat',
                'section' => null,
                'is_active' => false,
            ])->id,
            'slug' => 'unclassified-fixture-item',
            'section' => null,
            'purchase_mode' => null,
            'pricing_type' => null,
            'tab_shipping' => 'Production estimate: ask in the quotation.',
            'headline_text' => 'SKU: UNK-1 · Saved headline',
            'is_active' => false,
            'is_gallery_visible' => true,
        ]);

        $this->assertNull($unknown->resolvedSection());
        $this->assertSame(IndiaDelivery::QUOTATION_SHIPPING_NOTE, IndiaDelivery::shippingTrustNote(null));
        $this->assertSame(IndiaDelivery::QUOTATION_SHIPPING_NOTE, IndiaDelivery::shippingTrustNote($unknown));
        $this->get(route('shop.show', $unknown->slug))->assertNotFound();
    }

    public function test_future_shop_and_studio_categories_follow_section_and_keep_saved_text_after_enabling(): void
    {
        $shopEstimate = 'Production estimate: 8–10 weeks.';
        $shopHeadline = 'SKU: LANTERN-1 · Saved shop headline';
        $shopCategory = Category::factory()->create([
            'slug' => 'future-shop-lanterns',
            'section' => Product::SECTION_SHOP,
            'is_active' => false,
        ]);
        $shop = Product::factory()->create([
            'category_id' => $shopCategory->id,
            'slug' => 'future-lantern-piece',
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'tab_shipping' => $shopEstimate,
            'headline_text' => $shopHeadline,
            'sku' => 'LANTERN-1',
            'is_active' => false,
            'is_gallery_visible' => true,
        ]);

        $this->get(route('shop.show', $shop->slug))->assertNotFound();

        $shopCategory->update(['is_active' => true]);
        $shop->update(['is_active' => true]);

        $shopPage = $this->get(route('shop.show', $shop->slug));
        $shopPage->assertOk();
        $shopPage->assertSee($shopEstimate, false);
        $shopPage->assertSee($shopHeadline, false);
        $shopPage->assertSee(IndiaDelivery::SHOP_INDIA_SHIPPING, false);
        $shopPage->assertSee(IndiaDelivery::SHOP_INTERNATIONAL_SHIPPING, false);
        $shopPage->assertDontSee(IndiaDelivery::STUDIO_SHIPPING_NOTE, false);
        $shopPage->assertDontSee(IndiaDelivery::QUOTATION_SHIPPING_NOTE, false);
        $this->assertSame($shopEstimate, $shop->fresh()->tab_shipping);
        $this->assertSame($shopHeadline, $shop->fresh()->headline_text);
        $this->assertSame(Product::PURCHASE_MODE_CHECKOUT, $shop->fresh()->purchase_mode);
        $this->assertTrue($shop->fresh()->canEnterCart());

        $studioEstimate = 'Production estimate: 9–11 weeks.';
        $studioHeadline = 'SKU: SCR-9 · Saved studio headline';
        $studio = $this->product(Product::SECTION_STUDIO, $studioEstimate, 'future-screen-piece');
        $studio->category->update(['slug' => 'future-studio-screens']);
        $studio->forceFill([
            'headline_text' => $studioHeadline,
            'sku' => 'SCR-9',
        ])->save();

        $studioPage = $this->get(route('shop.show', $studio->slug));
        $studioPage->assertOk();
        $studioPage->assertSee($studioEstimate, false);
        $studioPage->assertSee($studioHeadline, false);
        $studioPage->assertSee(IndiaDelivery::STUDIO_SHIPPING_NOTE, false);
        $studioPage->assertDontSee(IndiaDelivery::SHOP_INDIA_SHIPPING, false);
        $studioPage->assertDontSee('included in the displayed price', false);
        $this->assertSame($studioEstimate, $studio->fresh()->tab_shipping);
        $this->assertSame($studioHeadline, $studio->fresh()->headline_text);
        $this->assertSame(Product::PURCHASE_MODE_ENQUIRY, $studio->fresh()->purchase_mode);
        $this->assertFalse($studio->fresh()->canEnterCart());
    }

    public function test_preview_and_default_copy_follow_the_same_shipping_policy(): void
    {
        $about = json_decode((string) file_get_contents(public_path('data/about.json')), true, 512, JSON_THROW_ON_ERROR);
        $professionals = json_decode((string) file_get_contents(public_path('data/professionals.json')), true, 512, JSON_THROW_ON_ERROR);
        $legal = json_decode((string) file_get_contents(public_path('data/legal.json')), true, 512, JSON_THROW_ON_ERROR);
        $site = json_decode((string) file_get_contents(public_path('data/site-content.json')), true, 512, JSON_THROW_ON_ERROR);

        $execution = collect($about['values']['items'] ?? [])->firstWhere('title', 'Reliable Execution');
        $timelines = collect($professionals['why_partner']['items'] ?? [])->firstWhere('title', 'Reliable Timelines');
        $leadTime = collect($professionals['faq']['items'] ?? [])->firstWhere('q', 'What are typical lead times?');
        $badge = collect($site['trust_badges'] ?? [])->firstWhere('title', 'Timelines confirmed');

        $this->assertSame(IndiaDelivery::TIMELINE_CONFIRMATION, str_replace('Clear timelines and secure packaging. ', '', (string) ($execution['text'] ?? '')));
        $this->assertSame(IndiaDelivery::TIMELINE_CONFIRMATION, $timelines['text'] ?? null);
        $this->assertStringContainsString(IndiaDelivery::TIMELINE_CONFIRMATION, (string) ($leadTime['a'] ?? ''));
        $this->assertSame('Production and delivery timelines depend on the product and destination.', $badge['text'] ?? null);
        $this->assertSame(config('site.trust_badges.1.text'), $badge['text'] ?? null);

        $shippingParagraphs = collect($legal['pages']['shipping']['sections'] ?? [])
            ->flatMap(fn (array $section): array => $section['paragraphs'] ?? [])
            ->implode("\n");
        $this->assertStringContainsString(IndiaDelivery::TIMELINE_CONFIRMATION, $shippingParagraphs);
        $this->assertStringContainsString('quoted separately at dispatch and agreed with the client before dispatch', $shippingParagraphs);
        $this->assertStringContainsString('Shipping within India is included in the displayed product price.', $shippingParagraphs);
        $this->assertStringNotContainsString('3–4 weeks', $shippingParagraphs);
        $this->assertStringNotContainsString('5–12', $shippingParagraphs);
        $this->assertStringNotContainsString('15–35', $shippingParagraphs);

        $root = json_encode(base_path(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $script = <<<JS
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = {$root};
const sandbox = {
  console,
  window: {
    addEventListener: function () {},
  },
  document: {
    readyState: 'loading',
    getElementById: () => ({ innerHTML: '' }),
    querySelector: () => null,
    documentElement: { dataset: {} },
    dispatchEvent: () => {},
    addEventListener: () => {},
  },
  XMLHttpRequest: function () { this.open = function () {}; this.send = function () {}; },
  CustomEvent: function () {},
};
sandbox.window.document = sandbox.document;
vm.runInNewContext(fs.readFileSync(path.join(root, 'public/js/preview-router.js'), 'utf8'), sandbox);
const api = sandbox.window.AmPreviewRouterApi;
const shop = { slug: 'future-lantern-piece', sku: 'LANTERN-1', section: 'shop' };
const studio = { slug: 'future-screen-piece', sku: 'SCR-9', section: 'studio', headline_text: 'SKU: SCR-9 · Saved studio headline' };
const railings = { slug: 'timeline-copy-railing', sku: 'RL-200', section: 'railings' };
const unknown = { slug: 'unclassified-fixture-item', sku: 'UNK-1' };
process.stdout.write(JSON.stringify({
  shopNote: api.shippingNoteHtml(shop),
  studioNote: api.shippingNoteHtml(studio),
  railingsNote: api.shippingNoteHtml(railings),
  unknownNote: api.shippingNoteHtml(unknown),
  emptyNote: api.shippingNoteHtml(null),
  indiaNote: api.shippingNoteHtml(shop, 'india'),
  internationalNote: api.shippingNoteHtml(shop, 'international'),
  shopHeadline: api.previewHeadline(shop),
  studioHeadline: api.previewHeadline(studio),
  railingsHeadline: api.previewHeadline(railings),
}));
JS;
        $process = new \Symfony\Component\Process\Process(['node', '-e', $script]);
        $process->mustRun();
        $preview = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            IndiaDelivery::SHOP_INDIA_SHIPPING.' '.IndiaDelivery::SHOP_INTERNATIONAL_SHIPPING,
            $preview['shopNote']
        );
        $this->assertSame(IndiaDelivery::STUDIO_SHIPPING_NOTE, $preview['studioNote']);
        $this->assertSame(IndiaDelivery::QUOTATION_SHIPPING_NOTE, $preview['railingsNote']);
        $this->assertSame(IndiaDelivery::QUOTATION_SHIPPING_NOTE, $preview['unknownNote']);
        $this->assertSame(IndiaDelivery::QUOTATION_SHIPPING_NOTE, $preview['emptyNote']);
        $this->assertSame(IndiaDelivery::SHOP_INDIA_SHIPPING, $preview['indiaNote']);
        $this->assertSame(IndiaDelivery::ENQUIRY_HINT, $preview['internationalNote']);
        $this->assertSame('SKU: LANTERN-1 · Pan-India shipping', $preview['shopHeadline']);
        $this->assertSame('SKU: SCR-9 · Saved studio headline', $preview['studioHeadline']);
        $this->assertSame('SKU: RL-200', $preview['railingsHeadline']);
    }

    public function test_service_design_preview_renders_a_missing_product_without_shop_shipping(): void
    {
        $root = json_encode(base_path(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $script = <<<JS
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = {$root};
const store = { innerHTML: '' };
const sandbox = {
  console,
  window: {
    addEventListener: function () {},
    scrollTo: function () {},
    AmPreview: { fmt: function (n) { return '₹' + n; } },
    AmPreviewCart: {
      read: function () { return [{ slug: 'brushed-brass-coffee-table', name: 'Table', price: 100, qty: 1 }]; },
      subtotal: function () { return 100; },
      fmt: function (n) { return '₹' + n; },
    },
  },
  document: {
    title: '',
    readyState: 'loading',
    getElementById: function (id) { return id === 'am-main' ? store : { innerHTML: '' }; },
    querySelector: function () { return null; },
    documentElement: { dataset: {} },
    dispatchEvent: function () {},
    addEventListener: function () {},
  },
  history: { pushState: function () {}, replaceState: function () {} },
  location: { pathname: '/', search: '', origin: 'http://localhost' },
  URLSearchParams,
  URL,
  XMLHttpRequest: function () { this.open = function () {}; this.send = function () {}; },
  CustomEvent: function () {},
};
sandbox.window.document = sandbox.document;
vm.runInNewContext(fs.readFileSync(path.join(root, 'public/js/preview-router.js'), 'utf8'), sandbox);
const api = sandbox.window.AmPreviewRouterApi;
api.useSiteData({
  services: [{
    slug: 'corten-steel-facade',
    name: 'Corten steel',
    has_calculator: true,
    rate_per_sqft: 2200,
    calc_label: 'screen',
    care: ['Material: Weathering steel'],
    designs: [{
      slug: 'entrance-screen',
      name: 'Entrance Screen',
      description: 'A corten entrance screen.',
    }],
  }],
});
api.renderServiceDesign('corten-steel-facade', 'entrance-screen');
const serviceHtml = store.innerHTML;
api.useSiteData({ services: [] });
api.renderProduct('brushed-brass-coffee-table');
const shopHtml = store.innerHTML;
api.renderProduct('champagne-wave-partition');
const studioHtml = store.innerHTML;
api.renderCheckout();
const indiaCheckoutHtml = store.innerHTML;
process.stdout.write(JSON.stringify({ serviceHtml, shopHtml, studioHtml, indiaCheckoutHtml }));
JS;
        $process = new \Symfony\Component\Process\Process(['node', '-e', $script]);
        $process->mustRun();
        $rendered = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $service = $rendered['serviceHtml'];
        $this->assertNotSame('', $service);
        $this->assertStringContainsString('data-am-panel="shipping"', $service);
        $this->assertStringContainsString('data-am-panel="packaging"', $service);
        $this->assertStringContainsString(IndiaDelivery::QUOTATION_SHIPPING_NOTE, $service);
        $this->assertStringContainsString(IndiaDelivery::STUDIO_SHIPPING_NOTE, $service);
        $this->assertStringNotContainsString(IndiaDelivery::SHOP_INDIA_SHIPPING, $service);
        $this->assertStringNotContainsString('during Pan-India shipping', $service);

        $this->assertStringContainsString(IndiaDelivery::SHOP_INDIA_SHIPPING, $rendered['shopHtml']);
        $this->assertStringContainsString(IndiaDelivery::SHOP_INTERNATIONAL_SHIPPING, $rendered['shopHtml']);
        $this->assertStringContainsString('· Pan-India shipping', $rendered['shopHtml']);
        $this->assertStringNotContainsString(IndiaDelivery::STUDIO_SHIPPING_NOTE, $rendered['shopHtml']);

        $this->assertStringContainsString(IndiaDelivery::STUDIO_SHIPPING_NOTE, $rendered['studioHtml']);
        $this->assertStringNotContainsString(IndiaDelivery::SHOP_INDIA_SHIPPING, $rendered['studioHtml']);
        $this->assertStringNotContainsString('· Pan-India shipping', $rendered['studioHtml']);

        $this->assertStringContainsString(IndiaDelivery::SHOP_INDIA_SHIPPING, $rendered['indiaCheckoutHtml']);
        $this->assertStringNotContainsString('import-duty responsibility', $rendered['indiaCheckoutHtml']);
    }

    private function product(string $section, ?string $shipping, ?string $slug = null): Product
    {
        $category = Category::factory()->create([
            'section' => $section,
        ]);

        $attributes = [
            'category_id' => $category->id,
            'section' => $section,
            'purchase_mode' => match ($section) {
                Product::SECTION_STUDIO => Product::PURCHASE_MODE_ENQUIRY,
                Product::SECTION_RAILINGS => Product::PURCHASE_MODE_QUOTE,
                default => Product::PURCHASE_MODE_CHECKOUT,
            },
            'pricing_type' => match ($section) {
                Product::SECTION_STUDIO => Product::PRICING_SQUARE_FOOT,
                Product::SECTION_RAILINGS => Product::PRICING_QUOTATION_ONLY,
                default => Product::PRICING_FIXED,
            },
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
