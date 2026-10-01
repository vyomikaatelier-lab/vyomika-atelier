<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFulfilmentOriginal;
use App\Models\User;
use App\Services\OrderRefundService;
use App\Services\ProductFulfilmentBackfill;
use App\Services\RefundIntent;
use App\Services\RefundMoney;
use App\Services\TurnstileService;
use App\Support\CheckoutPayments;
use App\Support\IndiaDelivery;
use App\Support\ProductFulfilment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use ReflectionMethod;
use Tests\TestCase;

class ProductFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_future_and_unknown_products_do_not_promise_stock_or_included_shipping(): void
    {
        $category = Category::factory()->create(['is_active' => false, 'section' => 'shop']);
        $future = Product::create([
            'category_id' => $category->id,
            'name' => 'Future Shelf',
            'slug' => 'future-shelf',
            'price' => 1000,
            'stock' => 0,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => false,
        ]);

        $this->assertSame(ProductFulfilment::AVAILABILITY_CONFIRM, $future->availability_mode);
        $this->assertSame(ProductFulfilment::CHARGE_QUOTED, $future->shipping_india_mode);
        $this->assertSame(ProductFulfilment::CHARGE_QUOTED, $future->packing_india_mode);
        $this->assertSame(ProductFulfilment::CHARGE_QUOTED, $future->shipping_international_mode);
        $this->assertNull($future->production_min);
        $this->assertStringContainsString('confirmed with the team', ProductFulfilment::availabilityLabel($future));
        $this->assertStringContainsString('quoted separately', ProductFulfilment::customerShippingNote($future));

        $unknown = Product::factory()->create([
            'section' => null,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
            'availability_mode' => ProductFulfilment::AVAILABILITY_CONFIRM,
            'shipping_india_mode' => ProductFulfilment::CHARGE_QUOTED,
            'packing_india_mode' => ProductFulfilment::CHARGE_QUOTED,
        ]);
        $this->assertSame(IndiaDelivery::QUOTATION_SHIPPING_NOTE, ProductFulfilment::customerShippingNote($unknown));
        $this->assertNotSame(Product::SECTION_SHOP, $unknown->section);
    }

    public function test_admin_validation_permissions_and_production_rules(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['slug' => 'mirror-frames', 'section' => 'shop', 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
            'availability_mode' => ProductFulfilment::AVAILABILITY_CONFIRM,
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.products.update', $product), $this->adminPayload($category, [
                'availability_mode' => 'ready_stock',
            ]))
            ->assertRedirect(route('admin.login'));
        $this->assertSame(ProductFulfilment::AVAILABILITY_CONFIRM, $product->fresh()->availability_mode);

        $this->actingAsAdmin($admin)
            ->put(route('admin.products.update', $product), $this->adminPayload($category, [
                'availability_mode' => 'overnight',
                'shipping_india_mode' => 'included',
                'packing_india_mode' => 'included',
                'shipping_international_mode' => 'quoted',
                'packing_international_mode' => 'quoted',
            ]))
            ->assertSessionHasErrors('availability_mode');

        $this->actingAsAdmin($admin)
            ->put(route('admin.products.update', $product), $this->adminPayload($category, [
                'availability_mode' => 'made_to_order',
                'shipping_india_mode' => 'fixed',
                'shipping_india_amount' => '-1',
                'shipping_india_basis' => 'per_order',
                'packing_india_mode' => 'quoted',
                'shipping_international_mode' => 'quoted',
                'packing_international_mode' => 'quoted',
            ]))
            ->assertSessionHasErrors(['production_min', 'shipping_india_amount', 'shipping_india_basis']);

        $this->actingAsAdmin($admin)
            ->put(route('admin.products.update', $product), $this->adminPayload($category, [
                'availability_mode' => 'made_to_order',
                'production_min' => 4,
                'production_max' => 6,
                'production_unit' => 'weeks',
                'production_starts' => 'final_specification_approval',
                'shipping_india_mode' => 'fixed',
                'shipping_india_amount' => '150.50',
                'shipping_india_basis' => 'per_unit',
                'packing_india_mode' => 'fixed',
                'packing_india_amount' => '0',
                'packing_india_basis' => 'per_line',
                'shipping_international_mode' => 'fixed',
                'shipping_international_amount' => '80',
                'shipping_international_basis' => 'per_line',
                'packing_international_mode' => 'quoted',
            ]))
            ->assertSessionHasNoErrors();

        $saved = $product->fresh();
        $this->assertSame(4, $saved->production_min);
        $this->assertSame('final_specification_approval', $saved->production_starts);
        $this->assertSame('150.50', $saved->shipping_india_amount);
        $this->assertSame('per_unit', $saved->shipping_india_basis);
        $this->assertSame('0.00', $saved->packing_india_amount);
        $this->assertSame(Product::PURCHASE_MODE_CHECKOUT, $saved->purchase_mode);
    }

    public function test_ready_stock_wording_follows_tracked_inventory(): void
    {
        $tracked = Product::factory()->create([
            'availability_mode' => ProductFulfilment::AVAILABILITY_READY,
            'hide_when_out_of_stock' => true,
            'stock' => 0,
        ]);
        $this->assertStringNotContainsString('Ready stock.', ProductFulfilment::availabilityLabel($tracked));
        $this->assertStringContainsString('not currently available', ProductFulfilment::availabilityLabel($tracked));

        $untracked = Product::factory()->create([
            'availability_mode' => ProductFulfilment::AVAILABILITY_READY,
            'hide_when_out_of_stock' => false,
            'stock' => 0,
        ]);
        $label = ProductFulfilment::availabilityLabel($untracked);
        $this->assertSame('Ready stock.', $label);
        $this->assertStringNotContainsString('0', $label);
    }

    public function test_charge_math_mixed_carts_and_quoted_blocks_payment(): void
    {
        $perUnit = Product::factory()->create([
            'price' => 1000,
            'shipping_india_mode' => 'fixed',
            'shipping_india_amount' => '10.00',
            'shipping_india_basis' => 'per_unit',
            'packing_india_mode' => 'fixed',
            'packing_india_amount' => '25.00',
            'packing_india_basis' => 'per_line',
        ]);
        $included = Product::factory()->create(['price' => 500]);
        $quote = ProductFulfilment::quote([
            ['product' => $perUnit, 'quantity' => 3, 'line_total' => 3000],
            ['product' => $included, 'quantity' => 2, 'line_total' => 1000],
        ], true);

        $this->assertTrue($quote['payable']);
        $this->assertSame(400000, $quote['merchandise_paise']);
        $this->assertSame(3000, $quote['shipping_paise']);
        $this->assertSame(2500, $quote['packing_paise']);
        $this->assertSame('per unit', $quote['lines'][0]['shipping_basis_label']);
        $this->assertSame('per product line', $quote['lines'][0]['packing_basis_label']);

        $zero = Product::factory()->create([
            'shipping_india_mode' => 'fixed',
            'shipping_india_amount' => '0.00',
            'shipping_india_basis' => 'per_line',
            'packing_india_mode' => 'included',
        ]);
        $zeroQuote = ProductFulfilment::quote([
            ['product' => $zero, 'quantity' => 2, 'line_total' => 1000],
        ], true);
        $this->assertTrue($zeroQuote['payable']);
        $this->assertSame(0, $zeroQuote['shipping_paise']);
        $this->assertSame('₹0.00', $zeroQuote['shipping_label']);
        $this->assertSame('Packing included', $zeroQuote['packing_label']);

        $quoted = Product::factory()->create([
            'shipping_india_mode' => 'quoted',
            'packing_india_mode' => 'included',
        ]);
        $blocked = ProductFulfilment::quote([
            ['product' => $perUnit, 'quantity' => 1, 'line_total' => 1000],
            ['product' => $quoted, 'quantity' => 1, 'line_total' => 1000],
        ], true);
        $this->assertFalse($blocked['payable']);
        $this->assertStringContainsString($quoted->name, implode(' ', $blocked['reasons']));
    }

    public function test_checkout_uses_server_charges_and_snapshots_survive_later_edits(): void
    {
        config(['services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 'rzp_test_secret']);
        $amounts = [];
        Http::fake(function ($request) use (&$amounts) {
            $amounts[] = (int) ($request->data()['amount'] ?? -1);

            return Http::response([
                'id' => 'order_fulfilment_1',
                'amount' => $amounts[0],
                'currency' => 'INR',
                'receipt' => 'local',
                'status' => 'created',
            ], 200);
        });

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price' => 2000,
            'stock' => 5,
            'shipping_india_mode' => 'fixed',
            'shipping_india_amount' => '10.00',
            'shipping_india_basis' => 'per_unit',
            'packing_india_mode' => 'fixed',
            'packing_india_amount' => '5.00',
            'packing_india_basis' => 'per_line',
            'shipping_international_mode' => 'fixed',
            'shipping_international_amount' => '99.00',
            'shipping_international_basis' => 'per_line',
        ]);

        $this->actingAs($user)
            ->withSession(['cart' => [$product->id => ['quantity' => 2, 'finish_slug' => null, 'finish_name' => null]]])
            ->post(route('checkout.store'), array_merge($this->addressPayload(), $this->protectionFields(), [
                'shipping_cost' => 199,
                'packing_cost' => 1,
                'total' => 1,
            ]))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('20.00', $order->shipping_cost);
        $this->assertSame('5.00', $order->packing_cost);
        $this->assertSame('4025.00', $order->total);
        $this->assertSame(1, $order->fulfilment_snapshot['version']);
        $this->assertSame(402500, $amounts[0]);
        $this->assertFalse(IndiaDelivery::hasObsoleteShippingCharge($order));
        $this->assertTrue(IndiaDelivery::canInitiateSelfServicePayment($order));

        $product->update([
            'shipping_india_amount' => '80.00',
            'price' => 9000,
        ]);
        $order->refresh();
        $this->assertSame('20.00', $order->shipping_cost);
        $this->assertSame('4025.00', $order->total);
        $this->assertSame('20.00', $order->items()->first()->fulfilment_snapshot['shipping_charge']);

        $this->actingAs($user)
            ->get(route('checkout.pay', $order))
            ->assertOk();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('4025.00', $order->fresh()->total);
    }

    public function test_quoted_and_international_carts_stay_enquiry_only_and_keep_the_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Quoted Table',
            'price' => 1500,
            'stock' => 4,
            'shipping_india_mode' => 'quoted',
            'packing_india_mode' => 'included',
        ]);
        $session = ['cart' => [$product->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]]];

        $this->actingAs($user)
            ->withSession($session)
            ->post(route('checkout.store'), array_merge($this->addressPayload(), $this->protectionFields()))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('success', ProductFulfilment::QUOTE_SAVED);

        $this->assertSame(0, Order::query()->count());
        $lead = Lead::query()->where('type', 'fulfilment_quote')->firstOrFail();
        $this->assertStringContainsString('Quoted Table', $lead->message);
        $this->assertFalse($lead->metadata['payment_started']);
        $this->assertNotEmpty(session('cart'));
    }

    public function test_international_fixed_charge_remains_enquiry_only(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price' => 1500,
            'stock' => 4,
            'shipping_international_mode' => 'fixed',
            'shipping_international_amount' => '99.00',
            'shipping_international_basis' => 'per_line',
        ]);

        $this->actingAs($user)
            ->withSession(['cart' => [$product->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]]])
            ->post(route('checkout.store'), array_merge($this->addressPayload('United States'), $this->protectionFields()))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('success', IndiaDelivery::ENQUIRY_SAVED);

        $this->assertSame(0, Order::query()->count());
        $this->assertNotEmpty(session('cart'));
    }

    public function test_disabled_checkout_refuses_payment_but_can_save_a_quotation(): void
    {
        config(['checkout.payments_enabled' => false]);
        $this->assertFalse(CheckoutPayments::enabled());

        $user = User::factory()->create();
        $payable = Product::factory()->create(['price' => 1000, 'stock' => 3]);
        $this->actingAs($user)
            ->withSession(['cart' => [$payable->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]]])
            ->post(route('checkout.store'), array_merge($this->addressPayload(), $this->protectionFields()))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', CheckoutPayments::UNAVAILABLE_MESSAGE);
        $this->assertSame(0, Order::query()->count());

        $quoted = Product::factory()->create([
            'price' => 1000,
            'stock' => 3,
            'shipping_india_mode' => 'quoted',
        ]);
        $this->actingAs($user)
            ->withSession(['cart' => [$quoted->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]]])
            ->post(route('checkout.store'), array_merge($this->addressPayload(), $this->protectionFields()))
            ->assertSessionHas('success', ProductFulfilment::QUOTE_SAVED);
        $this->assertSame(0, Order::query()->count());
        $this->assertFalse(CheckoutPayments::enabled());
    }

    public function test_get_does_not_reprice_and_post_replaces_an_untouched_pending_order(): void
    {
        config(['services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 'rzp_test_secret']);
        Http::fake(function () {
            return Http::response([
                'id' => 'order_replace_'.uniqid(),
                'amount' => 100000,
                'currency' => 'INR',
                'receipt' => 'local',
                'status' => 'created',
            ], 200);
        });

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 4]);
        $session = ['cart' => [$product->id => ['quantity' => 1, 'finish_slug' => null, 'finish_name' => null]]];
        $this->actingAs($user)->withSession($session)
            ->post(route('checkout.store'), array_merge($this->addressPayload(), $this->protectionFields()))
            ->assertRedirect();

        $original = Order::query()->firstOrFail();
        $this->assertSame('0.00', $original->shipping_cost);
        $original->forceFill([
            'razorpay_order_id' => null,
            'payment_id' => null,
        ])->save();
        $product->update([
            'shipping_india_mode' => 'fixed',
            'shipping_india_amount' => '15.00',
            'shipping_india_basis' => 'per_line',
        ]);

        $this->actingAs($user)->get(route('checkout.pay', $original))->assertOk();
        $this->assertSame('pending', $original->fresh()->status);
        $this->assertSame('0.00', $original->fresh()->shipping_cost);
        $this->assertNull($original->fresh()->razorpay_order_id);

        $this->actingAs($user)->withSession($session)
            ->post(route('checkout.store'), array_merge($this->addressPayload(), $this->protectionFields()))
            ->assertRedirect();

        $this->assertSame('cancelled', $original->fresh()->status);
        $this->assertSame('0.00', $original->fresh()->shipping_cost);
        $replacement = Order::query()->where('status', 'pending')->firstOrFail();
        $this->assertSame('15.00', $replacement->shipping_cost);
        $this->assertSame('1015.00', $replacement->total);
        $this->assertNotSame($original->id, $replacement->id);
    }

    public function test_backfill_is_retry_safe_and_preserves_stock_and_unknown_sections(): void
    {
        $disabledShop = Category::factory()->create(['is_active' => false, 'section' => 'shop']);
        $shop = Product::factory()->create([
            'category_id' => $disabledShop->id,
            'is_active' => false,
            'section' => Product::SECTION_SHOP,
            'stock' => 0,
            'hide_when_out_of_stock' => false,
            'availability_mode' => ProductFulfilment::AVAILABILITY_CONFIRM,
            'shipping_india_mode' => ProductFulfilment::CHARGE_QUOTED,
            'packing_india_mode' => ProductFulfilment::CHARGE_QUOTED,
            'tab_shipping' => "Estimated production time: 3–4 weeks.\nThe estimated production lead time will be confirmed after your order is placed.\nShipping within India is included in the displayed price.",
        ]);
        $studio = Product::factory()->studio()->create([
            'tab_shipping' => 'Estimated production time: approximately 4–6 weeks after approval of final dimensions.',
            'stock' => 2,
        ]);
        $unknown = Product::factory()->create([
            'section' => 'archive',
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
            'availability_mode' => ProductFulfilment::AVAILABILITY_CONFIRM,
            'shipping_india_mode' => ProductFulfilment::CHARGE_QUOTED,
            'packing_india_mode' => ProductFulfilment::CHARGE_QUOTED,
            'tab_shipping' => 'Delivery is usually 5 to 12 business days and is included in the displayed price.',
        ]);

        $counts = app(ProductFulfilmentBackfill::class)->run();
        $this->assertSame(1, $counts['shop']);
        $this->assertSame(1, $counts['studio']);
        $this->assertSame(1, $counts['unknown']);
        $this->assertGreaterThan(0, $counts['flagged']);

        $shop->refresh();
        $studio->refresh();
        $unknown->refresh();
        $this->assertSame(ProductFulfilment::AVAILABILITY_READY, $shop->availability_mode);
        $this->assertSame(ProductFulfilment::CHARGE_INCLUDED, $shop->shipping_india_mode);
        $this->assertSame(ProductFulfilment::CHARGE_INCLUDED, $shop->packing_india_mode);
        $this->assertSame(ProductFulfilment::CHARGE_QUOTED, $shop->shipping_international_mode);
        $this->assertSame(0, $shop->stock);
        $this->assertFalse($shop->hide_when_out_of_stock);
        $this->assertFalse($shop->needs_fulfilment_review);
        $this->assertNull($shop->fulfilment_review_note);
        $this->assertStringContainsString('3–4 weeks', $shop->tab_shipping);
        $this->assertStringContainsString('confirmed after your order is placed', $shop->tab_shipping);
        $this->assertTrue($unknown->needs_fulfilment_review);

        $this->assertSame(ProductFulfilment::AVAILABILITY_MADE, $studio->availability_mode);
        $this->assertSame(4, $studio->production_min);
        $this->assertSame(6, $studio->production_max);
        $this->assertSame(ProductFulfilment::START_SPECIFICATION, $studio->production_starts);
        $this->assertSame(ProductFulfilment::CHARGE_QUOTED, $studio->shipping_india_mode);
        $this->assertSame(2, $studio->stock);

        $this->assertSame('archive', $unknown->section);
        $this->assertSame(ProductFulfilment::AVAILABILITY_CONFIRM, $unknown->availability_mode);
        $this->assertNotSame(Product::SECTION_SHOP, $unknown->section);

        $shop->update(['availability_mode' => ProductFulfilment::AVAILABILITY_CONFIRM, 'shipping_india_mode' => ProductFulfilment::CHARGE_FIXED]);
        $again = app(ProductFulfilmentBackfill::class)->run();
        $this->assertSame(3, $again['skipped']);
        $this->assertSame(ProductFulfilment::AVAILABILITY_CONFIRM, $shop->fresh()->availability_mode);
        $this->assertSame(ProductFulfilment::CHARGE_FIXED, $shop->fresh()->shipping_india_mode);
        $original = ProductFulfilmentOriginal::query()->where('product_id', $shop->id)->firstOrFail();
        $this->assertSame(0, $original->original['stock']);
        $this->assertStringContainsString('3–4 weeks', $original->original['tab_shipping']);
    }

    public function test_ready_stock_confirmation_clears_only_resolved_production_flags(): void
    {
        $shop = Product::factory()->create([
            'section' => Product::SECTION_SHOP,
            'stock' => 4,
            'hide_when_out_of_stock' => true,
            'availability_mode' => ProductFulfilment::AVAILABILITY_READY,
            'needs_fulfilment_review' => true,
            'tab_shipping' => 'Estimated production time: 3–4 weeks. Inspect the crate on delivery.',
            'fulfilment_review_note' => 'A production range is saved, but the text does not say whether it starts at order confirmation or final-specification approval. Ready stock was applied. Complete the structured production fields only if this product is not ready stock.',
        ]);
        $confirmedAfter = Product::factory()->create([
            'section' => Product::SECTION_SHOP,
            'stock' => 1,
            'availability_mode' => ProductFulfilment::AVAILABILITY_READY,
            'needs_fulfilment_review' => true,
            'tab_shipping' => 'The estimated production lead time will be confirmed after your order is placed.',
            'fulfilment_review_note' => 'The saved text says the production lead time will be confirmed after the order is placed. Enter the estimate and its start event before showing a production period.',
        ]);
        $mixed = Product::factory()->create([
            'section' => Product::SECTION_SHOP,
            'stock' => 2,
            'availability_mode' => ProductFulfilment::AVAILABILITY_READY,
            'needs_fulfilment_review' => true,
            'fulfilment_review_note' => "A production range is saved, but the text does not say whether it starts at order confirmation or final-specification approval. Ready stock was applied. Complete the structured production fields only if this product is not ready stock.\nThe saved text contains a generic delivery window. It was not copied into the structured fields.",
        ]);
        $studio = Product::factory()->studio()->create([
            'stock' => 3,
            'needs_fulfilment_review' => true,
            'fulfilment_review_note' => 'The saved production text could not be mapped to a minimum, maximum, unit, and start event. Complete it in the product form.',
        ]);
        ProductFulfilmentOriginal::query()->create([
            'product_id' => $shop->id,
            'original' => ['stock' => 4, 'tab_shipping' => $shop->tab_shipping],
            'applied' => ['section_treated_as' => 'shop'],
            'applied_at' => now(),
        ]);

        $counts = app(ProductFulfilmentBackfill::class)->clearResolvedReadyStockFlags();

        $this->assertSame(2, $counts['cleared']);
        $this->assertSame(1, $counts['trimmed']);
        $this->assertSame(0, $counts['kept']);
        $shop->refresh();
        $confirmedAfter->refresh();
        $mixed->refresh();
        $studio->refresh();
        $this->assertFalse($shop->needs_fulfilment_review);
        $this->assertNull($shop->fulfilment_review_note);
        $this->assertSame(4, $shop->stock);
        $this->assertTrue($shop->hide_when_out_of_stock);
        $this->assertStringContainsString('Inspect the crate on delivery.', $shop->tab_shipping);
        $this->assertFalse($confirmedAfter->needs_fulfilment_review);
        $this->assertSame(1, $confirmedAfter->stock);
        $this->assertTrue($mixed->needs_fulfilment_review);
        $this->assertStringContainsString('generic delivery window', $mixed->fulfilment_review_note);
        $this->assertStringNotContainsString('production range is saved', $mixed->fulfilment_review_note);
        $this->assertTrue($studio->needs_fulfilment_review);
        $this->assertSame(3, $studio->stock);
        $original = ProductFulfilmentOriginal::query()->where('product_id', $shop->id)->firstOrFail();
        $this->assertSame(4, $original->original['stock']);
        $this->assertSame(1, ProductFulfilmentOriginal::query()->count());
    }

    public function test_historical_shipping_stays_obsolete_and_refunds_include_packing_once(): void
    {
        $legacy = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Legacy',
            'customer_email' => 'legacy@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'country' => 'India',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'status' => 'paid',
            'payment_method' => 'razorpay',
        ]);
        $this->assertTrue(IndiaDelivery::hasObsoleteShippingCharge($legacy));
        $this->assertNull($legacy->packing_cost);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'country' => 'India',
            'subtotal' => '1000.00',
            'shipping_cost' => '20.00',
            'packing_cost' => '5.00',
            'total' => '1025.00',
            'status' => 'paid',
            'payment_method' => 'razorpay',
            'payment_id' => 'pay_fulfilment',
            'captured_amount_paise' => 102500,
            'fulfilment_snapshot' => ['version' => 1, 'shipping' => '20.00', 'packing' => '5.00'],
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Table',
            'price' => '1000.00',
            'quantity' => 1,
            'total' => '1000.00',
        ]);

        $service = app(OrderRefundService::class);
        $method = new ReflectionMethod($service, 'allocate');
        $full = $method->invoke($service, $order, $order->items()->get(), new RefundIntent(
            'idem-full',
            'full',
            'customer_request',
            null,
            $order->order_number,
            true,
            [],
        ));
        $this->assertSame(102500, $full['amount']);
        $this->assertSame(2000, $full['shipping']);
        $this->assertSame(500, $full['packing']);

        $partial = $method->invoke($service, $order, $order->items()->get(), new RefundIntent(
            'idem-partial',
            'partial',
            'customer_request',
            null,
            null,
            true,
            [$item->id => 1],
        ));
        $this->assertSame(0, $partial['packing']);
        $this->assertSame(100000 + 2000, $partial['amount']);
        $this->assertLessThan(102500, $partial['amount']);
        $this->assertSame('20.00', RefundMoney::formatRupees($full['shipping']));
    }

    public function test_product_page_renders_structured_terms_without_contradicting_ready_stock(): void
    {
        $category = Category::factory()->create([
            'slug' => 'coffee-tables',
            'section' => 'shop',
            'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'structured-table',
            'is_active' => true,
            'availability_mode' => ProductFulfilment::AVAILABILITY_READY,
            'hide_when_out_of_stock' => false,
            'stock' => 0,
            'tab_shipping' => "Estimated production time: 3–4 weeks.\nThe estimated production lead time will be confirmed after your order is placed.\nInspect the crate on delivery.",
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Ready stock.')
            ->assertSee('Shipping within India is included in the displayed price.')
            ->assertSee('Inspect the crate on delivery.')
            ->assertDontSee('Estimated production time: 3–4 weeks.', false)
            ->assertDontSee('confirmed after your order is placed', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function adminPayload(Category $category, array $overrides): array
    {
        return array_merge([
            'category_id' => $category->id,
            'name' => 'Admin Product',
            'slug' => 'admin-product',
            'price' => 1000,
            'stock' => 1,
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'is_active' => '0',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(string $country = 'India'): array
    {
        return [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9876543210',
            'house_building' => '123 Test Building',
            'street' => 'Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'country' => $country,
            'payment_method' => 'razorpay',
            'billing_same_as_shipping' => '1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function protectionFields(): array
    {
        $turnstile = app(TurnstileService::class);

        return [
            'form_loaded_at' => Crypt::encryptString(json_encode([
                'form' => 'international_shipping',
                'loaded_at' => now()->subSeconds(10)->timestamp,
            ])),
            'turnstile_fallback_token' => $turnstile->fallbackToken('international_shipping'),
            'turnstile_unavailable' => '0',
            'cf-turnstile-response' => 'test-turnstile-pass',
            'human_confirmation' => '1',
            'enquiry_intent' => 'general_enquiry',
        ];
    }
}
