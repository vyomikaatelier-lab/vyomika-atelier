<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\AdminRolePermissionService;
use App\Services\StaffManagementService;
use App\Support\AdminRole;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffAdminController extends Controller
{
    public function __construct(private readonly StaffManagementService $staff) {}

    public function index(Request $request): View
    {
        return view('admin.staff.index', $this->staffPageData($request));
    }

    public function invitations(): View
    {
        return view('admin.staff.invitations', $this->invitationsPageData());
    }

    public function invite(Request $request): Response|RedirectResponse
    {
        try {
            if ($request->input('staff_invitation_action') === 'regenerate') {
                $validated = $request->validate([
                    'invitation_id' => ['required', 'integer'],
                ]);

                $invitation = StaffInvitation::query()->findOrFail($validated['invitation_id']);

                return $this->resend($request, $invitation);
            }

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
        } catch (ValidationException $exception) {
            return $this->redirectInvitationValidation($exception);
        }

        if ($result['email_sent']) {
            return redirect()
                ->route('admin.staff.invitations.index')
                ->with('success', 'Invitation sent');
        }

        return $this->invitationFallbackResponse($result['invitation'], $result['accept_url']);
    }

    public function resend(Request $request, StaffInvitation $invitation): Response|RedirectResponse
    {
        try {
            $result = $this->staff->regenerateLink($request->user(), $invitation);
        } catch (ValidationException $exception) {
            return $this->redirectInvitationValidation($exception);
        }

        if ($result['email_sent']) {
            return redirect()
                ->route('admin.staff.invitations.index')
                ->with('success', 'Invitation sent');
        }

        return $this->invitationFallbackResponse($result['invitation'], $result['accept_url'], regenerated: true);
    }

    public function revokeInvitation(Request $request, StaffInvitation $invitation): RedirectResponse
    {
        $this->staff->revokeInvitation($request->user(), $invitation);

        return redirect()
            ->route('admin.staff.invitations.index')
            ->with('success', 'Invitation revoked.');
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
            'permissions' => [
                'required',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    foreach ($value as $roleValues) {
                        if (! is_array($roleValues)) {
                            $fail('Each permission switch must be 0 or 1.');

                            return;
                        }

                        foreach ($roleValues as $enabled) {
                            if (! in_array($enabled, AdminRolePermissionService::ACCEPTED_ENABLED, true)) {
                                $fail('Each permission switch must be 0 or 1.');

                                return;
                            }
                        }
                    }
                },
            ],
            'permissions.*' => ['array'],
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
     * First-party Staff Invitations result used when email cannot be delivered.
     *
     * The normal admin layout loads third-party Tailwind CDN JavaScript, so the
     * one-time invitation URL is never rendered inside that layout. This POST
     * response is a first-party-only representation of Staff Invitations with
     * the fallback panel beside that page. The plain URL exists only in this
     * response body.
     */
    private function invitationFallbackResponse(
        StaffInvitation $invitation,
        string $acceptUrl,
        bool $regenerated = false,
    ): Response {
        return response()
            ->view('admin.staff.invitation-created', array_merge($this->invitationsPageData(), [
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
    private function staffPageData(?Request $request = null): array
    {
        $request ??= request();
        $selectedRole = (string) $request->query('role', AdminRole::OWNER);
        if (! AdminRole::isValid($selectedRole)) {
            $selectedRole = AdminRole::OWNER;
        }

        return [
            'staff' => User::query()->where('is_admin', true)->orderBy('name')->get(),
            'roles' => AdminRole::labels(),
            'roleMatrix' => AdminRole::permissionMatrix(),
            'permissionLabels' => AdminRole::permissionLabels(),
            'permissionGroups' => AdminRole::permissionGroups(),
            'permissionExplanations' => AdminRole::permissionExplanations(),
            'canEditRolePermissions' => (bool) auth()->user()?->isOwner(),
            'selectedRole' => $selectedRole,
        ];
    }

    /** @return array<string, mixed> */
    private function invitationsPageData(): array
    {
        return [
            'invitations' => StaffInvitation::query()->latest()->limit(50)->get(),
            'roles' => AdminRole::labels(),
        ];
    }

    private function redirectInvitationValidation(ValidationException $exception): RedirectResponse
    {
        return redirect()
            ->route('admin.staff.invitations.index')
            ->withErrors($exception->validator)
            ->withInput();
    }
}
