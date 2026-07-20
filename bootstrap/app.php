<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'customer' => \App\Http\Middleware\CustomerAccountMiddleware::class,
            'customer.guest' => \App\Http\Middleware\RedirectVerifiedCustomerMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CaptureAttribution::class,
        ]);

        // Razorpay redirect callback POSTs without a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'checkout/pay/*',
            'webhooks/razorpay',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
