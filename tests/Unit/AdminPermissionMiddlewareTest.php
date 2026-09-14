<?php

namespace Tests\Unit;

use App\Http\Middleware\AdminPermissionMiddleware;
use App\Models\User;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_staff_can_continue(): void
    {
        $admin = User::factory()->admin()->create(['admin_role' => AdminRole::ORDER_MANAGER]);
        $this->actingAs($admin);
        $request = Request::create('/admin/orders', 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = app(AdminPermissionMiddleware::class)->handle(
            $request,
            fn () => response('allowed'),
            AdminRole::ORDERS_VIEW,
        );

        $this->assertSame('allowed', $response->getContent());
    }

    public function test_unauthorized_staff_is_denied_with_403(): void
    {
        $admin = User::factory()->admin()->create(['admin_role' => AdminRole::CONTENT_EDITOR]);
        $this->actingAs($admin);
        $request = Request::create('/admin/orders', 'PUT');
        $request->setUserResolver(fn () => $admin);

        try {
            app(AdminPermissionMiddleware::class)->handle(
                $request,
                fn () => response('unexpected'),
                AdminRole::ORDERS_MANAGE,
            );
            $this->fail('Permission middleware allowed a forbidden action.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
