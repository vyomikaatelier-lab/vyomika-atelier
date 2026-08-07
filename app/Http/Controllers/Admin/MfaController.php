<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAccess;
use App\Support\AdminMfa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function __construct(private readonly AdminMfa $mfa) {}

    public function showChallenge(Request $request)
    {
        $user = $this->pendingAdmin($request);
        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! $this->mfa->hasMfaEnabled($user)) {
            return redirect()->route('admin.mfa.enroll');
        }

        return view('admin.auth.mfa-challenge');
    }

    public function challenge(Request $request)
    {
        $user = $this->pendingAdmin($request);
        if (! $user) {
            return redirect()->route('admin.login');
        }

        $validated = $request->validate([
            'code' => 'required|string|max:64',
        ]);

        $code = $validated['code'];
        $ok = $this->mfa->verifyTotp($user, $code)
            || $this->mfa->consumeRecoveryCode($user, $code);

        if (! $ok) {
            Log::warning('admin.mfa_challenge_failed', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'code' => 'Invalid authentication or recovery code.',
            ]);
        }

        $request->session()->forget(AdminMfa::SESSION_PENDING);
        AdminAccess::grant($request);

        Log::info('admin.mfa_challenge_succeeded', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function showEnroll(Request $request)
    {
        $user = auth()->user();
        if (! $user?->isAdmin() || ! $user->is_active) {
            return redirect()->route('admin.login');
        }

        if ($this->mfa->hasMfaEnabled($user) && AdminAccess::verified($request)) {
            return redirect()->route('admin.dashboard');
        }

        // Password-authenticated but not yet MFA-verified may enroll.
        if (! auth()->check()) {
            return redirect()->route('admin.login');
        }

        $secret = $request->session()->get(AdminMfa::SESSION_SETUP_SECRET);
        if (! filled($secret)) {
            $secret = $this->mfa->generateSecret();
            $request->session()->put(AdminMfa::SESSION_SETUP_SECRET, $secret);
        }

        $otpAuth = $this->mfa->qrUrl($user, $secret);

        return view('admin.auth.mfa-enroll', [
            'secret' => $secret,
            'qrDataUri' => $this->mfa->qrSvgDataUri($otpAuth),
            'graceEndsAt' => $user->two_factor_grace_ends_at,
            'mustEnroll' => $this->mfa->mustEnroll($user),
        ]);
    }

    public function enroll(Request $request)
    {
        $user = auth()->user();
        if (! $user?->isAdmin() || ! $user->is_active) {
            return redirect()->route('admin.login');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => 'required|string|max:16',
        ]);

        $secret = $request->session()->get(AdminMfa::SESSION_SETUP_SECRET);
        if (! filled($secret)) {
            return redirect()->route('admin.mfa.enroll')
                ->with('error', 'Enrollment session expired. Scan a new QR code.');
        }

        if (! $this->mfa->verifyTotp($user, $validated['code'], $secret)) {
            Log::warning('admin.mfa_enroll_failed', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'code' => 'Invalid authenticator code. Try again.',
            ]);
        }

        $recoveryCodes = $this->mfa->generateRecoveryCodes();
        $this->mfa->enable($user, $secret, $recoveryCodes);

        $request->session()->forget([
            AdminMfa::SESSION_SETUP_SECRET,
            AdminMfa::SESSION_PENDING,
            AdminMfa::SESSION_LAST_TOTP,
        ]);
        AdminAccess::grant($request);

        Log::info('admin.mfa_enrolled', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.mfa.recovery')
            ->with('recovery_codes', $recoveryCodes)
            ->with('success', 'Two-factor authentication is enabled. Store your recovery codes.');
    }

    public function showRecovery(Request $request)
    {
        if (! auth()->user()?->isAdmin() || ! AdminAccess::verified($request)) {
            return redirect()->route('admin.login');
        }

        $codes = $request->session()->get('recovery_codes', []);

        return view('admin.auth.mfa-recovery', [
            'codes' => is_array($codes) ? $codes : [],
        ]);
    }

    public function showManage(Request $request)
    {
        $user = auth()->user();
        if (! $user?->isAdmin() || ! AdminAccess::verified($request)) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.mfa-manage', [
            'enabled' => $this->mfa->hasMfaEnabled($user),
            'confirmedAt' => $user->two_factor_confirmed_at,
        ]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = auth()->user();
        if (! $user?->isAdmin() || ! AdminAccess::verified($request)) {
            return redirect()->route('admin.login');
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        if (! $this->mfa->hasMfaEnabled($user)) {
            return redirect()->route('admin.mfa.enroll');
        }

        $codes = $this->mfa->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $this->mfa->hashRecoveryCodes($codes),
        ])->save();

        Log::info('admin.mfa_recovery_regenerated', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.mfa.recovery')
            ->with('recovery_codes', $codes)
            ->with('success', 'New recovery codes generated. Previous codes no longer work.');
    }

    public function disable(Request $request)
    {
        $user = auth()->user();
        if (! $user?->isAdmin() || ! AdminAccess::verified($request)) {
            return redirect()->route('admin.login');
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        if (! $this->mfa->hasMfaEnabled($user)) {
            return redirect()->route('admin.mfa.enroll');
        }

        $this->mfa->disable($user);
        $request->session()->forget([
            AdminMfa::SESSION_SETUP_SECRET,
            AdminMfa::SESSION_LAST_TOTP,
        ]);

        Log::warning('admin.mfa_disabled', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.mfa.enroll')
            ->with('info', 'MFA disabled. Re-enroll two-factor authentication to continue using the admin panel.');
    }

    private function pendingAdmin(Request $request)
    {
        $userId = $request->session()->get(AdminMfa::SESSION_PENDING);
        if (! $userId || ! auth()->check() || (int) auth()->id() !== (int) $userId) {
            return null;
        }

        $user = auth()->user();
        if (! $user->isAdmin() || ! $user->is_active) {
            return null;
        }

        return $user;
    }
}
