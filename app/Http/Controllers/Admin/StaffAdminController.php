<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\StaffManagementService;
use App\Support\AdminRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'admin_role' => ['required', Rule::in(array_keys(AdminRole::labels()))],
        ]);

        $this->staff->invite(
            $request->user(),
            $validated['name'],
            $validated['email'],
            $validated['admin_role'],
        );

        return back()->with('success', 'Staff invitation sent securely.');
    }

    public function resend(Request $request, StaffInvitation $invitation): RedirectResponse
    {
        $this->staff->resend($request->user(), $invitation);

        return back()->with('success', 'A fresh invitation link was sent.');
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
}
