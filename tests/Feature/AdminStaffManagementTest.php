<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationMail;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_staff_without_creating_an_account_early(): void
    {
        Mail::fake();
        $owner = $this->owner();

        $response = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'name' => 'Catalog Staff',
            'email' => 'catalog@example.com',
            'admin_role' => AdminRole::CATALOG_MANAGER,
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['email' => 'catalog@example.com']);
        $this->assertDatabaseHas('staff_invitations', [
            'email' => 'catalog@example.com',
            'admin_role' => AdminRole::CATALOG_MANAGER,
        ]);
        Mail::assertSent(StaffInvitationMail::class, fn (StaffInvitationMail $mail) =>
            $mail->hasTo('catalog@example.com') && ! str_contains($mail->acceptUrl, 'token_hash'));
    }

    public function test_non_owner_cannot_invite_or_update_staff(): void
    {
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);
        $staff = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);

        $this->asVerifiedAdmin($administrator)->post(route('admin.staff.invite'), [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'admin_role' => AdminRole::VIEWER,
        ])->assertForbidden();

        $this->asVerifiedAdmin($administrator)->patch(route('admin.staff.update', $staff), [
            'admin_role' => AdminRole::ORDER_MANAGER,
            'is_active' => '1',
        ])->assertForbidden();
    }

    public function test_owner_role_cannot_be_assigned_directly_by_invitation(): void
    {
        Mail::fake();
        $owner = $this->owner();

        $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'name' => 'Second Owner',
            'email' => 'second-owner@example.com',
            'admin_role' => AdminRole::OWNER,
        ])->assertSessionHasErrors('admin_role');

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('staff_invitations', ['email' => 'second-owner@example.com']);
    }

    public function test_last_active_owner_cannot_be_demoted_or_disabled(): void
    {
        $owner = $this->owner();

        $this->asVerifiedAdmin($owner)->patch(route('admin.staff.update', $owner), [
            'admin_role' => AdminRole::ADMINISTRATOR,
            'is_active' => '1',
        ])->assertSessionHasErrors('admin_role');

        $this->asVerifiedAdmin($owner)->patch(route('admin.staff.update', $owner), [
            'admin_role' => AdminRole::OWNER,
            'is_active' => '0',
        ])->assertSessionHasErrors('admin_role');

        $owner->refresh();
        $this->assertTrue($owner->isOwner());
        $this->assertTrue($owner->is_active);
    }

    public function test_role_or_status_change_revokes_existing_admin_sessions(): void
    {
        $owner = $this->owner();
        $staff = User::factory()->admin()->create([
            'admin_role' => AdminRole::VIEWER,
            'admin_session_version' => 4,
        ]);

        $this->asVerifiedAdmin($owner)->patch(route('admin.staff.update', $staff), [
            'admin_role' => AdminRole::ORDER_MANAGER,
            'is_active' => '1',
        ])->assertRedirect();

        $staff->refresh();
        $this->assertSame(AdminRole::ORDER_MANAGER, $staff->admin_role);
        $this->assertSame(5, $staff->admin_session_version);
        $this->assertNotNull($staff->staff_id);

        $this->asVerifiedAdmin($owner)->post(route('admin.staff.revoke-sessions', $staff))
            ->assertRedirect();
        $this->assertSame(6, $staff->fresh()->admin_session_version);
    }

    public function test_administrator_can_view_staff_but_navigation_is_hidden_from_operational_roles(): void
    {
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);
        $viewer = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);

        $this->asVerifiedAdmin($administrator)->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('Staff & Roles', false)
            ->assertSee('View only');

        $this->asVerifiedAdmin($viewer)->get(route('admin.staff.index'))->assertForbidden();
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
}
