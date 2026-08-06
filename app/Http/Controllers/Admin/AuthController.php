<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAccess;
use App\Support\AdminMfa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    private const FAIL_MESSAGE = 'Invalid email or password.';

    public function __construct(private readonly AdminMfa $mfa) {}

    public function showLogin()
    {
        if (auth()->check() && auth()->user()->isAdmin() && AdminAccess::verified(request())) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = strtolower(trim($credentials['email']));

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            Log::warning('admin.login_failed', [
                'email' => $email,
                'ip' => $request->ip(),
                'reason' => 'invalid_credentials',
            ]);

            return back()->withErrors(['email' => self::FAIL_MESSAGE])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = auth()->user();

        if (! $user->isAdmin() || ! $user->is_active) {
            Log::warning('admin.login_failed', [
                'email' => $email,
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'reason' => 'not_admin_or_inactive',
            ]);

            Auth::logout();
            AdminAccess::revoke($request);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => self::FAIL_MESSAGE])->onlyInput('email');
        }

        // Password OK — do not grant full admin access until MFA rules are satisfied.
        AdminAccess::revoke($request);
        $request->session()->forget([AdminMfa::SESSION_SETUP_SECRET, AdminMfa::SESSION_LAST_TOTP]);

        if ($this->mfa->hasMfaEnabled($user)) {
            $request->session()->put(AdminMfa::SESSION_PENDING, $user->id);

            Log::info('admin.login_password_ok_mfa_required', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('admin.mfa.challenge');
        }

        if ($this->mfa->mustEnroll($user)) {
            $request->session()->put(AdminMfa::SESSION_PENDING, $user->id);

            Log::info('admin.login_password_ok_mfa_enroll_required', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('admin.mfa.enroll')
                ->with('info', 'Two-factor authentication is required for admin access.');
        }

        // Within grace: allow panel access and nudge enrollment.
        AdminAccess::grant($request);

        Log::info('admin.login_succeeded', [
            'user_id' => $user->id,
            'email' => $email,
            'ip' => $request->ip(),
            'mfa' => 'grace',
        ]);

        return redirect()
            ->intended(route('admin.dashboard'))
            ->with('info', 'Please enroll two-factor authentication before '
                .optional($user->two_factor_grace_ends_at)->timezone(config('app.timezone'))->format('d M Y H:i').'.');
    }

    public function logout(Request $request)
    {
        $userId = auth()->id();

        AdminAccess::revoke($request);
        $request->session()->forget([
            AdminMfa::SESSION_PENDING,
            AdminMfa::SESSION_SETUP_SECRET,
            AdminMfa::SESSION_LAST_TOTP,
        ]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('admin.logout', [
            'user_id' => $userId,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('admin.login');
    }
}
