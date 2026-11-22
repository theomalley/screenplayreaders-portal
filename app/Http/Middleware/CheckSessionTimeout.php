<?php

// v1.1 — 2026-11-22 | FIX: Timed-out admins were restored via
//                     QuickLoginController::loginConfiguredAdmin() whenever ANY admin
//                     timed out, not just the specific admin configured for quick-login
//                     — a second admin account's timeout was silently swapped for the
//                     quick-login admin's identity instead of a normal logout. Now only
//                     auto-restores when the timed-out user IS the configured admin.
// v1.0 | Per-user inactivity timeout, with quick-login auto-restore for the configured admin.

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $timeoutMinutes = Auth::user()->getSessionTimeoutMinutes();
            $lastActivity   = $request->session()->get('last_activity_at');

            if ($lastActivity !== null) {
                $idleMinutes = Carbon::createFromTimestamp($lastActivity)->diffInMinutes(now());

                if ($idleMinutes >= $timeoutMinutes) {
                    $timedOutUserId = (int) Auth::id();
                    $wasAdmin       = Auth::user()->isAdmin();

                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    if (
                        $wasAdmin
                        && \App\Http\Controllers\QuickLoginController::isConfiguredAdminId($timedOutUserId)
                        && \App\Http\Controllers\QuickLoginController::loginConfiguredAdmin()
                    ) {
                        return redirect()->route(\App\Http\Controllers\QuickLoginController::landingRoute());
                    }

                    return redirect()->route('login')
                        ->with('status', 'Your session expired due to inactivity. Please log in again.');
                }
            }

            $request->session()->put('last_activity_at', now()->timestamp);
        }

        return $next($request);
    }
}
