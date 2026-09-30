<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\TurnstileService;
use App\Support\IndiaDelivery;
use App\Support\OrderAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class IndiaInclusiveShippingTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'services.razorpay.webhook_secret' => 'whsec_test',
            'services.admin_email' => 'studio@example.com',
            'form_protection.turnstile.skip_verification' => false,
            'form_protection.turnstile.testing_bypass_token' => 'test-turnstile-pass',
            'form_protection.turnstile.require_manual_confirmation' => true,
        ]);
    }

    public function test_indian_shipping_is_included_below_and_above_the_old_threshold(): void
    {
        $below = $this->placeIndiaOrder(1000, [
            'shipping_cost' => 199,
            'total' => 1,
            'unit_price' => 1,
            'price' => 1,
            'eligible_for_payment' => '1',
        ]);

        $this->assertSame('India', $below->country);
        $this->assertEquals(0, (float) $below->shipping_cost);
        $this->assertEquals(1000, (float) $below->subtotal);
        $this->assertEquals(1000, (float) $below->total);
        $this->assertEquals(1000, (float) $below->items()->value('price'));
        $this->assertSame(100000, $this->gatewayAmounts[0]);

        $above = $this->placeIndiaOrder(6000, [
            'shipping_cost' => 199,
            'total' => 6199,
        ]);

        $this->assertEquals(0, (float) $above->shipping_cost);
        $this->assertEquals(6000, (float) $above->total);
        $this->assertSame(600000, $this->gatewayAmounts[1]);
        $this->assertSame(2, Order::query()->count());
    }

    public function test_typed_india_under_other_is_an_enquiry_not_a_payment(): void
    {
        Http::preventStrayRequests();

        [$user, $product] = $this->shopperWithProduct(2500, 'Typed India Shelf');

        $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->post(route('checkout.store'), array_merge($this->addressPayload(), [
                'country' => 'Other',
                'country_other' => 'India',
                'shipping_cost' => 0,
                'total' => 2500,
            ], $this->protectionFields()))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('success', IndiaDelivery::ENQUIRY_SAVED);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(5, $product->fresh()->stock);
        Http::assertNothingSent();

        $lead = Lead::query()->firstOrFail();
        $this->assertSame('international_shipping', $lead->type);
        $this->assertFalse($lead->metadata['payment_started']);
        $this->assertSame('India', $lead->metadata['destination']['country']);
    }

    public function test_international_enquiry_persists_for_admin_when_mail_fails(): void
    {
        Http::preventStrayRequests();
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP 550'));

        [$user, $product] = $this->shopperWithProduct(4200, 'Brass Partition');

        $this->actingAs($user)
            ->withSession($this->cartSession($product, 2))
            ->post(route('checkout.store'), array_merge($this->addressPayload(), [
                'country' => 'United Kingdom',
                'state' => 'England',
                'city' => 'London',
                'pincode' => 'SW1A 1AA',
                'customer_phone' => '02079460958',
                'product_name' => 'Tampered Product',
                'quantity' => 99,
                'shipping_cost' => 199,
                'total' => 1,
                'unit_price' => 1,
            ], $this->protectionFields()))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('success', IndiaDelivery::ENQUIRY_SAVED);

        $this->assertStringNotContainsString('email', strtolower(IndiaDelivery::ENQUIRY_SAVED));

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Brass Partition', false)
            ->assertSee('London, United Kingdom', false)
            ->assertSee(IndiaDelivery::MERCHANDISE_SUBTOTAL, false)
            ->assertSee(IndiaDelivery::SHIPPING_QUOTED, false);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(5, $product->fresh()->stock);
        Http::assertNothingSent();

        $lead = Lead::query()->firstOrFail();
        $this->assertSame('international_shipping', $lead->type);
        $this->assertSame('international_shipping', $lead->enquiry_type);
        $this->assertTrue($lead->metadata['international_shipping_enquiry']);
        $this->assertFalse($lead->metadata['payment_started']);
        $this->assertSame('United Kingdom', $lead->metadata['destination']['country']);
        $this->assertSame($product->id, $lead->metadata['lines'][0]['product_id']);
        $this->assertSame('Brass Partition', $lead->metadata['lines'][0]['name']);
        $this->assertSame(2, $lead->metadata['lines'][0]['quantity']);
        $this->assertEquals(4200, (float) $lead->metadata['lines'][0]['unit_price']);
        $this->assertStringContainsString('Brass Partition', $lead->message);
        $this->assertStringNotContainsString('Tampered Product', $lead->message);
        $this->assertStringContainsString('import-duty', $lead->message);

        $this->actingAsAdmin()
            ->get(route('admin.leads.index', ['enquiry_type' => 'international_shipping']))
            ->assertOk()
            ->assertSee('jane@example.com', false);

        $this->actingAsAdmin()
            ->get(route('admin.leads.show', $lead))
            ->assertOk()
            ->assertSee('Brass Partition', false)
            ->assertSee('No online payment was taken.', false);
    }

    public function test_international_requests_cannot_start_or_resume_payment(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $blocked = $this->pendingOrder($user, [
            'country' => 'United States',
            'razorpay_order_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('checkout.pay', $blocked))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', IndiaDelivery::PAYMENT_BLOCKED);
        $this->assertNull($blocked->fresh()->razorpay_order_id);

        $this->actingAs($user)
            ->postJson(route('api.create-order'), ['store_order_id' => $blocked->id])
            ->assertStatus(422)
            ->assertJson(['message' => IndiaDelivery::PAYMENT_BLOCKED]);
        $this->assertNull($blocked->fresh()->razorpay_order_id);

        $this->actingAs($user)
            ->post(route('checkout.store'), [])
            ->assertRedirect(route('cart.index'));
        $this->assertSame(1, Order::query()->count());

        [$shopper, $product] = $this->shopperWithProduct(1800, 'India Cabinet');
        $foreign = $this->pendingOrder($shopper, [
            'country' => 'United States',
            'razorpay_order_id' => null,
        ]);

        $this->fakeGatewayOrders();
        $created = $this->placeIndiaOrderFor($shopper, $product, 1800);
        $this->assertNotSame($foreign->id, $created->id);
        $this->assertSame('India', $created->country);
        $this->assertNull($foreign->fresh()->razorpay_order_id);
        $this->assertNotNull($created->razorpay_order_id);

        $started = $this->pendingOrder($shopper, [
            'country' => 'United States',
            'razorpay_order_id' => 'order_already_started',
            'shipping_cost' => 199,
            'total' => 1199,
            'expires_at' => now()->addDay(),
        ]);

        Http::preventStrayRequests();
        Http::fake(function () {
            throw new \RuntimeException('A stored gateway id must not be reused for a non-Indian order.');
        });

        $this->actingAs($shopper)
            ->get(route('checkout.pay', $started))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', IndiaDelivery::PAYMENT_BLOCKED)
            ->assertSessionMissing('resume_payment_url');

        $refused = $this->actingAs($shopper)
            ->postJson(route('api.create-order'), ['store_order_id' => $started->id]);
        $refused->assertStatus(422)->assertJson(['message' => IndiaDelivery::PAYMENT_BLOCKED]);
        $this->assertStringNotContainsString('order_already_started', $refused->getContent());

        $this->actingAs($shopper)
            ->post(route('checkout.store'), [])
            ->assertRedirect(route('cart.index'))
            ->assertSessionMissing('resume_payment_url');

        $this->assertSame('order_already_started', $started->fresh()->razorpay_order_id);
        $this->assertSame('United States', $started->fresh()->country);
        $this->assertSame('pending', $started->fresh()->status);
        $this->assertEquals(199, (float) $started->fresh()->shipping_cost);
        $this->assertEquals(1199, (float) $started->fresh()->total);

        Http::swap(new \Illuminate\Http\Client\Factory);
        $this->fakeGatewayOrders();
        $continued = $this->placeIndiaOrderFor($shopper, $product, 1800);
        $this->assertNotSame($started->id, $continued->id);
        $this->assertNotNull($continued->razorpay_order_id);
        $this->assertSame('order_already_started', $started->fresh()->razorpay_order_id);
        $this->assertNull(session('resume_payment_url'));
    }

    public function test_untouched_indian_orders_with_obsolete_shipping_are_retired(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $pay = $this->staleIndianOrder($user);
        $before = $this->staleSnapshot($pay);
        $this->actingAs($user)->get(route('checkout.pay', $pay))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', IndiaDelivery::STALE_ORDER_REFRESH);
        $this->actingAs($user)->get(route('checkout.success', $pay))
            ->assertRedirect(route('checkout.pay', $pay));
        $this->actingAs($user)->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'));
        $this->actingAs($user)->get(route('checkout.pay', $pay))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', IndiaDelivery::STALE_ORDER_REFRESH);
        $this->assertSame($before, $this->staleSnapshot($pay));

        $apiOrder = $this->staleIndianOrder($user);
        $apiBefore = $this->staleSnapshot($apiOrder);
        $api = $this->actingAs($user)->postJson(route('api.create-order'), ['store_order_id' => $apiOrder->id]);
        $api->assertStatus(422)->assertJson(['message' => IndiaDelivery::STALE_ORDER_REFRESH]);
        $this->assertStringNotContainsString('order_', $api->getContent());
        $this->assertSame($apiBefore, $this->staleSnapshot($apiOrder));

        $resume = $this->staleIndianOrder($user);
        $resumeBefore = $this->staleSnapshot($resume);
        $this->actingAs($user)
            ->post(route('checkout.store'), [])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', IndiaDelivery::STALE_ORDER_REFRESH)
            ->assertSessionMissing('resume_payment_url');
        $this->assertSame($resumeBefore, $this->staleSnapshot($resume));

        [$shopper, $product] = $this->shopperWithProduct(2500, 'Fresh India Shelf');
        $checkout = $this->staleIndianOrder($shopper);
        Http::swap(new \Illuminate\Http\Client\Factory);
        $this->fakeGatewayOrders();
        $this->actingAs($shopper)
            ->withSession($this->cartSession($product))
            ->post(route('checkout.store'), $this->addressPayload())
            ->assertRedirect()
            ->assertSessionHas('info', IndiaDelivery::STALE_ORDER_RETIRED)
            ->assertSessionMissing('resume_payment_url');

        $this->assertRetiredWithoutGateway($checkout);
        $fresh = Order::query()->where('user_id', $shopper->id)->where('status', 'pending')->firstOrFail();
        $this->assertNotSame($checkout->id, $fresh->id);
        $this->assertEquals(0, (float) $fresh->shipping_cost);
        $this->assertEquals(2500, (float) $fresh->total);
        $this->assertNotNull($fresh->razorpay_order_id);
        $this->assertSame(250000, $this->gatewayAmounts[array_key_last($this->gatewayAmounts)] ?? 0);
    }

    public function test_another_customer_cannot_retire_a_stale_order(): void
    {
        Http::preventStrayRequests();

        $owner = User::factory()->create();
        $order = $this->staleIndianOrder($owner);
        $before = $this->staleSnapshot($order);
        [$intruder, $product] = $this->shopperWithProduct(900, 'Intruder Shelf');

        $this->actingAs($intruder)
            ->get(route('checkout.pay', $order))
            ->assertRedirect();
        $this->actingAs($intruder)
            ->postJson(route('api.create-order'), ['store_order_id' => $order->id])
            ->assertNotFound();
        $this->fakeGatewayOrders();
        $this->actingAs($intruder)
            ->withSession($this->cartSession($product))
            ->post(route('checkout.store'), $this->addressPayload())
            ->assertRedirect();

        $this->assertSame($before, $this->staleSnapshot($order));
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_checkout_post_does_not_retire_financially_evidenced_orders(): void
    {
        Http::preventStrayRequests();
        Http::fake(function () {
            throw new \RuntimeException('A second gateway order must not be created.');
        });

        $cases = [
            'gateway' => ['razorpay_order_id' => 'order_evidence_gateway'],
            'payment' => ['payment_id' => 'pay_evidence'],
            'stock' => ['stock_deducted_at' => now()],
            'reconciliation' => ['reconciliation_reason' => 'amount_mismatch'],
            'refund' => ['refund_status' => 'pending'],
        ];

        foreach ($cases as $name => $overrides) {
            [$user, $product] = $this->shopperWithProduct(1600, 'Evidence '.$name);
            $order = $this->staleIndianOrder($user, $overrides);
            if ($name === 'refund') {
                DB::table('order_refunds')->insert([
                    'order_id' => $order->id,
                    'payment_id' => 'pay_evidence_refund',
                    'idempotency_key' => substr(hash('sha256', $name), 0, 32),
                    'receipt' => 'rcpt_'.$name,
                    'amount_paise' => 100,
                    'currency' => 'INR',
                    'kind' => 'partial',
                    'reason_code' => 'customer_request',
                    'status' => 'pending',
                    'includes_shipping' => 0,
                    'shipping_amount_paise' => 0,
                    'actor_user_id' => $user->id,
                    'requested_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $before = $this->staleSnapshot($order);

            $this->actingAs($user)
                ->withSession($this->cartSession($product))
                ->post(route('checkout.store'), $this->addressPayload())
                ->assertRedirect(route('checkout.index'))
                ->assertSessionHas('error', IndiaDelivery::STALE_SHIPPING_SUPPORT)
                ->assertSessionMissing('resume_payment_url');

            $this->assertSame($before, $this->staleSnapshot($order), $name);
            $this->assertSame(1, Order::query()->where('user_id', $user->id)->count(), $name);
        }
    }

    public function test_summary_labels_follow_the_selected_destination_without_javascript(): void
    {
        [$user, $product] = $this->shopperWithProduct(3200, 'Labelled Screen');
        \App\Models\CustomerAddress::create([
            'user_id' => $user->id,
            'name' => 'Jane Doe',
            'phone' => '9876543210',
            'email' => $user->email,
            'address_line1' => '10 King Street',
            'city' => 'London',
            'state' => 'England',
            'pincode' => 'SW1A 1AA',
            'country' => 'United Kingdom',
            'is_default' => true,
        ]);

        $international = $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->get(route('checkout.index'));
        $international->assertOk();
        $international->assertSee(IndiaDelivery::SHIPPING_QUOTED, false);
        $international->assertSee(IndiaDelivery::MERCHANDISE_SUBTOTAL, false);
        $international->assertSee(IndiaDelivery::ENQUIRY_HINT, false);
        $international->assertSee('>Save shipping enquiry</button>', false);
        $international->assertSee('London', false);
        $international->assertSee('Labelled Screen', false);
        $international->assertDontSee('>Continue to Payment</button>', false);
        $this->assertMatchesRegularExpression('/data-payable-total[^>]*hidden/', $international->getContent());

        config(['checkout.payments_enabled' => false]);
        $unavailable = $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->get(route('checkout.index'));
        $unavailable->assertOk();
        $unavailable->assertSee(IndiaDelivery::SHIPPING_QUOTED, false);
        $unavailable->assertSee('>Save shipping enquiry</button>', false);
        $unavailable->assertDontSee('>Checkout unavailable</button>', false);

        $india = User::factory()->create();
        $indiaProduct = Product::factory()->shop()->create([
            'category_id' => Category::factory()->create()->id,
            'name' => 'India Label Shelf',
            'price' => 2100,
            'stock' => 3,
        ]);
        $indiaCheckout = $this->actingAs($india)
            ->withSession($this->cartSession($indiaProduct))
            ->get(route('checkout.index'));
        $indiaCheckout->assertOk();
        $indiaCheckout->assertSee(\App\Support\CheckoutPayments::UNAVAILABLE_MESSAGE, false);
        $indiaCheckout->assertSee('>Checkout unavailable</button>', false);
        $indiaCheckout->assertSee('data-subtotal-label>Subtotal', false);
        $indiaCheckout->assertSee('data-shipping-label>Shipping included', false);
        $indiaCheckout->assertDontSee('>Save shipping enquiry</button>', false);
        $indiaCheckout->assertDontSee('data-subtotal-label>'.IndiaDelivery::MERCHANDISE_SUBTOTAL, false);
    }

    public function test_gateway_backed_obsolete_indian_shipping_is_not_rewritten(): void
    {
        Http::preventStrayRequests();
        Http::fake(function () {
            throw new \RuntimeException('A second gateway order must not be created.');
        });

        $user = User::factory()->create();
        $order = $this->staleIndianOrder($user, ['razorpay_order_id' => 'order_stale_keep']);
        $before = $order->only(['shipping_cost', 'subtotal', 'total', 'razorpay_order_id', 'status']);

        $this->actingAs($user)
            ->get(route('checkout.pay', $order))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', IndiaDelivery::STALE_SHIPPING_SUPPORT)
            ->assertSessionMissing('resume_payment_url');

        $api = $this->actingAs($user)->postJson(route('api.create-order'), ['store_order_id' => $order->id]);
        $api->assertStatus(422)->assertJson(['message' => IndiaDelivery::STALE_SHIPPING_SUPPORT]);
        $this->assertStringNotContainsString('order_stale_keep', $api->getContent());

        $this->actingAs($user)
            ->post(route('checkout.store'), [])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', IndiaDelivery::STALE_SHIPPING_SUPPORT)
            ->assertSessionMissing('resume_payment_url');

        [$shopper, $product] = $this->shopperWithProduct(1800, 'Blocked India Shelf');
        $backed = $this->staleIndianOrder($shopper, ['razorpay_order_id' => 'order_stale_checkout']);
        $this->actingAs($shopper)
            ->withSession($this->cartSession($product))
            ->post(route('checkout.store'), $this->addressPayload())
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', IndiaDelivery::STALE_SHIPPING_SUPPORT)
            ->assertSessionMissing('resume_payment_url');

        $this->assertSame($before, $order->fresh()->only(array_keys($before)));
        $this->assertSame('pending', $backed->fresh()->status);
        $this->assertSame('order_stale_checkout', $backed->fresh()->razorpay_order_id);
        $this->assertEquals(199, (float) $backed->fresh()->shipping_cost);
        $this->assertEquals(1199, (float) $backed->fresh()->total);
        $this->assertSame(1, $backed->items()->count());
        $this->assertSame(1, Order::query()->where('user_id', $shopper->id)->count());
    }

    public function test_existing_callback_webhook_and_refund_ignore_destination(): void
    {
        $captured = $this->pendingOrder(User::factory()->create(), [
            'country' => 'United States',
            'razorpay_order_id' => 'order_us_capture',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
        ]);
        $product = $this->addItem($captured, 4);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();
            $payments = [
                'pay_us_capture' => 'order_us_capture',
                'pay_de_callback' => 'order_de_callback',
            ];

            foreach ($payments as $paymentId => $razorpayOrderId) {
                if (str_ends_with($url, '/payments/'.$paymentId)) {
                    return Http::response([
                        'id' => $paymentId,
                        'order_id' => $razorpayOrderId,
                        'amount' => 119900,
                        'currency' => 'INR',
                        'status' => 'captured',
                    ], 200);
                }
            }

            throw new \RuntimeException('Unexpected request '.$url);
        });

        $this->postWebhook([
            'id' => 'pay_us_capture',
            'order_id' => 'order_us_capture',
            'status' => 'captured',
            'amount' => 119900,
            'currency' => 'INR',
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame('paid', $captured->fresh()->status);
        $this->assertSame('United States', $captured->fresh()->country);
        $this->assertEquals(199, (float) $captured->fresh()->shipping_cost);
        $this->assertEquals(1199, (float) $captured->fresh()->total);
        $this->assertSame(3, $product->fresh()->stock);

        $callbackOrder = $this->pendingOrder(User::factory()->create(), [
            'country' => 'Germany',
            'razorpay_order_id' => 'order_de_callback',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
        ]);
        $callbackProduct = $this->addItem($callbackOrder, 2);
        $signature = hash_hmac('sha256', 'order_de_callback|pay_de_callback', 'rzp_test_secret');

        $this->post(route('checkout.pay.verify', $callbackOrder), [
            'razorpay_payment_id' => 'pay_de_callback',
            'razorpay_order_id' => 'order_de_callback',
            'razorpay_signature' => $signature,
        ])->assertRedirect(route('checkout.success', $callbackOrder));

        $this->assertSame('paid', $callbackOrder->fresh()->status);
        $this->assertSame('Germany', $callbackOrder->fresh()->country);
        $this->assertEquals(1199, (float) $callbackOrder->fresh()->total);
        $this->assertSame(1, $callbackProduct->fresh()->stock);

        $refundOrder = $this->pendingOrder(User::factory()->create(), [
            'country' => 'United States',
            'status' => 'paid',
            'payment_id' => 'pay_us_refund',
            'razorpay_order_id' => 'order_us_refund',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'captured_amount_paise' => 119900,
            'stock_deducted_at' => now(),
            'expires_at' => null,
        ]);
        $admin = User::factory()->admin()->create();
        DB::table('order_refunds')->insert([
            'order_id' => $refundOrder->id,
            'payment_id' => 'pay_us_refund',
            'idempotency_key' => str_repeat('b', 32),
            'receipt' => 'rcpt_us_refund_1',
            'amount_paise' => 119900,
            'currency' => 'INR',
            'kind' => 'full',
            'reason_code' => 'customer_request',
            'status' => 'pending',
            'includes_shipping' => 1,
            'shipping_amount_paise' => 19900,
            'actor_user_id' => $admin->id,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postRefundWebhook([
            'id' => 'rfnd_us_1',
            'payment_id' => 'pay_us_refund',
            'amount' => 119900,
            'currency' => 'INR',
            'status' => 'processed',
            'receipt' => 'rcpt_us_refund_1',
        ])->assertOk();

        $this->assertSame('processed', DB::table('order_refunds')->where('order_id', $refundOrder->id)->value('status'));
        $this->assertSame('United States', $refundOrder->fresh()->country);
        $this->assertEquals(199, (float) $refundOrder->fresh()->shipping_cost);
        $this->assertEquals(1199, (float) $refundOrder->fresh()->total);
    }

    public function test_customer_wording_matches_the_approved_rules(): void
    {
        [$user, $product] = $this->shopperWithProduct(1500, 'Wording Shelf');

        $checkout = $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->get(route('checkout.index'));

        $checkout->assertOk();
        $checkout->assertSee(IndiaDelivery::CUSTOMER_NOTE, false);
        $checkout->assertSee('Shipping included', false);
        $checkout->assertSee('Razorpay', false);
        $checkout->assertDontSee('PayPal', false);
        $checkout->assertDontSee('Worldwide delivery', false);
        $checkout->assertDontSee('3–4 weeks', false);
        $checkout->assertDontSee('₹199', false);

        $cart = $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->get(route('cart.index'));
        $cart->assertOk();
        $cart->assertSee(IndiaDelivery::CUSTOMER_NOTE, false);
        $cart->assertSee('Shipping included', false);
        $cart->assertDontSee('PayPal', false);
        $cart->assertDontSee('3–4 weeks', false);

        $order = $this->pendingOrder($user, ['country' => 'India']);
        $pay = $this->actingAs($user)->get(route('checkout.pay', $order));
        $pay->assertOk();
        $pay->assertSee(IndiaDelivery::CUSTOMER_NOTE, false);
        $pay->assertSee('Razorpay', false);
        $pay->assertDontSee('PayPal', false);

        $policy = $this->get(route('legal.shipping'));
        $policy->assertOk();
        $policy->assertSee('Shipping within India is included in the displayed product price.', false);
        $policy->assertSee('There is no minimum order for that included shipping.', false);
        $policy->assertSee('The website does not calculate an international shipping charge.', false);
        $policy->assertSee('estimated 5 to 12 business days', false);
        $policy->assertSee('estimated 15 to 35 business days', false);
        $policy->assertDontSee('3–4 weeks', false);

        config(['checkout.payments_enabled' => false]);
        $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Checkout is temporarily unavailable', false)
            ->assertSee(IndiaDelivery::CUSTOMER_NOTE, false)
            ->assertDontSee('rzp_test_key', false);

        $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->post(route('checkout.store'), $this->addressPayload())
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', \App\Support\CheckoutPayments::UNAVAILABLE_MESSAGE);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, Lead::query()->count());

        Http::preventStrayRequests();
        $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->post(route('checkout.store'), array_merge($this->addressPayload(), [
                'country' => 'United Kingdom',
                'state' => 'England',
                'city' => 'London',
                'pincode' => 'SW1A 1AA',
                'customer_phone' => '02079460958',
            ], $this->protectionFields()))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('success', IndiaDelivery::ENQUIRY_SAVED);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Lead::query()->where('type', 'international_shipping')->count());
        Http::assertNothingSent();
    }

    public function test_checkout_and_daily_summary_defaults_remain_false(): void
    {
        $checkout = (string) file_get_contents(config_path('checkout.php'));
        $leads = (string) file_get_contents(config_path('leads.php'));

        $this->assertMatchesRegularExpression(
            "/'payments_enabled'\\s*=>\\s*filter_var\\(env\\('CHECKOUT_PAYMENTS_ENABLED',\\s*false\\),\\s*FILTER_VALIDATE_BOOLEAN\\)/",
            $checkout
        );
        $this->assertStringContainsString(
            "filter_var(env('LEADS_DAILY_SUMMARY_ENABLED', false), FILTER_VALIDATE_BOOLEAN)",
            $leads
        );
    }

    /**
     * @param  array<string, mixed>  $tamper
     */
    private function placeIndiaOrder(int $price, array $tamper = []): Order
    {
        [$user, $product] = $this->shopperWithProduct($price, 'India Piece '.$price);
        $this->fakeGatewayOrders();

        return $this->placeIndiaOrderFor($user, $product, $price, $tamper);
    }

    /**
     * @param  array<string, mixed>  $tamper
     */
    private function placeIndiaOrderFor(User $user, Product $product, int $price, array $tamper = []): Order
    {
        $before = Order::query()->where('user_id', $user->id)->where('country', 'India')->count();

        $this->actingAs($user)
            ->withSession($this->cartSession($product))
            ->post(route('checkout.store'), array_merge($this->addressPayload(), $tamper))
            ->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->where('country', 'India')->latest('id')->firstOrFail();
        $this->assertSame($before + 1, Order::query()->where('user_id', $user->id)->where('country', 'India')->count());
        $this->assertEquals($price, (float) $order->total);

        return $order;
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function shopperWithProduct(int $price, string $name): array
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => $name,
            'price' => $price,
            'stock' => 5,
        ]);

        return [$user, $product];
    }

    /**
     * @return array<string, mixed>
     */
    private function cartSession(Product $product, int $quantity = 1): array
    {
        return [
            'cart' => [
                $product->id => [
                    'quantity' => $quantity,
                    'finish_slug' => null,
                    'finish_name' => null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(): array
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
            'country' => 'India',
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

    /** @var list<int> */
    private array $gatewayAmounts = [];

    private function fakeGatewayOrders(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (! str_contains($request->url(), 'api.razorpay.com/v1/orders')) {
                throw new \RuntimeException('Unexpected request '.$request->url());
            }

            $amount = (int) ($request->data()['amount'] ?? -1);
            $this->gatewayAmounts[] = $amount;

            return Http::response([
                'id' => 'order_local_'.count($this->gatewayAmounts),
                'amount' => $amount,
                'currency' => 'INR',
            ], 200);
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function staleIndianOrder(User $user, array $overrides = []): Order
    {
        $order = $this->pendingOrder($user, array_merge([
            'country' => 'India',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'razorpay_order_id' => null,
            'payment_id' => null,
            'stock_deducted_at' => null,
        ], $overrides));
        $this->addItem($order, 4);

        return $order->fresh('items');
    }

    /**
     * @return array<string, mixed>
     */
    private function staleSnapshot(Order $order): array
    {
        $fresh = $order->fresh();

        return [
            'status' => $fresh->status,
            'shipping_cost' => (string) $fresh->shipping_cost,
            'subtotal' => (string) $fresh->subtotal,
            'total' => (string) $fresh->total,
            'razorpay_order_id' => $fresh->razorpay_order_id,
            'payment_id' => $fresh->payment_id,
            'stock_deducted_at' => $fresh->stock_deducted_at?->toDateTimeString(),
            'refund_status' => $fresh->refund_status,
            'reconciliation_reason' => $fresh->reconciliation_reason,
            'expires_at' => $fresh->expires_at?->toDateTimeString(),
            'items' => $fresh->items()->count(),
        ];
    }

    private function assertRetiredWithoutGateway(Order $order): void
    {
        $fresh = $order->fresh('items');
        $this->assertNotNull($fresh);
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNull($fresh->razorpay_order_id);
        $this->assertNull($fresh->payment_id);
        $this->assertEquals(199, (float) $fresh->shipping_cost);
        $this->assertEquals(1000, (float) $fresh->subtotal);
        $this->assertEquals(1199, (float) $fresh->total);
        $this->assertSame(1, $fresh->items->count());
        $this->assertNull($fresh->expires_at);
    }

    private function pendingOrder(User $user, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $user->id,
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => $user->email,
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'country' => 'India',
            'subtotal' => 1000,
            'shipping_cost' => 0,
            'total' => 1000,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'expires_at' => now()->addDay(),
        ], $overrides));
    }

    private function addItem(Order $order, int $stock): Product
    {
        $category = Category::factory()->create();
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => $stock,
            'price' => 1000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
        ]);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function postWebhook(array $payment)
    {
        $body = json_encode([
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => $payment,
                ],
            ],
        ]);

        return $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', (string) $body, 'whsec_test'),
        ], (string) $body);
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    private function postRefundWebhook(array $refund)
    {
        $body = json_encode([
            'event' => 'refund.processed',
            'payload' => [
                'refund' => [
                    'entity' => $refund,
                ],
            ],
        ]);

        return $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', (string) $body, 'whsec_test'),
        ], (string) $body);
    }
}
