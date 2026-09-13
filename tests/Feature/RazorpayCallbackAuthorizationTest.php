<?php

namespace Tests\Feature;

use App\Http\Middleware\CaptureAttribution;
use App\Http\Middleware\SecurityHeaders;
use App\Mail\PaymentSuccessfulMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\RazorpayService;
use App\Support\OrderAccess;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Route;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Razorpay's redirect callback is a cross-site POST. With SameSite=Lax the
 * storefront session cookie is not sent, so these tests deliberately post the
 * callback with no authenticated user and no checkout session key.
 *
 * Every Razorpay environment variable is blank (see phpunit.xml); credentials
 * come from config overrides and the gateway is always faked.
 */
class RazorpayCallbackAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rzp_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('', (string) env('RAZORPAY_KEY_ID'));
        $this->assertSame('', (string) env('RAZORPAY_KEY_SECRET'));
        $this->assertSame('', (string) env('RAZORPAY_WEBHOOK_SECRET'));

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => self::SECRET,
            'services.razorpay.webhook_secret' => 'whsec_test',
            'services.admin_email' => 'admin@example.com',
            'mail.default' => 'array',
            'mail.from.address' => 'shop@example.com',
            'mail.from.name' => 'Test Shop',
            'queue.default' => 'sync',
        ]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'status' => 'pending',
            'payment_method' => 'razorpay',
            'expires_at' => now()->addDay(),
        ], $overrides));
    }

    /**
     * An owned order proves the session cookie is not what authorises the
     * callback: the owner is never signed in during these tests.
     */
    private function makeOwnedOrder(string $razorpayOrderId): Order
    {
        $owner = User::factory()->create();

        return $this->makeOrder([
            'user_id' => $owner->id,
            'razorpay_order_id' => $razorpayOrderId,
        ]);
    }

    private function addShopItem(Order $order, int $stock = 5, int $quantity = 1): Product
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => $stock,
            'price' => 1000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => $quantity,
            'total' => $product->price * $quantity,
        ]);

        return $product;
    }

    private function signature(string $razorpayOrderId, string $paymentId): string
    {
        return hash_hmac('sha256', $razorpayOrderId.'|'.$paymentId, self::SECRET);
    }

    private function fakeCapturedPayment(string $paymentId, string $razorpayOrderId, array $overrides = []): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/*' => Http::response(array_merge([
                'id' => $paymentId,
                'order_id' => $razorpayOrderId,
                'amount' => 119900,
                'currency' => 'INR',
                'status' => 'captured',
            ], $overrides), 200),
        ]);
    }

    /**
     * Posts the callback the way the gateway does: no session, no CSRF token.
     */
    private function postGatewayCallback(Order $order, array $payload)
    {
        return $this->post(route('checkout.pay.verify', $order), $payload);
    }

    private function callbackRoute(): Route
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn (Route $route) => $route->getName() === 'checkout.pay.verify');

        $this->assertNotNull($route, 'The checkout.pay.verify route is missing.');

        return $route;
    }

    private function useCookieSessionDriver(): void
    {
        config(['session.driver' => 'cookie']);
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');
    }

    public function test_gateway_callback_without_a_session_completes_a_captured_payment(): void
    {
        Mail::fake();
        $order = $this->makeOwnedOrder('order_cb_ok');
        $product = $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_ok', 'order_cb_ok');

        $response = $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_ok',
            'razorpay_order_id' => 'order_cb_ok',
            'razorpay_signature' => $this->signature('order_cb_ok', 'pay_cb_ok'),
        ]);

        $response->assertRedirect(route('checkout.success', $order));

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('pay_cb_ok', $fresh->payment_id);
        $this->assertNull($fresh->expires_at);
        $this->assertNotNull($fresh->stock_deducted_at);
        $this->assertSame(4, $product->fresh()->stock);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);
    }

    public function test_gateway_callback_without_a_session_rejects_a_forged_signature(): void
    {
        Http::preventStrayRequests();
        $order = $this->makeOwnedOrder('order_cb_forged');
        $this->addShopItem($order);

        $response = $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_forged',
            'razorpay_order_id' => 'order_cb_forged',
            'razorpay_signature' => 'not-a-real-signature',
        ]);

        $response->assertRedirect(route('checkout.pay', $order));
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->payment_id);
    }

    public function test_gateway_callback_signature_for_another_order_cannot_pay_this_order(): void
    {
        Http::preventStrayRequests();
        $victim = $this->makeOwnedOrder('order_cb_victim');
        $this->addShopItem($victim);
        $attacker = $this->makeOwnedOrder('order_cb_attacker');
        $this->addShopItem($attacker);

        // A signature genuinely issued for the attacker's own Razorpay order.
        $response = $this->postGatewayCallback($victim, [
            'razorpay_payment_id' => 'pay_cb_replay',
            'razorpay_order_id' => 'order_cb_attacker',
            'razorpay_signature' => $this->signature('order_cb_attacker', 'pay_cb_replay'),
        ]);

        $response->assertRedirect(route('checkout.pay', $victim));
        $this->assertSame('pending', $victim->fresh()->status);
        $this->assertSame('pending', $attacker->fresh()->status);
    }

    public function test_gateway_callback_is_rejected_when_the_order_has_no_stored_razorpay_order_id(): void
    {
        Http::preventStrayRequests();
        $order = $this->makeOrder(['razorpay_order_id' => null]);
        $this->addShopItem($order);

        $response = $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_nostored',
            'razorpay_order_id' => 'order_cb_nostored',
            'razorpay_signature' => $this->signature('order_cb_nostored', 'pay_cb_nostored'),
        ]);

        $response->assertRedirect(route('checkout.pay', $order));
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_gateway_callback_is_rejected_when_razorpay_is_not_configured(): void
    {
        Http::preventStrayRequests();
        config(['services.razorpay.key' => '', 'services.razorpay.secret' => '']);

        $order = $this->makeOwnedOrder('order_cb_unconfigured');
        $this->addShopItem($order);

        // With no secret an empty-key HMAC must never be accepted as a signature.
        $response = $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_unconfigured',
            'razorpay_order_id' => 'order_cb_unconfigured',
            'razorpay_signature' => hash_hmac('sha256', 'order_cb_unconfigured|pay_cb_unconfigured', ''),
        ]);

        $response->assertRedirect(route('checkout.pay', $order));
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_signature_verification_fails_when_the_secret_is_missing(): void
    {
        config(['services.razorpay.secret' => '']);

        $razorpay = app(RazorpayService::class);

        $this->assertFalse($razorpay->verifySignature(
            'order_blank',
            'pay_blank',
            hash_hmac('sha256', 'order_blank|pay_blank', '')
        ));
    }

    public function test_gateway_callback_without_a_session_still_requires_a_captured_payment(): void
    {
        $order = $this->makeOwnedOrder('order_cb_auth');
        $product = $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_auth', 'order_cb_auth', ['status' => 'authorized']);

        $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_auth',
            'razorpay_order_id' => 'order_cb_auth',
            'razorpay_signature' => $this->signature('order_cb_auth', 'pay_cb_auth'),
        ]);

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->payment_id);
        $this->assertNull($fresh->stock_deducted_at);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_gateway_callback_amount_mismatch_does_not_mark_paid(): void
    {
        $order = $this->makeOwnedOrder('order_cb_amount');
        $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_amount', 'order_cb_amount', ['amount' => 100]);

        $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_amount',
            'razorpay_order_id' => 'order_cb_amount',
            'razorpay_signature' => $this->signature('order_cb_amount', 'pay_cb_amount'),
        ]);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_repeated_gateway_callback_without_a_session_is_idempotent(): void
    {
        Mail::fake();
        $order = $this->makeOwnedOrder('order_cb_dup');
        $product = $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_dup', 'order_cb_dup');

        $payload = [
            'razorpay_payment_id' => 'pay_cb_dup',
            'razorpay_order_id' => 'order_cb_dup',
            'razorpay_signature' => $this->signature('order_cb_dup', 'pay_cb_dup'),
        ];

        $this->postGatewayCallback($order, $payload);
        $second = $this->postGatewayCallback($order, $payload);

        $second->assertRedirect(route('checkout.success', $order));
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);
    }

    public function test_webhook_then_gateway_callback_pays_the_order_exactly_once(): void
    {
        Mail::fake();
        $order = $this->makeOwnedOrder('order_cb_race');
        $product = $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_race', 'order_cb_race');

        $payload = [
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_cb_race',
                'order_id' => 'order_cb_race',
                'status' => 'captured',
            ]]],
        ];
        $body = json_encode($payload);

        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_test'),
        ], $body)->assertOk();

        $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_race',
            'razorpay_order_id' => 'order_cb_race',
            'razorpay_signature' => $this->signature('order_cb_race', 'pay_cb_race'),
        ])->assertRedirect(route('checkout.success', $order));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);
    }

    public function test_gateway_callback_response_discloses_no_secret_or_payment_identifier(): void
    {
        Mail::fake();
        $order = $this->makeOwnedOrder('order_cb_leak');
        $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_leak', 'order_cb_leak');

        $response = $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_leak',
            'razorpay_order_id' => 'order_cb_leak',
            'razorpay_signature' => $this->signature('order_cb_leak', 'pay_cb_leak'),
        ]);

        $body = $response->getContent();
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('?', $location);
        $this->assertStringNotContainsString(self::SECRET, $body);
        $this->assertStringNotContainsString(self::SECRET, $location);
        $this->assertStringNotContainsString('pay_cb_leak', $body);
        $this->assertStringNotContainsString('pay_cb_leak', $location);
        $this->assertStringNotContainsString('order_cb_leak', $body);
        $this->assertStringNotContainsString('order_cb_leak', $location);
    }

    public function test_owner_with_a_session_still_lands_on_the_confirmation_page(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $owner->id, 'razorpay_order_id' => 'order_cb_owner']);
        $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_owner', 'order_cb_owner');

        $response = $this->actingAs($owner)
            ->withSession([OrderAccess::SESSION_KEY => $order->id])
            ->post(route('checkout.pay.verify', $order), [
                'razorpay_payment_id' => 'pay_cb_owner',
                'razorpay_order_id' => 'order_cb_owner',
                'razorpay_signature' => $this->signature('order_cb_owner', 'pay_cb_owner'),
            ]);

        $response->assertRedirect(route('checkout.success', $order->fresh()));
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_payment_fetch_requests_are_bounded_by_timeouts(): void
    {
        $method = new ReflectionMethod(RazorpayService::class, 'api');

        $options = (fn () => $this->options)->call($method->invoke(app(RazorpayService::class)));

        $this->assertSame(
            (int) config('checkout.razorpay_connect_timeout'),
            (int) ($options['connect_timeout'] ?? 0)
        );
        $this->assertSame(
            (int) config('checkout.razorpay_create_timeout'),
            (int) ($options['timeout'] ?? 0)
        );
    }

    public function test_callback_route_runs_without_any_session_middleware(): void
    {
        // Instantiating the HTTP kernel is what syncs the middleware groups into
        // the router, so the "web" group expands to its concrete classes here.
        app(HttpKernel::class);

        $middleware = app('router')->gatherRouteMiddleware($this->callbackRoute());

        $this->assertNotContains('web', $middleware);

        // StartSession is the only thing that issues or replaces the session
        // cookie. ValidateCsrfToken and ShareErrorsFromSession must go with it:
        // both dereference $request->session() unconditionally.
        foreach ([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            CaptureAttribution::class,
        ] as $excluded) {
            $this->assertNotContains($excluded, $middleware);
        }

        // Route binding and the security headers are preserved.
        $this->assertContains(SubstituteBindings::class, $middleware);
        $this->assertContains(SecurityHeaders::class, $middleware);
    }

    public function test_callback_response_does_not_issue_or_replace_the_session_cookie(): void
    {
        Mail::fake();
        // The array driver never emits a session cookie, which would make this
        // assertion vacuous. Use a persistent driver so a stateful route really
        // does set one, then prove the callback does not.
        $this->useCookieSessionDriver();
        $sessionCookie = config('session.cookie');

        $stateful = $this->get(route('cart.index'));
        $stateful->assertOk();
        $stateful->assertCookie($sessionCookie);
        $this->assertNotNull($stateful->headers->getCookies()[0] ?? null);

        // SameSite=Lax means the gateway POST does not receive the storefront
        // cookie. Clear the test client's jar so this request matches that.
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        $order = $this->makeOwnedOrder('order_cb_cookie');
        $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_cookie', 'order_cb_cookie');

        $callback = $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_cookie',
            'razorpay_order_id' => 'order_cb_cookie',
            'razorpay_signature' => $this->signature('order_cb_cookie', 'pay_cb_cookie'),
        ]);

        $callback->assertRedirect(route('checkout.success', $order));
        $callback->assertCookieMissing($sessionCookie);
        // XSRF-TOKEN is set on every stateful web response regardless of driver,
        // so its absence proves the session/CSRF stack did not run.
        $callback->assertCookieMissing('XSRF-TOKEN');
        $stateful->assertCookie('XSRF-TOKEN');
        $this->assertSame([], $callback->headers->getCookies());
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_cross_site_callback_succeeds_without_receiving_a_session_cookie(): void
    {
        Mail::fake();
        $this->useCookieSessionDriver();

        $this->assertSame([], $this->defaultCookies);
        $this->assertSame([], $this->unencryptedCookies);

        $order = $this->makeOwnedOrder('order_cb_nocookie');
        $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_nocookie', 'order_cb_nocookie');

        $callback = $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_nocookie',
            'razorpay_order_id' => 'order_cb_nocookie',
            'razorpay_signature' => $this->signature('order_cb_nocookie', 'pay_cb_nocookie'),
        ]);

        $callback->assertRedirect(route('checkout.success', $order));
        $this->assertSame([], $callback->headers->getCookies());
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_callback_keeps_security_headers_and_route_binding(): void
    {
        Mail::fake();
        $order = $this->makeOwnedOrder('order_cb_headers');
        $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_headers', 'order_cb_headers');

        $response = $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_headers',
            'razorpay_order_id' => 'order_cb_headers',
            'razorpay_signature' => $this->signature('order_cb_headers', 'pay_cb_headers'),
        ]);

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        // The order was resolved from the URI, so route binding still applies.
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_a_pre_existing_customer_session_still_works_on_the_success_page(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $owner->id, 'razorpay_order_id' => 'order_cb_keep']);
        $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_keep', 'order_cb_keep');

        // The gateway callback arrives with no session and issues no cookie.
        $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_keep',
            'razorpay_order_id' => 'order_cb_keep',
            'razorpay_signature' => $this->signature('order_cb_keep', 'pay_cb_keep'),
        ])->assertRedirect(route('checkout.success', $order));

        // The customer's own session, untouched, follows the same-site redirect.
        $confirmation = $this->actingAs($owner)
            ->withSession([OrderAccess::SESSION_KEY => $order->id])
            ->get(route('checkout.success', $order));

        $confirmation->assertOk();
        $confirmation->assertSee($order->order_number, false);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_anonymous_callback_records_payment_then_uses_the_normal_login_flow(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $owner->id, 'razorpay_order_id' => 'order_cb_anon']);
        $this->addShopItem($order);
        $this->fakeCapturedPayment('pay_cb_anon', 'order_cb_anon');

        $this->postGatewayCallback($order, [
            'razorpay_payment_id' => 'pay_cb_anon',
            'razorpay_order_id' => 'order_cb_anon',
            'razorpay_signature' => $this->signature('order_cb_anon', 'pay_cb_anon'),
        ])->assertRedirect(route('checkout.success', $order));

        $this->assertSame('paid', $order->fresh()->status);

        // An expired or absent session hits the protected success route, which
        // preserves the confirmation as the post-login destination.
        $guest = $this->get(route('checkout.success', $order));
        $guest->assertRedirect(route('account.login'));
        $this->assertSame(route('checkout.success', $order), session('url.intended'));
    }

    public function test_forged_signature_cannot_use_an_authenticated_session_as_a_bypass(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $owner->id, 'razorpay_order_id' => 'order_cb_bypass']);
        $this->addShopItem($order);

        $response = $this->actingAs($owner)
            ->withSession([OrderAccess::SESSION_KEY => $order->id])
            ->post(route('checkout.pay.verify', $order), [
                'razorpay_payment_id' => 'pay_cb_bypass',
                'razorpay_order_id' => 'order_cb_bypass',
                'razorpay_signature' => 'forged',
            ]);

        $response->assertRedirect(route('checkout.pay', $order));
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->payment_id);
    }

    public function test_missing_signature_fields_cannot_use_an_authenticated_session_as_a_bypass(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $owner->id, 'razorpay_order_id' => 'order_cb_nofields']);
        $this->addShopItem($order);

        $response = $this->actingAs($owner)
            ->withSession([OrderAccess::SESSION_KEY => $order->id])
            ->post(route('checkout.pay.verify', $order), []);

        $response->assertRedirect(route('checkout.pay', $order));
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_session_same_site_stays_lax_and_needs_no_environment_change(): void
    {
        $this->assertNull(env('SESSION_SAME_SITE'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_unknown_and_foreign_order_ids_answer_identically(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $foreign = $this->makeOrder(['user_id' => $owner->id, 'razorpay_order_id' => 'order_probe']);
        $intruder = User::factory()->create();

        $unknown = $this->actingAs($intruder)->postJson(route('api.create-order'), [
            'store_order_id' => $foreign->id + 5000,
        ]);
        $foreignResponse = $this->actingAs($intruder)->postJson(route('api.create-order'), [
            'store_order_id' => $foreign->id,
        ]);

        $unknown->assertNotFound()->assertExactJson(['message' => 'Order not found.']);
        $foreignResponse->assertNotFound()->assertExactJson(['message' => 'Order not found.']);

        $unknownVerify = $this->actingAs($intruder)->postJson(route('api.verify-payment'), [
            'store_order_id' => $foreign->id + 5000,
            'razorpay_payment_id' => 'pay_probe',
            'razorpay_order_id' => 'order_probe',
            'razorpay_signature' => 'invalid',
        ]);
        $unknownVerify->assertNotFound()->assertExactJson(['message' => 'Order not found.']);
        $this->assertSame('pending', $foreign->fresh()->status);
    }
}
