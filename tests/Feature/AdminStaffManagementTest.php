<?php

namespace Tests\Feature;

use App\Mail\StaffInvitationMail;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_generate_a_one_time_invitation_link_without_sending_mail(): void
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

        $response->assertOk()
            ->assertSee('Invitation link ready', false)
            ->assertSee('Catalog Staff', false)
            ->assertSee('catalog@example.com', false)
            ->assertSee('Catalog Manager', false)
            ->assertSee('This link is shown only once. Share it only with the intended staff member.', false)
            ->assertSee('Copy invitation link', false);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        Mail::assertNothingSent();
        Mail::assertNotSent(StaffInvitationMail::class);

        [$invitation, $token, $acceptUrl] = $this->extractInvitationReveal($response, 'catalog@example.com');

        $this->assertTrue($invitation->isPending());
        $this->assertSame(AdminRole::CATALOG_MANAGER, $invitation->admin_role);
        $this->assertDatabaseMissing('users', ['email' => 'catalog@example.com']);
        $this->assertDatabaseMissing('staff_invitations', ['token_hash' => $token]);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertStringNotContainsString($token, $invitation->getAttributes()['token_hash']);
        $this->assertSame(0, StaffInvitation::query()->where('email', 'catalog@example.com')->whereNotNull('accepted_at')->count());

        $encodedLogs = json_encode($logs);
        $this->assertStringNotContainsString($token, $encodedLogs);
        $this->assertStringNotContainsString($acceptUrl, $encodedLogs);
        $this->assertTrue(collect($logs)->contains(fn (MessageLogged $event) => $event->message === 'admin.staff_invited'));

        $history = $this->asVerifiedAdmin($owner)->get(route('admin.staff.index'));
        $history->assertOk()
            ->assertSee('catalog@example.com', false)
            ->assertSee('Pending', false)
            ->assertDontSee($token)
            ->assertDontSee($acceptUrl, false);
    }

    public function test_invitation_reveal_is_isolated_first_party_and_not_recoverable_later(): void
    {
        Mail::fake();
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

        $this->assertStringNotContainsString('cdn.tailwindcss.com', $content);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)/i', $content);
        $this->assertStringContainsString('/js/admin-invitation-reveal.js', $content);
        $this->assertStringContainsString('/css/admin-invitation-reveal.css', $content);
        $this->assertDoesNotMatchRegularExpression(
            '/href=(["\'])[^"\']*'.preg_quote($token, '/').'[^"\']*\1/',
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

    public function test_refreshing_invitation_creation_does_not_duplicate_or_rotate_the_pending_link(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $payload = [
            'name' => 'Catalog Staff',
            'email' => 'catalog@example.com',
            'admin_role' => AdminRole::CATALOG_MANAGER,
        ];

        $first = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), $payload);
        [$invitation, $token] = $this->extractInvitationReveal($first, 'catalog@example.com');
        $hash = $invitation->token_hash;

        $this->asVerifiedAdmin($owner)
            ->from(route('admin.staff.index'))
            ->post(route('admin.staff.invite'), $payload)
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, StaffInvitation::query()->where('email', 'catalog@example.com')->count());
        $this->assertSame($hash, $invitation->fresh()->token_hash);
        $this->assertTrue($invitation->fresh()->isPending());
        $this->assertSame(hash('sha256', $token), $invitation->fresh()->token_hash);
    }

    public function test_regenerating_an_invitation_rotates_the_token_and_invalidates_the_old_link(): void
    {
        Mail::fake();
        $logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logs): void {
            $logs[] = $event;
        });

        $owner = $this->owner();
        $created = $this->asVerifiedAdmin($owner)->post(route('admin.staff.invite'), [
            'name' => 'Order Staff',
            'email' => 'orders@example.com',
            'admin_role' => AdminRole::ORDER_MANAGER,
        ]);
        [$invitation, $oldToken] = $this->extractInvitationReveal($created, 'orders@example.com');

        $regenerated = $this->asVerifiedAdmin($owner)
            ->post(route('admin.staff-invitations.resend', $invitation));

        $regenerated->assertOk()
            ->assertSee('Replacement invitation link', false)
            ->assertSee('This link is shown only once. Share it only with the intended staff member.', false);

        Mail::assertNothingSent();
        [, $newToken, $newUrl] = $this->extractInvitationReveal($regenerated, 'orders@example.com');

        $invitation->refresh();
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(hash('sha256', $newToken), $invitation->token_hash);
        $this->assertTrue($invitation->isPending());
        $this->assertDatabaseMissing('staff_invitations', ['token_hash' => $oldToken]);

        $oldAcceptUrl = $this->signedUrl($invitation, $oldToken);
        $newAcceptUrl = $this->signedUrl($invitation, $newToken);

        $this->post(route('admin.logout'));
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get($oldAcceptUrl)->assertForbidden();
        $this->get($newAcceptUrl)->assertOk()->assertSee('Set up your account');

        $encodedLogs = json_encode($logs);
        $this->assertStringNotContainsString($oldToken, $encodedLogs);
        $this->assertStringNotContainsString($newToken, $encodedLogs);
        $this->assertStringNotContainsString($newUrl, $encodedLogs);

        $this->asVerifiedAdmin($owner)->get(route('admin.staff.index'))
            ->assertOk()
            ->assertDontSee($newToken)
            ->assertDontSee($oldToken);
    }

    public function test_non_owner_cannot_invite_or_update_staff(): void
    {
        $administrator = User::factory()->admin()->create(['admin_role' => AdminRole::ADMINISTRATOR]);
        $staff = User::factory()->admin()->create(['admin_role' => AdminRole::VIEWER]);
        $invitation = StaffInvitation::query()->create([
            'name' => 'Pending Staff',
            'email' => 'pending@example.com',
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

        $this->asVerifiedAdmin($administrator)->patch(route('admin.staff.update', $staff), [
            'admin_role' => AdminRole::ORDER_MANAGER,
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('staff_invitations', ['email' => 'blocked@example.com']);
        $this->assertSame(hash('sha256', str_repeat('a', 64)), $invitation->fresh()->token_hash);
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
            ->assertSee('Generate invitation link');

        foreach ($matrix as $definition) {
            $response->assertSee($definition['label']);
            $response->assertSee($definition['description']);
            $response->assertSee($definition['group_label']);
        }

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
            ->assertDontSee('Generate invitation link');

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
