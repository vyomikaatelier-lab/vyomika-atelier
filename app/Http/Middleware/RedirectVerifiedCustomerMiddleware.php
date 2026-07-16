<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectVerifiedCustomerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }
            if ($user->hasVerifiedPhone()) {
                return redirect()->route('account');
            }
        }

        return $next($request);
    }
}
