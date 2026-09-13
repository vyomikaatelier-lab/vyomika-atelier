<?php

namespace Tests\Feature;

use App\Http\Middleware\CaptureAttribution;
use App\Mail\PaymentSuccessfulMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderPaymentService;
use App\Support\CheckoutCustomer;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ReflectionClass;
use Tests\TestCase;

/**
 * Cart ownership moves to the pending order during the stateful Details
 * handoff. Stateless callback/webhook completion must not touch the cart.
 */
class CheckoutCartHandoffTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rzp_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::preventStrayRequests();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => self::SECRET,
            'services.razorpay.webhook_secret' => 'whsec_test',
            'mail.default' => 'array',
            'mail.from.address' => 'shop@example.com',
            'queue.default' => 'sync',
        ]);
    }

    private function shopProduct(array $overrides = []): Product
    {
        return Product::factory()->shop()->create(array_merge([
            'category_id' => Category::factory()->create(['slug' => 'coffee-tables'])->id,
            'stock' => 10,
            'price' => 5000,
        ], $overrides));
    }

    private function sizedProduct(array $overrides = []): Product
    {
        return Product::factory()->shop()->create(array_merge([
            'category_id' => Category::factory()->create(['slug' => 'door-handles'])->id,
            'stock' => 10,
            'price' => 20000,
            'size_options' => [
                ['label' => 'Small', 'price' => 14000],
                ['label' => 'Large', 'price' => 18000],
            ],
        ], $overrides));
    }

    private function customer(array $overrides = []): User
    {
        return User::factory()->unverified()->create(array_merge([
            'email' => 'handoff@example.com',
            'password' => Hash::make('secret-password'),
            'is_admin' => false,
            'is_active' => true,
        ], $overrides));
    }

    private function checkoutPayload(): array
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

    private function fakeRazorpayOrderCreate(string $id = 'order_handoff'): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response([
                'id' => $id,
                'amount' => 100,
                'currency' => 'INR',
            ], 200),
        ]);
    }

    private function fakeCapturedPayment(Order $order, string $paymentId): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/*' => Http::response([
                'id' => $paymentId,
                'order_id' => (string) $order->razorpay_order_id,
                'amount' => (int) round(((float) $order->total) * 100),
                'currency' => 'INR',
                'status' => 'captured',
            ], 200),
        ]);
    }

    private function placePendingOrder(User $user, Product $product, array $addOverrides = []): Order
    {
        $this->fakeRazorpayOrderCreate();

        $this->actingAs($user)
            ->post(route('cart.add', $product), $this->purchaseInput($addOverrides))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect();

        $order = Order::query()->first();
        $this->assertNotNull($order);

        return $order;
    }

    public function test_cart_persists_through_login_and_details_display(): void
    {
        $product = $this->shopProduct(['name' => 'Handoff Mirror']);
        $user = $this->customer();

        $this->post(route('cart.add', $product), $this->purchaseInput())
            ->assertRedirect();
        $this->get(route('checkout.index'))
            ->assertRedirect(route('account.login'))
            ->assertSessionHas('info', CheckoutCustomer::MSG_SIGN_IN);

        $this->post(route('account.login.email'), [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('checkout.index'));

        $this->assertTrue($this->sessionCartHasProduct($product));
        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Handoff Mirror', false)
            ->assertSee('Shipping details', false);
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Handoff Mirror', false);
    }

    public function test_failed_details_validation_preserves_cart(): void
    {
        $product = $this->shopProduct(['name' => 'Validation Mirror']);
        $user = $this->customer(['email' => 'validate@example.com']);

        $this->actingAs($user)
            ->post(route('cart.add', $product), $this->purchaseInput());

        $this->fakeRazorpayOrderCreate();

        $this->actingAs($user)
            ->post(route('checkout.store'), array_merge($this->checkoutPayload(), [
                'customer_phone' => '123',
            ]))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, Order::query()->count());
        $this->assertTrue($this->sessionCartHasProduct($product));
    }

    public function test_razorpay_order_creation_failure_before_handoff_preserves_cart(): void
    {
        $product = $this->shopProduct(['name' => 'Gateway Fail Mirror']);
        $user = $this->customer(['email' => 'rzp-fail-cart@example.com']);

        $this->actingAs($user)
            ->post(route('cart.add', $product), $this->purchaseInput())
            ->assertRedirect();
        $this->assertTrue($this->sessionCartHasProduct($product));
        $this->assertSame(1, $this->sessionCartLine($product)['quantity'] ?? null);

        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['error' => ['description' => 'gateway down']], 500),
        ]);

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', 'Could not create Razorpay order.');

        $this->assertTrue($this->sessionCartHasProduct($product));
        $this->assertSame(1, $this->sessionCartLine($product)['quantity'] ?? null);
        $this->assertNull(session('buy_now'));

        $order = Order::query()->first();
        if ($order) {
            $this->assertNull($order->razorpay_order_id);
            $this->assertSame('pending', $order->status);
        }
    }

    public function test_successful_pending_order_handoff_consumes_checked_out_cart(): void
    {
        $product = $this->shopProduct(['name' => 'Consumed Mirror']);
        $user = $this->customer(['email' => 'consume@example.com']);

        $order = $this->placePendingOrder($user, $product);

        $this->assertSame('pending', $order->status);
        $this->assertFalse($this->sessionCartHasProduct($product));
        $this->assertEmpty(session('cart', []));
        $this->assertNull(session('buy_now'));
        $this->assertFalse((bool) session('buy_now_intent'));
    }

    public function test_buy_now_handoff_consumes_the_same_canonical_cart(): void
    {
        $product = $this->shopProduct(['name' => 'Buy Now Handoff']);
        $user = $this->customer(['email' => 'buynow-handoff@example.com']);

        $order = $this->placePendingOrder($user, $product, ['buy_now' => 1]);

        $this->assertSame($product->id, $order->items()->value('product_id'));
        $this->assertFalse($this->sessionCartHasProduct($product));
        $this->assertEmpty(session('cart', []));
    }

    public function test_pending_order_retry_works_after_cart_is_consumed(): void
    {
        $product = $this->shopProduct();
        $user = $this->customer(['email' => 'retry-handoff@example.com']);
        $order = $this->placePendingOrder($user, $product);

        $this->assertFalse($this->sessionCartHasProduct($product));

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect(route('checkout.pay', $order));

        $this->assertSame(1, Order::query()->count());
        $this->actingAs($user)
            ->get(route('checkout.pay', $order))
            ->assertOk();
    }

    public function test_purchased_lines_cannot_reappear_as_a_duplicate_checkout(): void
    {
        $product = $this->shopProduct(['name' => 'No Duplicate Mirror']);
        $user = $this->customer(['email' => 'nodup@example.com']);
        $this->placePendingOrder($user, $product);

        $this->actingAs($user)
            ->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', 'Your cart is empty.');

        $this->assertSame(1, Order::query()->count());
    }

    public function test_items_added_after_handoff_survive_sessionful_payment_completion(): void
    {
        $checkedOut = $this->shopProduct(['name' => 'Old Pending Mirror', 'price' => 5000]);
        $later = Product::factory()->shop()->create([
            'category_id' => $checkedOut->category_id,
            'name' => 'New Cart Chair',
            'stock' => 8,
            'price' => 3000,
        ]);
        $user = $this->customer(['email' => 'later-cart@example.com']);
        $order = $this->placePendingOrder($user, $checkedOut);

        $this->actingAs($user)
            ->post(route('cart.add', $later), $this->purchaseInput())
            ->assertRedirect();
        $this->assertTrue($this->sessionCartHasProduct($later));
        $this->assertFalse($this->sessionCartHasProduct($checkedOut));

        $this->fakeCapturedPayment($order, 'pay_handoff');
        app(OrderPaymentService::class)->completeFromGateway(
            $order,
            'pay_handoff',
            (string) $order->razorpay_order_id,
        );

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertTrue($this->sessionCartHasProduct($later));
        $this->assertFalse($this->sessionCartHasProduct($checkedOut));
    }

    public function test_same_variant_added_after_handoff_survives_older_payment(): void
    {
        $product = $this->sizedProduct(['name' => 'Same Variant Handle']);
        $user = $this->customer(['email' => 'same-variant@example.com']);
        $finish = 'black-mirror';
        $size = 'Large';
        $variant = [
            'quantity' => 1,
            'finish_slug' => $finish,
            'size_label' => $size,
        ];

        $this->fakeRazorpayOrderCreate('order_same_variant');
        $this->actingAs($user)
            ->post(route('cart.add', $product), $this->purchaseInput($variant))
            ->assertRedirect();

        $checkedOut = $this->sessionCartLine($product, $size, $finish);
        $this->assertNotNull($checkedOut);
        $this->assertSame(1, $checkedOut['quantity']);
        $this->assertSame($finish, $checkedOut['finish_slug']);
        $this->assertSame($size, $checkedOut['size_label']);

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->checkoutPayload())
            ->assertRedirect();

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame(1, (int) $order->items()->value('quantity'));
        $this->assertNull($this->sessionCartLine($product, $size, $finish));
        $this->assertEmpty(session('cart', []));

        $this->actingAs($user)
            ->post(route('cart.add', $product), $this->purchaseInput([
                'quantity' => 2,
                'finish_slug' => $finish,
                'size_label' => $size,
            ]))
            ->assertRedirect();

        $readded = $this->sessionCartLine($product, $size, $finish);
        $this->assertNotNull($readded);
        $this->assertSame(2, $readded['quantity']);
        $this->assertSame($finish, $readded['finish_slug']);
        $this->assertSame($size, $readded['size_label']);
        $cartBeforePayment = session('cart');
        $this->assertCount(1, $cartBeforePayment);

        $this->fakeCapturedPayment($order, 'pay_same_variant');
        app(OrderPaymentService::class)->completeFromGateway(
            $order,
            'pay_same_variant',
            (string) $order->razorpay_order_id,
        );

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1, (int) $order->fresh()->items()->value('quantity'));
        $this->assertSame($cartBeforePayment, session('cart'));
        $surviving = $this->sessionCartLine($product, $size, $finish);
        $this->assertNotNull($surviving);
        $this->assertSame(2, $surviving['quantity']);
        $this->assertSame($finish, $surviving['finish_slug']);
        $this->assertSame($size, $surviving['size_label']);
        $this->assertCount(1, session('cart', []));
    }

    public function test_stateless_callback_completes_payment_without_cookies_or_cart_access(): void
    {
        $product = $this->shopProduct(['name' => 'Callback Mirror']);
        $later = Product::factory()->shop()->create([
            'category_id' => $product->category_id,
            'name' => 'Post Handoff Lamp',
            'stock' => 6,
            'price' => 2500,
        ]);
        $user = $this->customer(['email' => 'callback-cart@example.com']);
        $order = $this->placePendingOrder($user, $product);

        $this->actingAs($user)->post(route('cart.add', $later), $this->purchaseInput());
        $this->assertTrue($this->sessionCartHasProduct($later));

        config(['session.driver' => 'cookie']);
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');

        $stateful = $this->get(route('cart.index'));
        $sessionCookie = config('session.cookie');
        $stateful->assertCookie($sessionCookie);

        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        $paymentId = 'pay_cb_handoff';
        $razorpayOrderId = (string) $order->razorpay_order_id;
        $this->fakeCapturedPayment($order, $paymentId);

        $callback = $this->post(route('checkout.pay.verify', $order), [
            'razorpay_payment_id' => $paymentId,
            'razorpay_order_id' => $razorpayOrderId,
            'razorpay_signature' => hash_hmac('sha256', $razorpayOrderId.'|'.$paymentId, self::SECRET),
        ]);

        $callback->assertRedirect(route('checkout.success', $order));
        $callback->assertCookieMissing($sessionCookie);
        $this->assertSame([], $callback->headers->getCookies());
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_webhook_completion_requires_no_session(): void
    {
        $product = $this->shopProduct();
        $user = $this->customer(['email' => 'webhook-cart@example.com']);
        $order = $this->placePendingOrder($user, $product);

        $this->flushSession();

        $paymentId = 'pay_wh_handoff';
        $this->fakeCapturedPayment($order, $paymentId);
        $razorpayOrderId = (string) $order->razorpay_order_id;

        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $paymentId,
                        'order_id' => $razorpayOrderId,
                        'status' => 'captured',
                    ],
                ],
            ],
        ];
        $body = json_encode($payload);

        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_test'),
        ], $body)->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame('paid', $order->fresh()->status);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);
    }

    public function test_repeated_callback_and_webhook_pay_exactly_once(): void
    {
        $product = $this->shopProduct();
        $user = $this->customer(['email' => 'once@example.com']);
        $order = $this->placePendingOrder($user, $product);

        $paymentId = 'pay_once_handoff';
        $razorpayOrderId = (string) $order->razorpay_order_id;
        $this->fakeCapturedPayment($order, $paymentId);

        $payments = app(OrderPaymentService::class);
        $payments->completeFromGateway($order, $paymentId, $razorpayOrderId);
        $payments->completeFromGateway($order->fresh(), $paymentId, $razorpayOrderId);

        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $paymentId,
                        'order_id' => $razorpayOrderId,
                        'status' => 'captured',
                    ],
                ],
            ],
        ];
        $body = json_encode($payload);
        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_test'),
        ], $body)->assertOk()->assertJson(['status' => 'already_processed']);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(9, $product->fresh()->stock);
        Mail::assertQueued(PaymentSuccessfulMail::class, 1);
    }

    public function test_order_payment_service_has_no_cart_or_session_dependency(): void
    {
        $reflection = new ReflectionClass(OrderPaymentService::class);
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            $name = $type && method_exists($type, 'getName') ? $type->getName() : '';
            $this->assertNotSame(CartService::class, $name);
        }

        $source = (string) file_get_contents($reflection->getFileName());
        $this->assertStringNotContainsString('CartService', $source);
        $this->assertStringNotContainsString('session(', $source);
        $this->assertStringNotContainsString('->clear(', $source);

        $middleware = app('router')->gatherRouteMiddleware(
            collect(app('router')->getRoutes()->getRoutes())
                ->first(fn ($route) => $route->getName() === 'checkout.pay.verify')
        );
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
    }
}
