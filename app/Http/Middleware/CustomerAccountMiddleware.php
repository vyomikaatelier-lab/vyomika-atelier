<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerAccountMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('account.login')
                ->with('info', 'Please sign in to access your account.');
        }

        $user = auth()->user();

        if ($user->isAdmin()) {
            return redirect()->route('home')
                ->with('info', 'Customer account pages are for shoppers. Open /admin and sign in to manage the store.');
        }

        if (! $user->is_active) {
            Auth::logout();

            return redirect()->route('account.login')
                ->withErrors(['email' => 'This account has been disabled.']);
        }

        if (! $user->hasVerifiedPhone()) {
            return redirect()->route('account.verify');
        }

        return $next($request);
    }
}
