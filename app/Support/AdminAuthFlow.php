<?php

namespace App\Support;

use App\Models\User;
use App\Services\StaffIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminAuthFlow
{
    private const FAIL_MESSAGE = 'Invalid email or password.';

    public function __construct(
        private readonly AdminMfa $mfa,
        private readonly StaffIdentityService $staffIdentities,
    ) {}

    /**
     * After password or passkey verification: enforce admin rules and MFA before panel access.
     */
    public function completeAdminLogin(Request $request, User $user, string $via, ?string $emailHint = null): RedirectResponse
    {
        if (! $user->isAdmin() || ! $user->is_active) {
            Log::warning('admin.login_failed', [
                'email' => $emailHint ?? $user->email,
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'reason' => 'not_admin_or_inactive',
                'via' => $via,
            ]);

            auth()->logout();
            AdminAccess::revoke($request);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => self::FAIL_MESSAGE]);
        }

        if ($via === 'passkey') {
            // Passkey verification authenticates the user on the pre-login
            // session id. Rotate it before any admin state is granted so a
            // session fixated before sign-in can never become an admin session.
            $request->session()->regenerate(true);
        }

        AdminAccess::revoke($request);
        $request->session()->forget([AdminMfa::SESSION_SETUP_SECRET, AdminMfa::SESSION_LAST_TOTP]);

        $email = strtolower(trim((string) ($emailHint ?? $user->email)));

        if ($this->mfa->hasMfaEnabled($user)) {
            if ($via === 'passkey') {
                $this->staffIdentities->recordLogin($user, $request->ip());
                AdminAccess::grant($request, $user);

                Log::info('admin.login_succeeded', [
                    'user_id' => $user->id,
                    'email' => $email,
                    'ip' => $request->ip(),
                    'mfa' => 'passkey',
                    'via' => $via,
                ]);

                return self::intendedAdminRedirect($request);
            }

            $request->session()->put(AdminMfa::SESSION_PENDING, $user->id);

            Log::info('admin.login_ok_mfa_required', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip(),
                'via' => $via,
            ]);

            return redirect()->route('admin.mfa.challenge');
        }

        if ($this->mfa->mustEnroll($user)) {
            $request->session()->put(AdminMfa::SESSION_PENDING, $user->id);

            Log::info('admin.login_ok_mfa_enroll_required', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip(),
                'via' => $via,
            ]);

            return redirect()->route('admin.mfa.enroll')
                ->with('info', 'Two-factor authentication is required for admin access.');
        }

        $this->staffIdentities->recordLogin($user, $request->ip());
        AdminAccess::grant($request, $user);

        Log::info('admin.login_succeeded', [
            'user_id' => $user->id,
            'email' => $email,
            'ip' => $request->ip(),
            'mfa' => 'grace',
            'via' => $via,
        ]);

        return self::intendedAdminRedirect($request)
            ->with('info', 'Please enroll two-factor authentication before '
                .optional($user->two_factor_grace_ends_at)->timezone(config('app.timezone'))->format('d M Y H:i').'.');
    }

    /**
     * Consume a stored intended URL only when it is a safe internal destination.
     *
     * redirect()->intended() replays whatever sits in url.intended without
     * checking it, so a planted value could send a freshly authenticated admin
     * off-site. Anything not provably internal falls back to the dashboard.
     */
    public static function intendedAdminRedirect(Request $request): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');

        // Backslashes are rejected outright: browsers normalize them to "/",
        // so "/\evil.test" would escape a path-only check.
        if (is_string($intended) && ! str_contains($intended, '\\') && SafeInternalUrl::isSafe($intended)) {
            return redirect()->to($intended);
        }

        return redirect()->route('admin.dashboard');
    }
}
