<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use App\Services\StaffManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffInvitationController extends Controller
{
    public function show(Request $request, StaffInvitation $invitation): View
    {
        abort_unless($invitation->isPending(), 410, 'This invitation is no longer available.');
        $token = (string) $request->query('token');
        abort_unless(
            strlen($token) === 64
                && hash_equals($invitation->token_hash, hash('sha256', $token)),
            403,
        );

        return view('admin.staff.accept', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    public function store(
        Request $request,
        StaffInvitation $invitation,
        StaffManagementService $staff,
    ): RedirectResponse {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $staff->accept($invitation, $validated['token'], $validated['password']);

        return redirect()->route('admin.login')
            ->with('success', 'Your staff account is ready. Sign in and complete two-factor authentication.');
    }
}
