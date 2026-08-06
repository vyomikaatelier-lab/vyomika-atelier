<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use App\Support\AdminMfa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function __construct(private readonly AdminMfa $mfa) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || ! auth()->user()->isAdmin() || ! auth()->user()->is_active) {
            return redirect()->route('admin.login');
        }

        $user = auth()->user();

        // Password OK but MFA challenge/enroll still pending.
        if ($request->session()->has(AdminMfa::SESSION_PENDING)
            && (int) $request->session()->get(AdminMfa::SESSION_PENDING) === (int) $user->id
            && ! AdminAccess::verified($request)) {
            if ($this->mfa->hasMfaEnabled($user)) {
                return redirect()->route('admin.mfa.challenge');
            }

            return redirect()->route('admin.mfa.enroll');
        }

        if (! AdminAccess::verified($request)) {
            return redirect()->route('admin.login')
                ->with('info', 'Sign in at the admin login page to access the panel.');
        }

        // Grace expired while already in a session — force enrollment.
        if ($this->mfa->mustEnroll($user)) {
            return redirect()->route('admin.mfa.enroll')
                ->with('info', 'Two-factor authentication enrollment is required.');
        }

        return $next($request);
    }
}
