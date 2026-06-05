<?php

namespace App\Http\Middleware;

use App\Models\Setting;
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
            $timeoutMinutes = (int) Setting::getValue('session_timeout_minutes', 120);
            $lastActivity   = $request->session()->get('last_activity_at');

            if ($lastActivity !== null) {
                $idleMinutes = Carbon::createFromTimestamp($lastActivity)->diffInMinutes(now());

                if ($idleMinutes >= $timeoutMinutes) {
                    $wasAdmin = Auth::user()->isAdmin();

                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    if ($wasAdmin) {
                        $quickLoginUrl = \App\Http\Controllers\QuickLoginController::quickLoginUrl();
                        if ($quickLoginUrl) {
                            return redirect($quickLoginUrl);
                        }
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
