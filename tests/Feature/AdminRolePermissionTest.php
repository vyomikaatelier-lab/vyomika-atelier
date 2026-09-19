<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminPermissionMiddleware;
use App\Models\AdminRolePermissionAudit;
use App\Models\AdminRolePermissionOverride;
use App\Models\User;
use App\Services\AdminRolePermissionService;
use App\Support\AdminAccess;
use App\Support\AdminPermissionResolver;
use App\Support\AdminRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_code_defaults_remain_effective_without_overrides(): void
    {
        $this->assertSame(0, AdminRolePermissionOverride::query()->count());

        $owner = $this->owner();
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);
        $viewer = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);

        foreach (AdminRole::permissions() as $permission) {
            $this->assertTrue($owner->hasAdminPermission($permission), $permission);
        }

        $this->assertTrue($administrator->hasAdminPermission(AdminRole::STAFF_VIEW));
        $this->assertFalse($administrator->hasAdminPermission(AdminRole::STAFF_MANAGE));
        $this->assertTrue($viewer->hasAdminPermission(AdminRole::ORDERS_VIEW));
        $this->assertFalse($viewer->hasAdminPermission(AdminRole::ORDERS_MANAGE));
        $this->assertSame(
            AdminRole::permissionsFor(AdminRole::VIEWER),
            app(AdminPermissionResolver::class)->permissionsFor(AdminRole::VIEWER),
        );
    }

    public function test_owner_sees_editable_switches_and_non_owner_cannot_update(): void
    {
        $owner = $this->owner();
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);

        $this->asVerifiedAdmin($owner)->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('role="switch"', false)
            ->assertSee('type="checkbox"', false)
            ->assertSee('aria-checked="true"', false)
            ->assertSee('aria-checked="false"', false)
            ->assertSee('Save permission changes')
            ->assertSee('Required for the Owner role and cannot be turned off.')
            ->assertSee('Only the Owner can manage staff, invitations and role access.')
            ->assertSee('Owner: Manage staff &amp; roles, on, locked.', false)
            ->assertSee('Viewer: View orders, on', false)
            ->assertSee('Administrator: Manage staff &amp; roles, off, locked.', false);

        $this->asVerifiedAdmin($administrator)->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('role="switch"', false)
            ->assertDontSee('Save permission changes')
            ->assertSee('aria-disabled="true"', false);

        $this->asVerifiedAdmin($administrator)
            ->from(route('admin.staff.index'))
            ->put(route('admin.staff.role-permissions.update'), [
                'permissions' => [
                    AdminRole::VIEWER => [
                        AdminRole::ORDERS_MANAGE => '1',
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('admin_role_permission_overrides', 0);
    }

    public function test_owner_essential_and_owner_only_permissions_are_locked(): void
    {
        $owner = $this->owner();

        $this->asVerifiedAdmin($owner)
            ->from(route('admin.staff.index'))
            ->put(route('admin.staff.role-permissions.update'), [
                'permissions' => [
                    AdminRole::OWNER => [
                        AdminRole::STAFF_MANAGE => '0',
                    ],
                    AdminRole::ADMINISTRATOR => [
                        AdminRole::STAFF_MANAGE => '1',
                    ],
                    AdminRole::VIEWER => [
                        AdminRole::STAFF_MANAGE => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseCount('admin_role_permission_overrides', 0);
        $this->assertTrue($owner->fresh()->hasAdminPermission(AdminRole::STAFF_MANAGE));
        $this->assertFalse(
            User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR])
                ->hasAdminPermission(AdminRole::STAFF_MANAGE),
        );
    }

    public function test_enabling_a_permission_updates_middleware_and_navigation(): void
    {
        $owner = $this->owner();
        $viewer = User::factory()->admin()->create([
            'admin_role' => AdminRole::VIEWER,
            'admin_session_version' => 3,
        ]);

        $this->assertFalse($viewer->hasAdminPermission(AdminRole::STAFF_VIEW));
        $this->asVerifiedAdmin($viewer)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Staff & Roles');
        $this->assertMiddlewareDenied($viewer, AdminRole::STAFF_VIEW);

        $this->asVerifiedAdmin($owner)->put(route('admin.staff.role-permissions.update'), [
            'permissions' => [
                AdminRole::VIEWER => [
                    AdminRole::STAFF_VIEW => '1',
                ],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $viewer->refresh();
        $this->assertTrue($viewer->hasAdminPermission(AdminRole::STAFF_VIEW));
        $this->assertSame(4, $viewer->admin_session_version);
        $this->assertMiddlewareAllowed($viewer, AdminRole::STAFF_VIEW);

        $this->asVerifiedAdmin($viewer)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Staff & Roles', false);

        $this->asVerifiedAdmin($viewer)->get(route('admin.staff.index'))
            ->assertOk()
            ->assertDontSee('Save permission changes');
    }

    public function test_disabling_a_permission_rejects_existing_sessions_for_that_role(): void
    {
        $owner = $this->owner();
        $editor = User::factory()->admin()->create([
            'admin_role' => AdminRole::CONTENT_EDITOR,
            'admin_session_version' => 2,
        ]);

        $this->assertTrue($editor->hasAdminPermission(AdminRole::CONTENT_PUBLISH));
        $this->assertTrue(AdminAccess::verified($this->verifiedRequest($editor)));

        $this->asVerifiedAdmin($owner)->put(route('admin.staff.role-permissions.update'), [
            'permissions' => [
                AdminRole::CONTENT_EDITOR => [
                    AdminRole::CONTENT_PUBLISH => '0',
                ],
            ],
        ])->assertRedirect();

        $editor->refresh();
        $this->assertFalse($editor->hasAdminPermission(AdminRole::CONTENT_PUBLISH));
        $this->assertSame(3, $editor->admin_session_version);
        $this->assertFalse(AdminAccess::verified($this->verifiedRequest($editor, sessionVersion: 2)));
        $this->assertSame(1, $owner->fresh()->admin_session_version);
        $this->assertTrue(AdminAccess::verified($this->verifiedRequest($owner)));
        $this->assertMiddlewareDenied($editor, AdminRole::CONTENT_PUBLISH);
    }

    public function test_invalid_roles_and_permissions_are_rejected(): void
    {
        $owner = $this->owner();

        $this->asVerifiedAdmin($owner)
            ->from(route('admin.staff.index'))
            ->put(route('admin.staff.role-permissions.update'), [
                'permissions' => [
                    'invented-role' => [
                        AdminRole::DASHBOARD_VIEW => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('permissions');

        $this->asVerifiedAdmin($owner)
            ->from(route('admin.staff.index'))
            ->put(route('admin.staff.role-permissions.update'), [
                'permissions' => [
                    AdminRole::VIEWER => [
                        'not.a.permission' => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseCount('admin_role_permission_overrides', 0);
        $this->assertDatabaseCount('admin_role_permission_audits', 0);
    }

    public function test_updates_are_atomic_and_noops_do_not_audit_or_revoke_sessions(): void
    {
        $owner = $this->owner();
        $viewer = User::factory()->admin()->create([
            'admin_role' => AdminRole::VIEWER,
            'admin_session_version' => 1,
        ]);

        $this->asVerifiedAdmin($owner)
            ->from(route('admin.staff.index'))
            ->put(route('admin.staff.role-permissions.update'), [
                'permissions' => [
                    AdminRole::VIEWER => [
                        AdminRole::ORDERS_VIEW => '1',
                        AdminRole::STAFF_MANAGE => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseCount('admin_role_permission_overrides', 0);
        $this->assertDatabaseCount('admin_role_permission_audits', 0);
        $this->assertSame(1, $viewer->fresh()->admin_session_version);

        $this->asVerifiedAdmin($owner)->put(route('admin.staff.role-permissions.update'), [
            'permissions' => [
                AdminRole::VIEWER => [
                    AdminRole::ORDERS_VIEW => '1',
                ],
            ],
        ])->assertRedirect()->assertSessionHas('success', 'No permission changes were needed.');

        $this->assertDatabaseCount('admin_role_permission_overrides', 0);
        $this->assertDatabaseCount('admin_role_permission_audits', 0);
        $this->assertSame(1, $viewer->fresh()->admin_session_version);
    }

    public function test_effective_changes_create_audit_records_and_store_only_differences(): void
    {
        $owner = $this->owner();
        $viewer = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);

        $this->asVerifiedAdmin($owner)->put(route('admin.staff.role-permissions.update'), [
            'permissions' => [
                AdminRole::VIEWER => [
                    AdminRole::ORDERS_MANAGE => '1',
                    AdminRole::ORDERS_VIEW => '0',
                ],
            ],
        ])->assertRedirect();

        $this->assertTrue($viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_MANAGE));
        $this->assertFalse($viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_VIEW));
        $this->assertDatabaseCount('admin_role_permission_overrides', 2);
        $this->assertDatabaseCount('admin_role_permission_audits', 2);

        $audit = AdminRolePermissionAudit::query()
            ->where('permission', AdminRole::ORDERS_MANAGE)
            ->firstOrFail();
        $this->assertSame($owner->id, $audit->actor_user_id);
        $this->assertSame(AdminRole::VIEWER, $audit->admin_role);
        $this->assertFalse($audit->previous_enabled);
        $this->assertTrue($audit->new_enabled);
        $this->assertNotNull($audit->created_at);

        $this->asVerifiedAdmin($owner)->put(route('admin.staff.role-permissions.update'), [
            'permissions' => [
                AdminRole::VIEWER => [
                    AdminRole::ORDERS_VIEW => '1',
                ],
            ],
        ])->assertRedirect();

        $this->assertTrue($viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_VIEW));
        $this->assertDatabaseMissing('admin_role_permission_overrides', [
            'admin_role' => AdminRole::VIEWER,
            'permission' => AdminRole::ORDERS_VIEW,
        ]);
    }

    public function test_last_active_owner_protection_remains_intact(): void
    {
        $owner = $this->owner();

        $this->asVerifiedAdmin($owner)->patch(route('admin.staff.update', $owner), [
            'admin_role' => AdminRole::ADMINISTRATOR,
            'is_active' => '1',
        ])->assertSessionHasErrors('admin_role');

        $this->assertTrue($owner->fresh()->isOwner());
        $this->assertTrue($owner->fresh()->hasAdminPermission(AdminRole::STAFF_MANAGE));
    }

    public function test_permission_tables_roll_back_without_changing_users(): void
    {
        $path = 'database/migrations/2026_09_19_000002_create_admin_role_permission_tables.php';
        $owner = $this->owner();

        $this->assertTrue(Schema::hasTable('admin_role_permission_overrides'));
        $this->assertTrue(Schema::hasTable('admin_role_permission_audits'));

        $this->artisan('migrate:rollback', [
            '--path' => $path,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertFalse(Schema::hasTable('admin_role_permission_overrides'));
        $this->assertFalse(Schema::hasTable('admin_role_permission_audits'));
        $this->assertTrue($owner->fresh()->isOwner());
        $this->assertTrue($owner->fresh()->hasAdminPermission(AdminRole::STAFF_MANAGE));

        $this->artisan('migrate', [
            '--path' => $path,
            '--force' => true,
        ])->assertExitCode(0);
    }

    public function test_missing_overrides_table_uses_code_defaults(): void
    {
        $viewer = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);

        Schema::drop('admin_role_permission_overrides');
        app()->forgetInstance(AdminPermissionResolver::class);

        $this->assertFalse(Schema::hasTable('admin_role_permission_overrides'));
        $this->assertTrue($viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_VIEW));
        $this->assertFalse($viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_MANAGE));
        $this->assertFalse($administrator->fresh()->hasAdminPermission(AdminRole::STAFF_MANAGE));
        $this->assertSame(
            AdminRole::permissionsFor(AdminRole::VIEWER),
            app(AdminPermissionResolver::class)->permissionsFor(AdminRole::VIEWER),
        );
    }

    public function test_database_failure_does_not_restore_disabled_permissions(): void
    {
        $owner = $this->owner();
        $viewer = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);

        $this->asVerifiedAdmin($owner)->put(route('admin.staff.role-permissions.update'), [
            'permissions' => [
                AdminRole::VIEWER => [
                    AdminRole::ORDERS_VIEW => '0',
                ],
            ],
        ])->assertRedirect();

        $this->assertFalse($viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_VIEW));

        $queryFailure = new class extends AdminPermissionResolver
        {
            protected function fetchOverrideRows(): Collection
            {
                throw new QueryException(
                    'sqlite',
                    'select * from admin_role_permission_overrides',
                    [],
                    new PDOException('SQLSTATE[HY000] [2002] Connection refused'),
                );
            }
        };

        app()->instance(AdminPermissionResolver::class, $queryFailure);

        try {
            $viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_VIEW);
            $this->fail('Query failures must not fail open.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Connection refused', $exception->getMessage());
        }

        $schemaFailure = new class extends AdminPermissionResolver
        {
            protected function overridesTableExists(): bool
            {
                throw new QueryException(
                    'sqlite',
                    'select name from sqlite_master',
                    [],
                    new PDOException('SQLSTATE[HY000] [2002] Connection refused'),
                );
            }
        };

        app()->instance(AdminPermissionResolver::class, $schemaFailure);

        try {
            $viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_VIEW);
            $this->fail('Schema inspection failures must not be treated as a missing table.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Connection refused', $exception->getMessage());
        }
    }

    public function test_stale_shared_cache_cannot_restore_a_disabled_permission(): void
    {
        config(['cache.default' => 'file']);
        Cache::flush();

        $owner = $this->owner();
        $viewer = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);
        $this->assertTrue($viewer->hasAdminPermission(AdminRole::ORDERS_VIEW));

        $this->asVerifiedAdmin($owner)->put(route('admin.staff.role-permissions.update'), [
            'permissions' => [
                AdminRole::VIEWER => [
                    AdminRole::ORDERS_VIEW => '0',
                ],
            ],
        ])->assertRedirect();

        Cache::put('admin.role_permission_overrides', [
            AdminRole::VIEWER.'.'.AdminRole::ORDERS_VIEW => true,
        ], 300);
        Cache::put('admin_role_permission_overrides', [
            AdminRole::VIEWER.'.'.AdminRole::ORDERS_VIEW => true,
        ], 300);

        app()->forgetInstance(AdminPermissionResolver::class);

        $viewer->refresh();
        $this->assertFalse($viewer->hasAdminPermission(AdminRole::ORDERS_VIEW));
        $this->assertFalse(
            app(AdminPermissionResolver::class)->isGranted(AdminRole::VIEWER, AdminRole::ORDERS_VIEW)
        );
        $this->assertFalse(AdminRole::permissionMatrix()[AdminRole::VIEWER]['permissions'][AdminRole::ORDERS_VIEW]);
        $this->assertMiddlewareDenied($viewer, AdminRole::ORDERS_VIEW);
        $this->assertSame(app(AdminPermissionResolver::class), app(AdminPermissionResolver::class));

        Cache::flush();
    }

    public function test_malformed_switch_values_are_rejected_without_side_effects(): void
    {
        $owner = $this->owner();
        $viewer = User::factory()->admin()->create([
            'admin_role' => AdminRole::VIEWER,
            'admin_session_version' => 4,
        ]);
        $service = app(AdminRolePermissionService::class);

        foreach (['maybe', '2', 'true', 'false', 'on', ['1'], (object) ['enabled' => '1']] as $invalid) {
            $this->asVerifiedAdmin($owner)
                ->from(route('admin.staff.index'))
                ->put(route('admin.staff.role-permissions.update'), [
                    'permissions' => [
                        AdminRole::VIEWER => [
                            AdminRole::ORDERS_MANAGE => $invalid,
                        ],
                    ],
                ])
                ->assertRedirect(route('admin.staff.index'))
                ->assertSessionHasErrors('permissions');

            try {
                $service->update($owner, [
                    AdminRole::VIEWER => [
                        AdminRole::ORDERS_MANAGE => $invalid,
                    ],
                ]);
                $this->fail('Malformed permission values must fail service validation.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('permissions', $exception->errors());
            }

            $this->assertDatabaseCount('admin_role_permission_overrides', 0);
            $this->assertDatabaseCount('admin_role_permission_audits', 0);
            $this->assertSame(4, $viewer->fresh()->admin_session_version);
            $this->assertFalse($viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_MANAGE));
        }
    }

    public function test_missing_unchecked_switches_do_not_disable_unsubmitted_permissions(): void
    {
        $owner = $this->owner();
        $viewer = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);

        $applied = app(AdminRolePermissionService::class)->update($owner, [
            AdminRole::VIEWER => [
                AdminRole::ORDERS_MANAGE => '1',
            ],
        ]);

        $this->assertSame(1, $applied);
        $this->assertTrue($viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_MANAGE));
        $this->assertTrue($viewer->fresh()->hasAdminPermission(AdminRole::ORDERS_VIEW));
        $this->assertDatabaseCount('admin_role_permission_overrides', 1);
    }

    public function test_stored_staff_manage_override_is_ignored_for_non_owners(): void
    {
        $owner = $this->owner();
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);

        AdminRolePermissionOverride::query()->create([
            'admin_role' => AdminRole::ADMINISTRATOR,
            'permission' => AdminRole::STAFF_MANAGE,
            'enabled' => true,
            'updated_by' => $owner->getKey(),
        ]);

        app()->forgetInstance(AdminPermissionResolver::class);

        $this->assertFalse($administrator->fresh()->hasAdminPermission(AdminRole::STAFF_MANAGE));
        $this->assertMiddlewareDenied($administrator, AdminRole::STAFF_MANAGE);
        $this->assertFalse(
            AdminRole::permissionMatrix()[AdminRole::ADMINISTRATOR]['permissions'][AdminRole::STAFF_MANAGE]
        );
    }

    public function test_permission_audit_records_are_immutable_through_the_model_surface(): void
    {
        $owner = $this->owner();

        $this->asVerifiedAdmin($owner)->put(route('admin.staff.role-permissions.update'), [
            'permissions' => [
                AdminRole::VIEWER => [
                    AdminRole::ORDERS_MANAGE => '1',
                ],
            ],
        ])->assertRedirect();

        $audit = AdminRolePermissionAudit::query()->firstOrFail();

        try {
            $audit->update(['new_enabled' => false]);
            $this->fail('Audit updates must be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be changed', $exception->getMessage());
        }

        try {
            $audit->forceFill(['new_enabled' => false])->save();
            $this->fail('Audit saves must be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be changed', $exception->getMessage());
        }

        try {
            $audit->delete();
            $this->fail('Audit deletes must be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }

        $this->assertTrue($audit->fresh()->new_enabled);
        $this->assertDatabaseCount('admin_role_permission_audits', 1);
    }

    private function owner(): User
    {
        return User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
    }

    private function asVerifiedAdmin(User $admin): self
    {
        $admin->refresh();

        return $this->actingAs($admin)->withSession([
            AdminAccess::SESSION_KEY => true,
            AdminAccess::SESSION_VERSION_KEY => (int) $admin->admin_session_version,
            AdminAccess::SESSION_USER_KEY => $admin->getKey(),
        ]);
    }

    private function verifiedRequest(User $admin, ?int $sessionVersion = null): Request
    {
        $this->actingAs($admin);
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $admin);
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put([
            AdminAccess::SESSION_KEY => true,
            AdminAccess::SESSION_VERSION_KEY => $sessionVersion ?? (int) $admin->admin_session_version,
            AdminAccess::SESSION_USER_KEY => $admin->getKey(),
        ]);

        return $request;
    }

    private function assertMiddlewareAllowed(User $admin, string $permission): void
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/staff', 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = app(AdminPermissionMiddleware::class)->handle(
            $request,
            fn () => response('allowed'),
            $permission,
        );

        $this->assertSame('allowed', $response->getContent());
    }

    private function assertMiddlewareDenied(User $admin, string $permission): void
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/staff', 'GET');
        $request->setUserResolver(fn () => $admin);

        try {
            app(AdminPermissionMiddleware::class)->handle(
                $request,
                fn () => response('unexpected'),
                $permission,
            );
            $this->fail('Permission middleware allowed a forbidden action.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
