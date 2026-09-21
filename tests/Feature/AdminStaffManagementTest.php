<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationMail;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\StaffInvitationMailer;
use App\Support\AdminAccess;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_invitation_email_sends_once_and_does_not_reveal_the_link(): void
    {
        Mail::fake();
        $logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logs): void {
            $logs[] = $event;
        });

        $owner = $this->owner();

        $response = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'name' => 'Catalog Staff',
            'email' => 'catalog@example.com',
            'admin_role' => AdminRole::CATALOG_MANAGER,
        ]);

        $response->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('success', 'Invitation sent');

        Mail::assertSent(StaffInvitationMail::class, 1);

        $invitation = StaffInvitation::query()->where('email', 'catalog@example.com')->firstOrFail();
        $this->assertTrue($invitation->isPending());
        $this->assertSame('catalog@example.com', $invitation->pending_email);
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertDatabaseMissing('users', ['email' => 'catalog@example.com']);

        $acceptUrl = null;
        Mail::assertSent(StaffInvitationMail::class, function (StaffInvitationMail $mail) use (&$acceptUrl, $invitation): bool {
            $acceptUrl = $mail->acceptUrl;
            $this->assertSame($invitation->email, $mail->invitation->email);

            return true;
        });

        $this->assertNotNull($acceptUrl);
        preg_match('/token=([A-Za-z0-9]{64})/', $acceptUrl, $matches);
        $token = $matches[1] ?? '';
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertDatabaseMissing('staff_invitations', ['token_hash' => $token]);

        $this->assertSecretAbsentFromTransport($token, $acceptUrl, $logs, $response);

        $history = $this->asVerifiedAdmin($owner)->get(route('admin.staff.index'));
        $history->assertOk()
            ->assertSee('Invitation sent', false)
            ->assertSee('catalog@example.com', false)
            ->assertSee('Pending', false)
            ->assertDontSee($token)
            ->assertDontSee($acceptUrl, false)
            ->assertDontSee('id="invitation-url"', false)
            ->assertDontSee('Manual invitation link', false);
    }

    public function test_mail_failure_does_not_return_500_and_shows_one_time_fallback_link(): void
    {
        $this->failInvitationMail('SMTP 535 authentication failed for user smtp-secret');
        $logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logs): void {
            $logs[] = $event;
        });

        $owner = $this->owner();
        $response = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'name' => 'Catalog Staff',
            'email' => 'fallback@example.com',
            'admin_role' => AdminRole::CATALOG_MANAGER,
        ]);

        $response->assertOk()
            ->assertSee('Staff &amp; Roles', false)
            ->assertSee('Email could not be delivered', false)
            ->assertSee('data-invitation-fallback-panel', false)
            ->assertSee('Catalog Staff', false)
            ->assertSee('fallback@example.com', false)
            ->assertSee('Catalog Manager', false)
            ->assertSee('Copy Link', false)
            ->assertSee('Regenerate Link', false)
            ->assertSee('>Close</a>', false)
            ->assertSee('This link is shown only once. Share it only with the intended staff member.', false)
            ->assertSee('The invitation email could not be delivered. Send this link to the intended staff member manually.', false)
            ->assertDontSee('SMTP 535', false)
            ->assertDontSee('smtp-secret', false)
            ->assertDontSee('authentication failed', false)
            ->assertDontSee('min-w-[960px]', false)
            ->assertDontSee('Role permission comparison table');

        $this->assertSame(200, $response->status());
        $this->assertSame('/admin/staff', parse_url(route('admin.staff.invite'), PHP_URL_PATH));
        $this->assertStringNotContainsString('token=', (string) $response->headers->get('Location'));

        $this->assertSame(200, $response->status());
        [$invitation, $token, $acceptUrl] = $this->extractInvitationReveal($response, 'fallback@example.com');

        $this->assertTrue($invitation->isPending());
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertSame('fallback@example.com', $invitation->pending_email);
        $this->assertDatabaseMissing('staff_invitations', ['token_hash' => $token]);
        $this->assertSecretAbsentFromTransport($token, $acceptUrl, $logs, $response, allowBody: true);

        $this->post(route('admin.logout'));
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get($this->signedUrl($invitation, $token))->assertOk()->assertSee('Set up your account');
    }

    public function test_invitation_fallback_is_isolated_first_party_and_not_recoverable_later(): void
    {
        $this->failInvitationMail();
        $owner = $this->owner();

        $response = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'name' => 'Catalog Staff',
            'email' => 'isolated@example.com',
            'admin_role' => AdminRole::CATALOG_MANAGER,
        ]);

        $response->assertOk();
        $content = $response->getContent();
        [, $token, $acceptUrl] = $this->extractInvitationReveal($response, 'isolated@example.com');

        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringContainsString("connect-src 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);

        $this->assertStringNotContainsString('cdn.tailwindcss.com', $content);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)/i', $content);
        $this->assertStringContainsString('/js/admin-invitation-reveal.js', $content);
        $this->assertStringContainsString('/css/admin-invitation-reveal.css', $content);
        $this->assertStringContainsString('data-invitation-fallback-panel', $content);
        $this->assertMatchesRegularExpression('/data-staff-index-url="[^"]*\/admin\/staff"/', $content);
        $this->assertDoesNotMatchRegularExpression('/data-staff-index-url="[^"]*[?#]/', $content);
        $this->assertMatchesRegularExpression('/name="staff_invitation_action"[^>]*value="regenerate"|value="regenerate"[^>]*name="staff_invitation_action"/', $content);
        $this->assertMatchesRegularExpression('/class="reveal-close" href="[^"]*\/admin\/staff"/', $content);
        $this->assertDoesNotMatchRegularExpression('/class="reveal-close"[^>]*href="[^"]+\?/', $content);
        $this->assertDoesNotMatchRegularExpression(
            '/href=(["\'])[^"\']*'.preg_quote($token, '/').'[^"\']*\1/',
            $content,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-[a-z-]+=(["\'])[^"\']*'.preg_quote($token, '/').'[^"\']*\1/i',
            $content,
        );

        $this->assertNoThirdPartyAssetReferences($content);
        $this->assertFirstPartyCopyScriptHasNoNetworkBehavior();

        $history = $this->asVerifiedAdmin($owner)->get(route('admin.staff.index'));
        $history->assertOk()
            ->assertSee('isolated@example.com', false)
            ->assertDontSee($token)
            ->assertDontSee($acceptUrl, false)
            ->assertDontSee('id="invitation-url"', false);
    }

    public function test_invitation_is_posted_to_staff_page_and_legacy_url_remains_compatible(): void
    {
        Mail::fake();
        $owner = $this->owner();

        $this->assertSame('/admin/staff', parse_url(route('admin.staff.invite'), PHP_URL_PATH));
        $this->assertSame('/admin/staff/invitations', parse_url(route('admin.staff.invite.legacy'), PHP_URL_PATH));

        $this->asVerifiedAdmin($owner)->post('/admin/staff', [
            'name' => 'Catalog Staff',
            'email' => 'posted-staff@example.com',
            'admin_role' => AdminRole::CATALOG_MANAGER,
        ])->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('success', 'Invitation sent');

        $this->failInvitationMail();
        $legacy = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite.legacy'), [
            'name' => 'Legacy Staff',
            'email' => 'legacy-invite@example.com',
            'admin_role' => AdminRole::ORDER_MANAGER,
        ]);
        $legacy->assertOk()
            ->assertSee('Email could not be delivered', false)
            ->assertSee('data-invitation-fallback-panel', false)
            ->assertDontSee('min-w-[960px]', false);
        $this->extractInvitationReveal($legacy, 'legacy-invite@example.com');
    }

    public function test_refreshing_invitation_creation_does_not_duplicate_rotate_or_reveal(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $payload = [
            'name' => 'Catalog Staff',
            'email' => 'catalog@example.com',
            'admin_role' => AdminRole::CATALOG_MANAGER,
        ];

        $first = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), $payload);
        $first->assertRedirect(route('admin.staff.index'));
        $invitation = StaffInvitation::query()->where('email', 'catalog@example.com')->firstOrFail();
        $hash = $invitation->token_hash;

        $repeat = $this->asVerifiedAdmin($owner)
            ->from(route('admin.staff.index'))
            ->post(route('admin.staff.invite'), $payload);

        $repeat->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('token=', (string) $repeat->getContent());
        $this->assertSame(1, StaffInvitation::query()->where('email', 'catalog@example.com')->count());
        $this->assertSame($hash, $invitation->fresh()->token_hash);
        $this->assertTrue($invitation->fresh()->isPending());
        Mail::assertSent(StaffInvitationMail::class, 1);
    }

    public function test_successful_regeneration_attempts_email_again_and_does_not_reveal_the_link(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'name' => 'Order Staff',
            'email' => 'orders@example.com',
            'admin_role' => AdminRole::ORDER_MANAGER,
        ])->assertRedirect();

        $invitation = StaffInvitation::query()->where('email', 'orders@example.com')->firstOrFail();
        $oldHash = $invitation->token_hash;

        $regenerated = $this->asVerifiedAdmin($owner)
            ->post(route('admin.staff-invitations.resend', $invitation));

        $regenerated->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('success', 'Invitation sent');
        $this->assertNotSame($oldHash, $invitation->fresh()->token_hash);
        Mail::assertSent(StaffInvitationMail::class, 2);

        $follow = $this->asVerifiedAdmin($owner)->get(route('admin.staff.index'));
        $follow->assertOk()->assertDontSee('id="invitation-url"', false);
    }

    public function test_failed_regeneration_shows_new_fallback_link_and_invalidates_the_old_token(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'name' => 'Order Staff',
            'email' => 'orders@example.com',
            'admin_role' => AdminRole::ORDER_MANAGER,
        ]);

        $invitation = StaffInvitation::query()->where('email', 'orders@example.com')->firstOrFail();
        $oldToken = $this->tokenFromSentMail();
        $this->failInvitationMail();

        $regenerated = $this->asVerifiedAdmin($owner)
            ->post(route('admin.staff-invitations.resend', $invitation));

        $regenerated->assertOk()
            ->assertSee('Replacement link ready', false)
            ->assertSee('This link is shown only once. Share it only with the intended staff member.', false);

        [, $newToken, $newUrl] = $this->extractInvitationReveal($regenerated, 'orders@example.com');
        $invitation->refresh();
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(hash('sha256', $newToken), $invitation->token_hash);

        $this->post(route('admin.logout'));
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get($this->signedUrl($invitation, $oldToken))->assertForbidden();
        $this->get($this->signedUrl($invitation, $newToken))->assertOk()->assertSee('Set up your account');
        $this->assertStringNotContainsString($newUrl, json_encode(session()->all() ?? []));
    }

    public function test_failed_regeneration_stays_on_staff_url_and_refresh_does_not_rotate_again(): void
    {
        $owner = $this->owner();
        $oldToken = str_repeat('b', 64);
        $invitation = StaffInvitation::query()->create([
            'name' => 'Order Staff',
            'email' => 'orders-refresh@example.com',
            'pending_email' => 'orders-refresh@example.com',
            'admin_role' => AdminRole::ORDER_MANAGER,
            'token_hash' => hash('sha256', $oldToken),
            'invited_by' => $owner->getKey(),
            'expires_at' => now()->addDay(),
        ]);

        $this->failInvitationMail();
        $regenerated = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'staff_invitation_action' => 'regenerate',
            'invitation_id' => $invitation->getKey(),
        ]);

        $regenerated->assertOk()
            ->assertSee('Replacement link ready', false)
            ->assertSee('data-invitation-fallback-panel', false)
            ->assertSee('name="staff_invitation_action"', false)
            ->assertSee('value="regenerate"', false);
        $this->assertSame('/admin/staff', parse_url(route('admin.staff.invite'), PHP_URL_PATH));
        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="[^"]*\/admin\/staff"[^>]*>[\s\S]*name="staff_invitation_action"/',
            $regenerated->getContent(),
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]*action="[^"]*\/admin\/staff\/invitations\/\d+\/resend"/',
            $regenerated->getContent(),
        );
        $this->assertMatchesRegularExpression('/data-staff-index-url="[^"]*\/admin\/staff"/', $regenerated->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-staff-index-url="[^"]*[?#]/', $regenerated->getContent());
        $this->assertDoesNotMatchRegularExpression('/history\.replaceState\([^)]*token=/', $regenerated->getContent());

        [, $newToken] = $this->extractInvitationReveal($regenerated, 'orders-refresh@example.com');
        $hashAfterRegen = $invitation->fresh()->token_hash;
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(hash('sha256', $newToken), $hashAfterRegen);
        $this->assertDatabaseMissing('staff_invitations', ['token_hash' => hash('sha256', $oldToken)]);

        $refresh = $this->asVerifiedAdmin($owner)->get(route('admin.staff.index'));
        $refresh->assertOk()
            ->assertDontSee('id="invitation-url"', false)
            ->assertDontSee($newToken)
            ->assertDontSee('Replacement link ready', false);
        $this->assertSame($hashAfterRegen, $invitation->fresh()->token_hash);

        $legacy = $this->asVerifiedAdmin($owner)
            ->post(route('admin.staff-invitations.resend', $invitation));
        $legacy->assertOk()->assertSee('Replacement link ready', false);
        [, $legacyToken] = $this->extractInvitationReveal($legacy, 'orders-refresh@example.com');
        $this->assertNotSame($newToken, $legacyToken);
        $this->assertSame(hash('sha256', $legacyToken), $invitation->fresh()->token_hash);
        $this->assertDatabaseMissing('staff_invitations', ['token_hash' => $hashAfterRegen]);
    }

    public function test_accepted_expired_and_revoked_invitations_cannot_be_regenerated(): void
    {
        $owner = $this->owner();
        $accepted = StaffInvitation::query()->create([
            'name' => 'Accepted',
            'email' => 'accepted@example.com',
            'admin_role' => AdminRole::VIEWER,
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'invited_by' => $owner->getKey(),
            'expires_at' => now()->addDay(),
            'accepted_at' => now(),
        ]);
        $expired = StaffInvitation::query()->create([
            'name' => 'Expired',
            'email' => 'expired@example.com',
            'admin_role' => AdminRole::VIEWER,
            'token_hash' => hash('sha256', str_repeat('b', 64)),
            'invited_by' => $owner->getKey(),
            'expires_at' => now()->subMinute(),
        ]);
        $revoked = StaffInvitation::query()->create([
            'name' => 'Revoked',
            'email' => 'revoked@example.com',
            'admin_role' => AdminRole::VIEWER,
            'token_hash' => hash('sha256', str_repeat('c', 64)),
            'invited_by' => $owner->getKey(),
            'expires_at' => now()->addDay(),
            'revoked_at' => now(),
        ]);

        foreach ([$accepted, $expired, $revoked] as $invitation) {
            $this->asVerifiedAdmin($owner)
                ->from(route('admin.staff.index'))
                ->post(route('admin.staff-invitations.resend', $invitation))
                ->assertRedirect(route('admin.staff.index'))
                ->assertSessionHasErrors('invitation');
        }
    }

    public function test_non_owner_cannot_invite_regenerate_or_revoke(): void
    {
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);
        $staff = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);
        $invitation = StaffInvitation::query()->create([
            'name' => 'Pending Staff',
            'email' => 'pending@example.com',
            'pending_email' => 'pending@example.com',
            'admin_role' => AdminRole::VIEWER,
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'invited_by' => $staff->getKey(),
            'expires_at' => now()->addDay(),
        ]);

        $this->asVerifiedAdmin($administrator)->post(route('admin.staff.invite'), [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'admin_role' => AdminRole::VIEWER,
        ])->assertForbidden();

        $this->asVerifiedAdmin($administrator)
            ->post(route('admin.staff-invitations.resend', $invitation))
            ->assertForbidden();

        $this->asVerifiedAdmin($administrator)->post(route('admin.staff.invite'), [
            'staff_invitation_action' => 'regenerate',
            'invitation_id' => $invitation->getKey(),
        ])->assertForbidden();

        $this->asVerifiedAdmin($administrator)
            ->delete(route('admin.staff-invitations.revoke', $invitation))
            ->assertForbidden();

        $this->asVerifiedAdmin($administrator)->patch(route('admin.staff.update', $staff), [
            'admin_role' => AdminRole::ORDER_MANAGER,
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('staff_invitations', ['email' => 'blocked@example.com']);
        $this->assertSame(hash('sha256', str_repeat('a', 64)), $invitation->fresh()->token_hash);
        $this->assertNull($invitation->fresh()->revoked_at);
    }

    public function test_owner_role_cannot_be_assigned_directly_by_invitation(): void
    {
        Mail::fake();
        $owner = $this->owner();

        $this->asVerifiedAdmin($owner)->from(route('admin.staff.index'))->post(route('admin.staff.invite'), [
            'name' => 'Second Owner',
            'email' => 'second-owner@example.com',
            'admin_role' => AdminRole::OWNER,
        ])->assertRedirect(route('admin.staff.index'))->assertSessionHasErrors('admin_role');

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('staff_invitations', ['email' => 'second-owner@example.com']);
    }

    public function test_existing_customer_is_not_promoted_through_invitation(): void
    {
        Mail::fake();
        $owner = $this->owner();
        User::factory()->create(['email' => 'customer@example.com']);

        $this->asVerifiedAdmin($owner)->from(route('admin.staff.index'))->post(route('admin.staff.invite'), [
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'admin_role' => AdminRole::VIEWER,
        ])->assertRedirect(route('admin.staff.index'))->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('staff_invitations', ['email' => 'customer@example.com']);
        $this->assertFalse((bool) User::query()->where('email', 'customer@example.com')->value('is_admin'));
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

    public function test_role_permission_matrix_renders_from_the_authoritative_mapping(): void
    {
        $owner = $this->owner();
        $matrix = AdminRole::permissionMatrix();

        $response = $this->asVerifiedAdmin($owner)->get(route('admin.staff.index'));
        $response->assertOk()
            ->assertSee('Roles and permissions')
            ->assertSee('Send invitation');

        foreach ($matrix as $definition) {
            $response->assertSee($definition['label']);
            $response->assertSee($definition['group_label']);
        }
        $response->assertSee($matrix[AdminRole::OWNER]['description']);
        $response->assertDontSee($matrix[AdminRole::VIEWER]['description']);
        $response->assertDontSee('min-w-[960px]', false);
        $response->assertDontSee('Role permission comparison table');

        foreach (AdminRole::permissionLabels() as $permission => $label) {
            $response->assertSee($label);
            $this->assertSame(
                in_array($permission, AdminRole::permissionsFor(AdminRole::OWNER), true),
                $matrix[AdminRole::OWNER]['permissions'][$permission],
            );
        }

        $this->assertFalse($matrix[AdminRole::ADMINISTRATOR]['permissions'][AdminRole::STAFF_MANAGE]);
        $this->assertTrue($matrix[AdminRole::ADMINISTRATOR]['permissions'][AdminRole::STAFF_VIEW]);
        $this->assertFalse($matrix[AdminRole::VIEWER]['permissions'][AdminRole::STAFF_VIEW]);
        $this->assertFalse($matrix[AdminRole::CATALOG_MANAGER]['permissions'][AdminRole::STAFF_MANAGE]);
    }

    public function test_smtp_timeout_exceptions_show_the_secure_fallback_instead_of_a_public_500(): void
    {
        $this->mock(StaffInvitationMailer::class, function ($mock): void {
            $mock->shouldReceive('send')->andThrow(new TransportException(
                'Connection to smtp.example.com timed out after 8 seconds for user smtp-secret'
            ));
        });
        $logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logs): void {
            $logs[] = $event;
        });

        $owner = $this->owner();
        $response = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'name' => 'Catalog Staff',
            'email' => 'timeout@example.com',
            'admin_role' => AdminRole::CATALOG_MANAGER,
        ]);

        $response->assertOk()
            ->assertSee('Email could not be delivered', false)
            ->assertDontSee('smtp.example.com', false)
            ->assertDontSee('smtp-secret', false)
            ->assertDontSee('timed out after 8 seconds', false);
        $this->assertSame(200, $response->status());
        [$invitation, $token, $acceptUrl] = $this->extractInvitationReveal($response, 'timeout@example.com');
        $this->assertSecretAbsentFromTransport($token, $acceptUrl, $logs, $response, allowBody: true);
        $this->assertTrue($invitation->isPending());
    }

    public function test_repeat_invitation_after_mail_failure_does_not_rotate_or_reveal_the_token(): void
    {
        $this->failInvitationMail();
        $owner = $this->owner();
        $payload = [
            'name' => 'Catalog Staff',
            'email' => 'fallback-repeat@example.com',
            'admin_role' => AdminRole::CATALOG_MANAGER,
        ];

        $first = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), $payload);
        $first->assertOk();
        [$invitation, $token, $acceptUrl] = $this->extractInvitationReveal($first, 'fallback-repeat@example.com');
        $hash = $invitation->token_hash;

        $repeat = $this->asVerifiedAdmin($owner)
            ->from(route('admin.staff.index'))
            ->post(route('admin.staff.invite'), $payload);

        $repeat->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('email');
        $this->assertStringNotContainsString($token, (string) $repeat->getContent());
        $this->assertStringNotContainsString($acceptUrl, (string) $repeat->getContent());
        $this->assertSame(1, StaffInvitation::query()->where('email', 'fallback-repeat@example.com')->count());
        $this->assertSame($hash, $invitation->fresh()->token_hash);
        $this->assertTrue($invitation->fresh()->isPending());
    }

    public function test_expired_invitation_releases_pending_email_and_permits_a_replacement(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $expiredToken = str_repeat('e', 64);
        $expired = StaffInvitation::query()->create([
            'name' => 'Expired Staff',
            'email' => 'replace@example.com',
            'pending_email' => 'replace@example.com',
            'admin_role' => AdminRole::VIEWER,
            'token_hash' => hash('sha256', $expiredToken),
            'invited_by' => $owner->getKey(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'name' => 'Replacement Staff',
            'email' => 'Replace@Example.com',
            'admin_role' => AdminRole::VIEWER,
        ])->assertRedirect(route('admin.staff.index'))->assertSessionHas('success', 'Invitation sent');

        $expired->refresh();
        $this->assertNull($expired->pending_email);
        $this->assertNull($expired->revoked_at);
        $this->assertNull($expired->accepted_at);

        $replacement = StaffInvitation::query()
            ->where('email', 'replace@example.com')
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $this->assertSame('replace@example.com', $replacement->pending_email);
        $this->assertNotSame($expired->getKey(), $replacement->getKey());

        $this->post(route('admin.logout'));
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $expiredAcceptUrl = URL::temporarySignedRoute(
            'admin.staff-invitations.accept',
            now()->addHour(),
            ['invitation' => $expired, 'token' => $expiredToken],
        );
        $this->get($expiredAcceptUrl)->assertGone();
    }

    public function test_administrator_can_view_staff_but_navigation_is_hidden_from_operational_roles(): void
    {
        $owner = $this->owner();
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);
        $viewer = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);

        $this->asVerifiedAdmin($owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Staff & Roles', false);

        $this->asVerifiedAdmin($administrator)->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('Staff & Roles', false)
            ->assertSee('Roles and permissions', false)
            ->assertSee('View only')
            ->assertDontSee('Send invitation');

        $this->asVerifiedAdmin($administrator)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Staff & Roles', false);

        $this->asVerifiedAdmin($viewer)->get(route('admin.staff.index'))->assertForbidden();
        $this->asVerifiedAdmin($viewer)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Staff & Roles');
    }

    /** @return array{StaffInvitation, string, string} */
    private function extractInvitationReveal($response, string $email): array
    {
        $invitation = StaffInvitation::query()->where('email', $email)->latest('id')->firstOrFail();
        $content = $response->getContent();

        $this->assertMatchesRegularExpression('/token=([A-Za-z0-9]{64})/', $content);
        preg_match('/token=([A-Za-z0-9]{64})/', $content, $matches);
        $token = $matches[1];

        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);

        $this->assertTrue(
            preg_match('/id="invitation-url"[^>]*value="([^"]+)"/', $content, $urlMatch) === 1
            || preg_match('/value="([^"]+)"[^>]*id="invitation-url"/', $content, $urlMatch) === 1
        );
        $acceptUrl = html_entity_decode($urlMatch[1], ENT_QUOTES);

        return [$invitation, $token, $acceptUrl];
    }

    private function signedUrl(StaffInvitation $invitation, string $token): string
    {
        return URL::temporarySignedRoute(
            'admin.staff-invitations.accept',
            $invitation->expires_at ?? now()->addHour(),
            ['invitation' => $invitation, 'token' => $token],
        );
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

    private function failInvitationMail(string $message = 'SMTP delivery failed'): void
    {
        $this->mock(StaffInvitationMailer::class, function ($mock) use ($message): void {
            $mock->shouldReceive('send')->andThrow(new RuntimeException($message));
        });
    }

    private function tokenFromSentMail(): string
    {
        $token = '';
        Mail::assertSent(StaffInvitationMail::class, function (StaffInvitationMail $mail) use (&$token): bool {
            preg_match('/token=([A-Za-z0-9]{64})/', $mail->acceptUrl, $matches);
            $token = $matches[1] ?? '';

            return true;
        });

        $this->assertSame(64, strlen($token));

        return $token;
    }

    /**
     * @param  list<MessageLogged>  $logs
     */
    private function assertSecretAbsentFromTransport(
        string $token,
        string $acceptUrl,
        array $logs,
        $response,
        bool $allowBody = false,
    ): void {
        $encodedLogs = json_encode($logs);
        $this->assertStringNotContainsString($token, $encodedLogs);
        $this->assertStringNotContainsString($acceptUrl, $encodedLogs);

        $session = json_encode(session()->all());
        $this->assertStringNotContainsString($token, $session);
        $this->assertStringNotContainsString($acceptUrl, $session);

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringNotContainsString($token, (string) $cookie->getValue());
            $this->assertStringNotContainsString($acceptUrl, (string) $cookie->getValue());
        }

        if (! $allowBody) {
            $this->assertStringNotContainsString($token, (string) $response->getContent());
            $this->assertStringNotContainsString($acceptUrl, (string) $response->getContent());
        }

        $this->assertDatabaseMissing('staff_invitations', ['token_hash' => $token]);
        $this->assertDatabaseMissing('staff_invitations', ['email' => $acceptUrl]);
    }

    private function assertNoThirdPartyAssetReferences(string $html): void
    {
        $allowedHosts = array_values(array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url(url('/'), PHP_URL_HOST),
        ]));

        preg_match_all(
            '/<(?:script|link|img|iframe|embed|object|source|video|audio)\b[^>]*(?:src|href)=["\']([^"\']+)["\']/i',
            $html,
            $matches,
        );

        foreach ($matches[1] as $reference) {
            if (! str_starts_with($reference, 'http://') && ! str_starts_with($reference, 'https://')) {
                $this->assertFalse(
                    str_contains($reference, 'cdn.tailwindcss.com'),
                    "Unexpected third-party reference: {$reference}",
                );

                continue;
            }

            $host = parse_url($reference, PHP_URL_HOST);
            $this->assertNotFalse($host);
            $this->assertContains(
                $host,
                $allowedHosts,
                "Third-party asset reference is not allowed: {$reference}",
            );
        }
    }

    private function assertFirstPartyCopyScriptHasNoNetworkBehavior(): void
    {
        $path = public_path('js/admin-invitation-reveal.js');
        $this->assertFileExists($path);
        $script = file_get_contents($path);
        $this->assertNotFalse($script);
        $this->assertStringContainsString('copy-invitation-link', $script);
        $this->assertStringContainsString('setSelectionRange', $script);
        $this->assertStringContainsString('data-staff-index-url', $script);
        $this->assertStringContainsString('replaceState({}, \'\', staffIndexUrl)', $script);
        $this->assertStringContainsString('isCleanStaffIndexUrl', $script);
        $this->assertStringContainsString("indexOf('token=')", $script);
        $this->assertDoesNotMatchRegularExpression('/replaceState\([^;]*\.value/', $script);
        $this->assertDoesNotMatchRegularExpression('/replaceState\([^;]*invitation-url/', $script);

        foreach ([
            'fetch(',
            'XMLHttpRequest',
            'sendBeacon',
            'navigator.sendBeacon',
            'window.open',
            'location.assign',
            'location.replace',
            'document.location',
            'window.location',
            'console.log',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script);
        }
    }
}
