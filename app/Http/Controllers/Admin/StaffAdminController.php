<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\AdminRolePermissionService;
use App\Services\StaffManagementService;
use App\Support\AdminRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffAdminController extends Controller
{
    public function __construct(private readonly StaffManagementService $staff) {}

    public function index(): View
    {
        return view('admin.staff.index', $this->staffPageData());
    }

    public function invite(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'admin_role' => ['required', Rule::in(array_keys(AdminRole::labels()))],
        ]);

        $result = $this->staff->invite(
            $request->user(),
            $validated['name'],
            $validated['email'],
            $validated['admin_role'],
        );

        if ($result['email_sent']) {
            return redirect()
                ->route('admin.staff.index')
                ->with('success', 'Invitation sent');
        }

        return $this->invitationFallbackResponse($result['invitation'], $result['accept_url']);
    }

    public function resend(Request $request, StaffInvitation $invitation): Response|RedirectResponse
    {
        $result = $this->staff->regenerateLink($request->user(), $invitation);

        if ($result['email_sent']) {
            return redirect()
                ->route('admin.staff.index')
                ->with('success', 'Invitation sent');
        }

        return $this->invitationFallbackResponse($result['invitation'], $result['accept_url'], regenerated: true);
    }

    public function revokeInvitation(Request $request, StaffInvitation $invitation): RedirectResponse
    {
        $this->staff->revokeInvitation($request->user(), $invitation);

        return back()->with('success', 'Invitation revoked.');
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->isAdmin(), 404);

        $validated = $request->validate([
            'admin_role' => ['required', Rule::in(array_keys(AdminRole::labels()))],
            'is_active' => ['required', 'boolean'],
        ]);

        $this->staff->update(
            $request->user(),
            $staff,
            $validated['admin_role'],
            (bool) $validated['is_active'],
        );

        return back()->with('success', 'Staff access updated. Existing sessions were revoked when access changed.');
    }

    public function updateRolePermissions(Request $request, AdminRolePermissionService $permissions): RedirectResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        $request->validate([
            'permissions' => ['required', 'array'],
        ]);

        $applied = $permissions->update(
            $request->user(),
            is_array($request->input('permissions')) ? $request->input('permissions') : [],
        );

        if ($applied === 0) {
            return back()->with('success', 'No permission changes were needed.');
        }

        return back()->with('success', 'Role permissions updated. Existing sessions for affected roles were revoked.');
    }

    public function revokeSessions(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->isAdmin(), 404);
        $this->staff->revokeSessions($request->user(), $staff);

        return back()->with('success', 'All existing admin sessions for this staff member are now invalid.');
    }

    /**
     * First-party Staff & Roles result used when email cannot be delivered.
     *
     * The normal admin layout loads third-party Tailwind CDN JavaScript, so the
     * one-time invitation URL is never rendered inside that layout. This POST
     * response is a first-party-only representation of Staff & Roles with the
     * fallback panel at the top-right. The plain URL exists only in this
     * response body.
     */
    private function invitationFallbackResponse(
        StaffInvitation $invitation,
        string $acceptUrl,
        bool $regenerated = false,
    ): Response {
        return response()
            ->view('admin.staff.invitation-created', array_merge($this->staffPageData(), [
                'invitation' => $invitation,
                'acceptUrl' => $acceptUrl,
                'regenerated' => $regenerated,
            ]))
            ->header('Cache-Control', 'private, no-store, no-cache, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header(
                'Content-Security-Policy',
                "default-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; script-src 'self'; style-src 'self'; img-src 'none'; font-src 'none'; connect-src 'none'; object-src 'none'; media-src 'none'; worker-src 'none'; manifest-src 'none'"
            );
    }

    /** @return array<string, mixed> */
    private function staffPageData(): array
    {
        return [
            'staff' => User::query()->where('is_admin', true)->orderBy('name')->get(),
            'invitations' => StaffInvitation::query()->latest()->limit(50)->get(),
            'roles' => AdminRole::labels(),
            'roleMatrix' => AdminRole::permissionMatrix(),
            'permissionLabels' => AdminRole::permissionLabels(),
            'canEditRolePermissions' => (bool) auth()->user()?->isOwner(),
        ];
    }
}
