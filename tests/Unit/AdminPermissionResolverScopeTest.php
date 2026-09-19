<?php

namespace Tests\Unit;

use App\Support\AdminPermissionResolver;
use Tests\TestCase;

class AdminPermissionResolverScopeTest extends TestCase
{
    public function test_resolver_is_request_scoped_and_flushed_between_lifecycles(): void
    {
        $first = app(AdminPermissionResolver::class);
        $second = app(AdminPermissionResolver::class);

        $this->assertSame($first, $second);

        $this->app->forgetScopedInstances();

        $afterFlush = app(AdminPermissionResolver::class);

        $this->assertNotSame($first, $afterFlush);
        $this->assertSame($afterFlush, app(AdminPermissionResolver::class));
    }
}
