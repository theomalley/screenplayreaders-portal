<?php

// v1.0 — 2026-06-04 | Tokenized admin quick-login link for phone/browser bookmarks

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class QuickLoginController extends Controller
{
    private const TOKEN_KEY   = 'admin_quick_login_token';
    private const USER_ID_KEY = 'admin_quick_login_user_id';

    /** Public route — validates token and logs in. Rate-limited. */
    public function login(string $token)
    {
        $key = 'quick-login:' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Too many attempts. Try again later.');
        }

        RateLimiter::hit($key, 60);

        $stored = Setting::getValue(self::TOKEN_KEY);
        $userId = Setting::getValue(self::USER_ID_KEY);

        if (! $stored || ! $userId) {
            abort(404);
        }

        try {
            $plain = Crypt::decryptString($stored);
        } catch (\Throwable) {
            abort(404);
        }

        if (! hash_equals($plain, $token)) {
            abort(403, 'Invalid login link.');
        }

        $user = User::find($userId);

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        RateLimiter::clear($key);

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->route('assignments.index');
    }

    /** Admin: generate (or regenerate) the quick-login token. */
    public function generate()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $token = Str::random(64);

        Setting::setValue(self::TOKEN_KEY,   Crypt::encryptString($token));
        Setting::setValue(self::USER_ID_KEY, (string) auth()->id());

        return back()->with('success', 'Quick-login link generated.');
    }

    /** Admin: revoke the token, immediately invalidating any saved bookmarks. */
    public function revoke()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        Setting::setValue(self::TOKEN_KEY,   '');
        Setting::setValue(self::USER_ID_KEY, '');

        return back()->with('success', 'Quick-login link revoked.');
    }

    /** Retrieve the plain token for display (null if not set). */
    public static function currentToken(): ?string
    {
        $stored = Setting::getValue(self::TOKEN_KEY);
        if (! $stored) return null;

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }
}
