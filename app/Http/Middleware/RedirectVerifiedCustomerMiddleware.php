<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectVerifiedCustomerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            if ($user->isAdmin()) {
                AdminAccess::revoke($request);
                Auth::logout();

                return $next($request);
            }

            if ($user->hasVerifiedPhone()) {
                return redirect()->route('account');
            }
        }

        return $next($request);
    }
}
