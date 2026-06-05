<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\TrackLastSeen::class,
            \App\Http\Middleware\CheckSessionTimeout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // When a session has no valid auth, redirect to quick-login URL if configured
        $exceptions->unauthenticated(function (
            \Illuminate\Http\Request $request,
            \Illuminate\Auth\AuthenticationException $e
        ) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $quickLoginUrl = \App\Http\Controllers\QuickLoginController::quickLoginUrl();
            if ($quickLoginUrl) {
                return redirect($quickLoginUrl);
            }

            return redirect()->guest(route('login'));
        });
    })->create();
