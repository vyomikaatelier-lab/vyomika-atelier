<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminAccessVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_staff_session_must_match_current_version(): void
    {
        $admin = User::factory()->admin()->create([
            'admin_role' => AdminRole::ORDER_MANAGER,
            'admin_session_version' => 4,
        ]);
        $this->actingAs($admin);

        $request = Request::create('/admin', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put([
            AdminAccess::SESSION_KEY => true,
            AdminAccess::SESSION_VERSION_KEY => 4,
            AdminAccess::SESSION_USER_KEY => $admin->id,
        ]);

        $this->assertTrue(AdminAccess::verified($request));

        $admin->forceFill(['admin_session_version' => 5])->save();

        $this->assertFalse(AdminAccess::verified($request));
    }

    public function test_legacy_admin_session_remains_compatible_until_role_assignment(): void
    {
        $admin = User::factory()->admin()->create(['admin_role' => null]);
        $this->actingAs($admin);

        $request = Request::create('/admin', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put(AdminAccess::SESSION_KEY, true);

        $this->assertTrue(AdminAccess::verified($request));

        $admin->forceFill(['admin_role' => AdminRole::OWNER])->save();

        $this->assertFalse(AdminAccess::verified($request));
    }

    public function test_grant_records_current_version_and_revoke_removes_both_keys(): void
    {
        $admin = User::factory()->admin()->create([
            'admin_role' => AdminRole::OWNER,
            'admin_session_version' => 7,
        ]);
        $this->actingAs($admin);

        $request = Request::create('/admin', 'GET');
        $request->setLaravelSession(app('session')->driver());

        AdminAccess::grant($request);

        $this->assertTrue($request->session()->get(AdminAccess::SESSION_KEY));
        $this->assertSame(7, $request->session()->get(AdminAccess::SESSION_VERSION_KEY));
        $this->assertSame($admin->id, $request->session()->get(AdminAccess::SESSION_USER_KEY));
        $this->assertTrue(AdminAccess::verified($request));

        AdminAccess::revoke($request);

        $this->assertFalse($request->session()->has(AdminAccess::SESSION_KEY));
        $this->assertFalse($request->session()->has(AdminAccess::SESSION_VERSION_KEY));
        $this->assertFalse($request->session()->has(AdminAccess::SESSION_USER_KEY));
    }

    public function test_versioned_access_cannot_be_replayed_as_another_admin(): void
    {
        $owner = User::factory()->admin()->create([
            'admin_role' => AdminRole::OWNER,
            'admin_session_version' => 2,
        ]);
        $otherAdmin = User::factory()->admin()->create([
            'admin_role' => AdminRole::ADMINISTRATOR,
            'admin_session_version' => 2,
        ]);

        $request = Request::create('/admin', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn () => $owner);
        AdminAccess::grant($request, $owner);

        $this->assertTrue(AdminAccess::verified($request));

        $request->setUserResolver(fn () => $otherAdmin);

        $this->assertFalse(AdminAccess::verified($request));
    }
}
