<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || ! auth()->user()->isAdmin() || ! auth()->user()->is_active) {
            return redirect()->route('admin.login');
        }

        if (! AdminAccess::verified($request)) {
            return redirect()->route('admin.login')
                ->with('info', 'Sign in at the admin login page to access the panel.');
        }

        return $next($request);
    }
}
