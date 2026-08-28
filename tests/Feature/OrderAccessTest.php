<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\OrderAccess;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();
        Http::fake();
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
            'razorpay_order_id' => 'order_test123',
        ], $overrides));
    }

    private function orderOwner(Order $order): User
    {
        return User::factory()->create([
            'email' => $order->customer_email,
            'mobile' => '9999999999',
        ]);
    }

    private function assertStorefrontOrderNotFound($response): void
    {
        $response->assertRedirect(StorefrontRoutes::primaryShopUrl());
        $response->assertSessionHas('error', 'Order not found.');
    }

    public function test_guest_cannot_view_checkout_success_for_foreign_order(): void
    {
        $order = $this->makeOrder(['status' => 'paid']);

        $response = $this->get(route('checkout.success', $order));

        $response->assertRedirect(route('account.login'));
    }

    public function test_guest_can_view_checkout_success_for_session_order(): void
    {
        $order = $this->makeOrder(['status' => 'paid']);
        $user = $this->orderOwner($order);

        $response = $this->actingAs($user)
            ->withSession([OrderAccess::SESSION_KEY => $order->id])
            ->get(route('checkout.success', $order));

        $response->assertOk();
        $response->assertSee($order->order_number);
    }

    public function test_guest_cannot_open_payment_page_for_foreign_order(): void
    {
        $order = $this->makeOrder();

        $response = $this->get(route('checkout.pay', $order));

        $response->assertRedirect(route('account.login'));
    }

    public function test_payment_verify_rejects_mismatched_razorpay_order_id(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        Product::factory()->shop()->create(['category_id' => $category->id]);
        $order = $this->makeOrder();
        $user = $this->orderOwner($order);

        $response = $this->actingAs($user)
            ->withSession([OrderAccess::SESSION_KEY => $order->id])
            ->post(route('checkout.pay.verify', $order), [
                'razorpay_payment_id' => 'pay_test',
                'razorpay_order_id' => 'order_wrong',
                'razorpay_signature' => 'invalid',
            ]);

        $response->assertRedirect(route('checkout.pay', $order));
        $response->assertSessionHas('error');
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_authenticated_owner_can_access_order_by_user_id(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $user->id,
            'customer_email' => $user->email,
            'status' => 'paid',
        ]);

        $this->actingAs($user);
        $this->assertTrue(OrderAccess::canAccess($order));

        $response = $this->get(route('checkout.success', $order));

        $response->assertOk();
        $response->assertSee($order->order_number);
    }

    public function test_foreign_customer_cannot_access_order_by_matching_customer_email(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $order = $this->makeOrder([
            'user_id' => $owner->id,
            'customer_email' => 'checkout-owner@example.com',
            'status' => 'paid',
        ]);
        $intruder = User::factory()->create(['email' => 'checkout-owner@example.com']);

        $this->actingAs($intruder);
        $this->assertFalse(OrderAccess::canAccess($order));

        $this->assertStorefrontOrderNotFound(
            $this->actingAs($intruder)->get(route('checkout.success', $order))
        );
    }

    public function test_foreign_customer_cannot_access_order_after_changing_profile_email_to_checkout_email(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $order = $this->makeOrder([
            'user_id' => $owner->id,
            'customer_email' => 'editable-checkout@example.com',
            'status' => 'paid',
        ]);
        $intruder = User::factory()->create([
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
        ]);

        $this->actingAs($intruder)
            ->from(route('account'))
            ->post(route('account.profile.update'), [
                'name' => 'Intruder',
                'email' => 'editable-checkout@example.com',
                'whatsapp' => $intruder->whatsapp,
                'city' => 'Mumbai',
            ])
            ->assertRedirect(route('account'))
            ->assertSessionHas('success');

        $this->assertSame('editable-checkout@example.com', $intruder->fresh()->email);

        $this->actingAs($intruder->fresh());
        $this->assertFalse(OrderAccess::canAccess($order));

        $this->assertStorefrontOrderNotFound(
            $this->actingAs($intruder->fresh())->get(route('checkout.success', $order))
        );
    }

    public function test_foreign_customer_cannot_access_order_by_matching_phone_suffix(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $owner->id,
            'customer_phone' => '+91 9876543210',
            'status' => 'paid',
        ]);
        $intruder = User::factory()->create(['mobile' => '9876543210']);

        $this->actingAs($intruder);
        $this->assertFalse(OrderAccess::canAccess($order));

        $this->assertStorefrontOrderNotFound(
            $this->actingAs($intruder)->get(route('checkout.success', $order))
        );
    }

    public function test_foreign_customer_cannot_open_owner_success_page(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $owner->id,
            'status' => 'paid',
        ]);
        $intruder = User::factory()->create();

        $this->assertStorefrontOrderNotFound(
            $this->actingAs($intruder)->get(route('checkout.success', $order))
        );
    }

    public function test_foreign_customer_cannot_open_owner_payment_page(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $owner->id]);
        $intruder = User::factory()->create();

        $this->assertStorefrontOrderNotFound(
            $this->actingAs($intruder)->get(route('checkout.pay', $order))
        );
    }

    public function test_foreign_customer_cannot_use_owner_order_through_razorpay_api_endpoints(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder(['user_id' => $owner->id]);
        $intruder = User::factory()->create();

        $create = $this->actingAs($intruder)->postJson(route('api.create-order'), [
            'store_order_id' => $order->id,
        ]);
        $create->assertNotFound();
        $create->assertJson(['message' => 'Order not found.']);

        $verify = $this->actingAs($intruder)->postJson(route('api.verify-payment'), [
            'store_order_id' => $order->id,
            'razorpay_payment_id' => 'pay_foreign',
            'razorpay_order_id' => 'order_test123',
            'razorpay_signature' => 'invalid',
        ]);
        $verify->assertNotFound();
        $verify->assertJson(['message' => 'Order not found.']);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_dashboard_does_not_list_foreign_order_by_email_match(): void
    {
        $owner = User::factory()->create(['email' => 'owner-dash@example.com']);
        $order = $this->makeOrder([
            'user_id' => $owner->id,
            'customer_email' => 'shared-checkout@example.com',
            'order_number' => 'VA-EMAILLEAK',
            'status' => 'paid',
        ]);
        $intruder = User::factory()->create(['email' => 'shared-checkout@example.com']);

        $this->actingAs($intruder)
            ->get(route('account'))
            ->assertOk()
            ->assertDontSee('VA-EMAILLEAK', false)
            ->assertDontSee($order->order_number, false);
    }

    public function test_dashboard_does_not_list_foreign_order_by_phone_match(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $owner->id,
            'customer_phone' => '919876543210',
            'order_number' => 'VA-PHONELEAK',
            'status' => 'paid',
        ]);
        $intruder = User::factory()->create(['mobile' => '9876543210']);

        $this->actingAs($intruder)
            ->get(route('account'))
            ->assertOk()
            ->assertDontSee('VA-PHONELEAK', false)
            ->assertDontSee($order->order_number, false);
    }

    public function test_session_checkout_order_id_cannot_override_foreign_user_id(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $owner->id,
            'status' => 'paid',
        ]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder);
        session([OrderAccess::SESSION_KEY => $order->id]);
        $this->assertFalse(OrderAccess::canAccess($order));

        $this->assertStorefrontOrderNotFound(
            $this->actingAs($intruder)
                ->withSession([OrderAccess::SESSION_KEY => $order->id])
                ->get(route('checkout.success', $order))
        );

        $this->assertStorefrontOrderNotFound(
            $this->actingAs($intruder)
                ->withSession([OrderAccess::SESSION_KEY => $order->id])
                ->get(route('checkout.pay', $order))
        );
    }

    public function test_legacy_null_user_id_order_is_not_granted_through_email_or_phone(): void
    {
        $legacy = $this->makeOrder([
            'user_id' => null,
            'customer_email' => 'legacy@example.com',
            'customer_phone' => '919111111111',
            'status' => 'paid',
        ]);
        $intruder = User::factory()->create([
            'email' => 'legacy@example.com',
            'mobile' => '9111111111',
        ]);

        $this->actingAs($intruder);
        $this->assertFalse(OrderAccess::canAccess($legacy));

        $this->assertStorefrontOrderNotFound(
            $this->actingAs($intruder)->get(route('checkout.success', $legacy))
        );
        $this->assertStorefrontOrderNotFound(
            $this->actingAs($intruder)->get(route('checkout.pay', $legacy))
        );
        $this->actingAs($intruder)
            ->get(route('account'))
            ->assertOk()
            ->assertDontSee($legacy->order_number, false);
    }

    /**
     * Documented policy: a null-user_id legacy order may be accessed only
     * through an exact match of the placing-session checkout_order_id. That
     * session key never grants access when user_id is a foreign non-null value.
     */
    public function test_legacy_null_user_id_order_allows_exact_placing_session(): void
    {
        $legacy = $this->makeOrder([
            'user_id' => null,
            'status' => 'paid',
        ]);
        $placer = User::factory()->create();

        $this->actingAs($placer);
        session([OrderAccess::SESSION_KEY => $legacy->id]);
        $this->assertTrue(OrderAccess::canAccess($legacy));

        $response = $this->actingAs($placer)
            ->withSession([OrderAccess::SESSION_KEY => $legacy->id])
            ->get(route('checkout.success', $legacy));

        $response->assertOk();
        $response->assertSee($legacy->order_number);
    }

    public function test_admin_order_access_remains_unaffected(): void
    {
        $owner = User::factory()->create();
        $order = $this->makeOrder([
            'user_id' => $owner->id,
            'customer_name' => 'Owned Customer',
            'status' => 'paid',
            'admin_notes' => 'Pack with care.',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Owned Customer')
            ->assertSee($order->order_number)
            ->assertSee('Pack with care.');

        $this->actingAsAdmin($admin)
            ->put(route('admin.orders.update', $order), [
                'status' => 'shipped',
                'admin_notes' => 'Dispatched.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame('Dispatched.', $order->fresh()->admin_notes);
    }
}
