<?php

// v1.0 — 2026-06-05 | Passwordless magic-link login — generates token, emails link, consumes on visit

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    /** POST /magic-link — validate email, generate token, send email. */
    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $key = 'magic-link:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('magic_link_status', 'Too many requests. Please wait a minute and try again.');
        }
        RateLimiter::hit($key, 60);

        $user = User::where('email', $request->input('email'))->first();

        // Always respond the same way — don't reveal whether the address is registered.
        if ($user) {
            // Invalidate any prior unused tokens for this user.
            MagicLinkToken::where('user_id', $user->id)->whereNull('used_at')->delete();

            $plain = Str::random(64);

            MagicLinkToken::create([
                'user_id'    => $user->id,
                'token_hash' => hash('sha256', $plain),
                'expires_at' => now()->addMinutes(15),
            ]);

            Mail::to($user->email)->send(new MagicLinkMail($user, $plain));
        }

        return back()->with('magic_link_status', 'If that email is registered you\'ll receive a login link shortly. Check your inbox — it expires in 15 minutes.');
    }

    /** GET /magic-link/{token} — validate token, log user in. */
    public function login(string $token): RedirectResponse
    {
        $key = 'magic-link-use:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Too many attempts.');
        }
        RateLimiter::hit($key, 60);

        $record = MagicLinkToken::where('token_hash', hash('sha256', $token))
            ->with('user')
            ->first();

        if (! $record || ! $record->isValid()) {
            return redirect()->route('login')
                ->with('status', 'This login link is invalid or has expired. Please request a new one.');
        }

        // Mark as used before logging in — prevents replay.
        $record->update(['used_at' => now()]);

        RateLimiter::clear($key);

        $user = $record->user;
        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
