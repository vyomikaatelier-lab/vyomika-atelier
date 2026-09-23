<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationMail;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\StaffManagementService;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StaffInvitationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_invitation_creates_staff_once_and_requires_immediate_mfa_enrollment(): void
    {
        [$invitation, $token] = $this->invitation();

        $response = $this->post(route('admin.staff-invitations.store', $invitation), [
            'token' => $token,
            'password' => 'Strong!Password123',
            'password_confirmation' => 'Strong!Password123',
        ]);

        $response->assertRedirect(route('admin.login'))->assertSessionHas('success');
        $staff = User::query()->where('email', 'staff@example.com')->firstOrFail();
        $this->assertTrue($staff->isAdmin());
        $this->assertTrue($staff->is_active);
        $this->assertSame(AdminRole::ORDER_MANAGER, $staff->admin_role);
        $this->assertNotNull($staff->staff_id);
        $this->assertTrue($staff->two_factor_grace_ends_at->lessThanOrEqualTo(now()));
        $this->assertNotNull($invitation->fresh()->accepted_at);

        $this->post(route('admin.staff-invitations.store', $invitation), [
            'token' => $token,
            'password' => 'Another!Password123',
            'password_confirmation' => 'Another!Password123',
        ])->assertSessionHasErrors('invitation');
        $this->assertSame(1, User::query()->where('email', 'staff@example.com')->count());
    }

    public function test_wrong_expired_or_revoked_tokens_cannot_create_staff(): void
    {
        [$invitation] = $this->invitation();

        $this->post(route('admin.staff-invitations.store', $invitation), [
            'token' => str_repeat('x', 64),
            'password' => 'Strong!Password123',
            'password_confirmation' => 'Strong!Password123',
        ])->assertSessionHasErrors('invitation');

        $invitation->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->get($this->signedUrl($invitation, str_repeat('a', 64)))->assertGone();

        $invitation->forceFill(['expires_at' => now()->addHour(), 'revoked_at' => now()])->save();
        $this->get($this->signedUrl($invitation, str_repeat('a', 64)))->assertGone();
        $this->assertDatabaseMissing('users', ['email' => 'staff@example.com']);
    }

    public function test_unsigned_acceptance_page_is_rejected(): void
    {
        [$invitation, $token] = $this->invitation();

        $this->get(route('admin.staff-invitations.accept', [
            'invitation' => $invitation,
            'token' => $token,
        ]))->assertForbidden();
    }

    public function test_signed_page_still_requires_the_current_plain_token(): void
    {
        [$invitation, $token] = $this->invitation();

        $this->get($this->signedUrl($invitation, $token))
            ->assertOk()
            ->assertSee('Set up your account');

        $this->get($this->signedUrl($invitation, str_repeat('b', 64)))
            ->assertForbidden();
    }

    public function test_existing_customer_email_is_never_promoted_to_admin(): void
    {
        Mail::fake();
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $customer = User::factory()->create(['email' => 'customer@example.com']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(StaffManagementService::class)->invite(
            $owner,
            'Customer',
            $customer->email,
            AdminRole::VIEWER,
        );
    }

    /** @return array{StaffInvitation, string} */
    private function invitation(): array
    {
        $token = str_repeat('a', 64);
        $owner = User::factory()->admin()->create(['admin_role' => AdminRole::OWNER]);
        $invitation = StaffInvitation::query()->create([
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'admin_role' => AdminRole::ORDER_MANAGER,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $owner->getKey(),
            'expires_at' => now()->addHour(),
        ]);

        return [$invitation, $token];
    }

    private function signedUrl(StaffInvitation $invitation, string $token): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'admin.staff-invitations.accept',
            now()->addHour(),
            ['invitation' => $invitation, 'token' => $token],
        );
    }
}
