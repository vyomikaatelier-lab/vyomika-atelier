<?php

namespace Tests\Feature;

use App\Models\AdminRolePermissionOverride;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminMfa;
use App\Support\AdminPermissionResolver;
use App\Support\AdminRole;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderRefundAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_permission_defaults_and_owner_delegation(): void
    {
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);
        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
        $legacy = User::factory()->admin()->create(['admin_role' => null]);

        $this->assertTrue($owner->hasAdminPermission(AdminRole::ORDERS_REFUND));
        $this->assertFalse($administrator->hasAdminPermission(AdminRole::ORDERS_REFUND));
        $this->assertFalse($manager->hasAdminPermission(AdminRole::ORDERS_REFUND));
        $this->assertFalse($legacy->hasAdminPermission(AdminRole::ORDERS_REFUND));

        AdminRolePermissionOverride::query()->create([
            'admin_role' => AdminRole::ORDER_MANAGER,
            'permission' => AdminRole::ORDERS_REFUND,
            'enabled' => true,
            'updated_by' => $owner->id,
        ]);
        app(AdminPermissionResolver::class)->flush();

        $this->assertTrue($manager->fresh()->hasAdminPermission(AdminRole::ORDERS_REFUND));
    }

    public function test_route_requires_mfa_permission_password_confirmation_and_csrf(): void
    {
        $order = $this->order();
        $route = app('router')->getRoutes()->getByName('admin.orders.refunds.store');
        $middleware = app('router')->gatherRouteMiddleware($route);

        $this->assertContains('admin', $middleware);
        $this->assertContains('admin.permission:orders.refund', $middleware);
        $this->assertContains('throttle:admin-refund', $middleware);
        $retryMiddleware = app('router')->gatherRouteMiddleware(
            app('router')->getRoutes()->getByName('admin.orders.refunds.retry')
        );
        $this->assertContains('admin', $retryMiddleware);
        $this->assertContains('admin.permission:orders.refund', $retryMiddleware);
        $this->assertContains('throttle:admin-refund', $retryMiddleware);
        $this->assertContains(
            ValidateCsrfToken::class,
            app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'],
        );

        $this->post(route('admin.orders.refunds.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.login'));

        $inactive = User::factory()->admin()->disabled()->create(['admin_role' => AdminRole::OWNER]);
        $this->actingAsAdmin($inactive)
            ->post(route('admin.orders.refunds.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.login'));

        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $this->actingAs($owner)
            ->withSession([AdminMfa::SESSION_PENDING => $owner->id])
            ->post(route('admin.orders.refunds.store', $order), $this->payload($order))
            ->assertRedirect(route('admin.mfa.enroll'));

        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
        $this->actingAsAdmin($manager)
            ->post(route('admin.orders.refunds.store', $order), $this->payload($order))
            ->assertForbidden();

        $this->actingAsAdmin($owner)
            ->withSession([
                AdminAccess::SESSION_KEY => true,
                'order_refund_idempotency.'.$order->id => 'idem-auth-test',
            ])
            ->post(route('admin.orders.refunds.store', $order), $this->payload($order, [
                'current_password' => 'wrong-password',
            ]))
            ->assertSessionHasErrors('current_password');

        $this->actingAsAdmin($owner)
            ->withSession([
                AdminAccess::SESSION_KEY => true,
                'order_refund_idempotency.'.$order->id => 'idem-auth-test',
            ])
            ->post(route('admin.orders.refunds.store', $order), $this->payload($order, [
                'order_number_confirmation' => 'VA-WRONG',
            ]))
            ->assertSessionHasErrors('order_number_confirmation');

        $this->app->bind(ValidateCsrfToken::class, function ($app) {
            return new class($app, $app->make('encrypter')) extends ValidateCsrfToken
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.store', $order), $this->payload($order))
            ->assertStatus(419);
    }

    public function test_uncertain_retry_requires_permission_mfa_password_csrf_and_throttle(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.razorpay.com/*' => Http::response(['message' => 'in progress'], 409),
        ]);

        $order = $this->order();
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $refund = $this->uncertainRefund($order, $owner);

        $this->post(route('admin.orders.refunds.retry', [$order, $refund]), [
            'current_password' => 'password',
        ])->assertRedirect(route('admin.login'));

        $this->actingAs($owner)
            ->withSession([AdminMfa::SESSION_PENDING => $owner->id])
            ->post(route('admin.orders.refunds.retry', [$order, $refund]), [
                'current_password' => 'password',
            ])
            ->assertRedirect(route('admin.mfa.enroll'));

        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
        $this->actingAsAdmin($manager)
            ->post(route('admin.orders.refunds.retry', [$order, $refund]), [
                'current_password' => 'password',
            ])
            ->assertForbidden();

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.retry', [$order, $refund]), [
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->app->bind(ValidateCsrfToken::class, function ($app) {
            return new class($app, $app->make('encrypter')) extends ValidateCsrfToken
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.retry', [$order, $refund]), [
                'current_password' => 'password',
            ])
            ->assertStatus(419);
    }

    public function test_refund_retry_route_is_throttled(): void
    {
        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.razorpay.com/*' => Http::response(['message' => 'in progress'], 409),
        ]);

        $order = $this->order();
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $refund = $this->uncertainRefund($order, $owner);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAsAdmin($owner)
                ->post(route('admin.orders.refunds.retry', [$order, $refund]), [
                    'current_password' => 'password',
                ])
                ->assertRedirect();
        }

        $this->actingAsAdmin($owner)
            ->post(route('admin.orders.refunds.retry', [$order, $refund]), [
                'current_password' => 'password',
            ])
            ->assertStatus(429);

        $this->assertSame(5, (int) $refund->fresh()->submit_attempts);
        $this->assertSame(OrderRefund::STATUS_SUBMIT_UNCERTAIN, $refund->fresh()->status);
        $this->assertSame($refund->idempotency_key, $refund->fresh()->idempotency_key);
    }

    public function test_refund_references_are_hidden_from_admins_without_the_refund_permission(): void
    {
        $order = $this->order();
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER, 'staff_id' => 'STF-1']);
        \Illuminate\Support\Facades\DB::table('order_refunds')->insert([
            'order_id' => $order->id,
            'payment_id' => 'pay_refund',
            'idempotency_key' => 'idem-visible-admin',
            'gateway_refund_id' => 'rfnd_admin_only',
            'receipt' => 'rf_admin_only_receipt',
            'amount_paise' => 119900,
            'currency' => 'INR',
            'kind' => 'full',
            'reason_code' => 'customer_request',
            'status' => 'processed',
            'includes_shipping' => true,
            'shipping_amount_paise' => 19900,
            'actor_user_id' => $owner->id,
            'internal_note' => 'Keep this internal',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin($owner)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('rfnd_admin_only', false)
            ->assertSee('Keep this internal', false);

        $manager = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
        $this->actingAsAdmin($manager)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('rfnd_admin_only', false)
            ->assertDontSee('Keep this internal', false);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Order $order, array $extra = []): array
    {
        return array_merge([
            'idempotency_key' => 'idem-auth-test',
            'kind' => 'full',
            'reason_code' => 'customer_request',
            'internal_note' => 'note',
            'current_password' => 'password',
            'order_number_confirmation' => $order->order_number,
        ], $extra);
    }

    private function uncertainRefund(Order $order, User $owner): OrderRefund
    {
        $refund = new OrderRefund;
        $refund->forceFill([
            'order_id' => $order->id,
            'payment_id' => 'pay_refund',
            'idempotency_key' => 'idem-retry-auth',
            'receipt' => 'rf_retry_auth_receipt',
            'amount_paise' => 119900,
            'currency' => 'INR',
            'kind' => 'full',
            'reason_code' => 'customer_request',
            'status' => OrderRefund::STATUS_SUBMIT_UNCERTAIN,
            'includes_shipping' => false,
            'shipping_amount_paise' => 0,
            'actor_user_id' => $owner->id,
            'requested_at' => now(),
            'submitted_at' => now(),
            'submit_attempts' => 0,
        ])->save();

        return $refund;
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'VA-AUTHREF',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'status' => 'paid',
            'payment_method' => 'razorpay',
            'payment_id' => 'pay_refund',
            'razorpay_order_id' => 'order_refund',
            'stock_deducted_at' => now(),
        ]);
    }
}
