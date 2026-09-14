<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use App\Models\User;
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
        return view('admin.staff.index', [
            'staff' => User::query()->where('is_admin', true)->orderBy('name')->get(),
            'invitations' => StaffInvitation::query()->latest()->limit(50)->get(),
            'roles' => AdminRole::labels(),
            'roleMatrix' => AdminRole::permissionMatrix(),
            'permissionLabels' => AdminRole::permissionLabels(),
        ]);
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

        return $this->invitationRevealResponse($result['invitation'], $result['accept_url']);
    }

    public function resend(Request $request, StaffInvitation $invitation): Response|RedirectResponse
    {
        $result = $this->staff->regenerateLink($request->user(), $invitation);

        return $this->invitationRevealResponse($result['invitation'], $result['accept_url'], regenerated: true);
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

    public function revokeSessions(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->isAdmin(), 404);
        $this->staff->revokeSessions($request->user(), $staff);

        return back()->with('success', 'All existing admin sessions for this staff member are now invalid.');
    }

    private function invitationRevealResponse(
        StaffInvitation $invitation,
        string $acceptUrl,
        bool $regenerated = false,
    ): Response {
        return response()
            ->view('admin.staff.invitation-created', [
                'invitation' => $invitation,
                'acceptUrl' => $acceptUrl,
                'roles' => AdminRole::labels(),
                'regenerated' => $regenerated,
            ])
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }
}
