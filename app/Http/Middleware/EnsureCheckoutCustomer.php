<?php

namespace App\Http\Middleware;

use App\Support\CheckoutCustomer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCheckoutCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('account.login'))
                ->with('info', CheckoutCustomer::MSG_SIGN_IN);
        }

        if ($user->isAdmin()) {
            return redirect()->route('cart.index')
                ->with('error', CheckoutCustomer::MSG_ADMIN);
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('account.login')
                ->withErrors(['email' => CheckoutCustomer::MSG_DISABLED]);
        }

        if (! $user->hasVerifiedPhone()) {
            return redirect()->route('account.verify')
                ->with('info', CheckoutCustomer::MSG_VERIFY);
        }

        return $next($request);
    }
}
