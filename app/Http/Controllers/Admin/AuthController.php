<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAccess;
use App\Support\AdminAuthFlow;
use App\Support\AdminMfa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    private const FAIL_MESSAGE = 'Invalid email or password.';

    public function __construct(private readonly AdminAuthFlow $authFlow) {}

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

        return $this->authFlow->completeAdminLogin($request, auth()->user(), 'password', $email);
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
