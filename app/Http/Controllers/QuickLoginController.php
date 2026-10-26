<?php

// v1.3 — 2026-10-26 | Session-timeout handling no longer redirects through the public
//                     /ql/{token} URL (which put the permanent, un-expiring token in
//                     every proxy/access log on every timeout). CheckSessionTimeout now
//                     calls loginConfiguredAdmin()/landingRoute() directly, server-side,
//                     so the plaintext token never has to appear in a URL for this path.
// v1.2 — 2026-07-23 | Authorization moved to the manage-quick-login Gate ability
//                     (AppServiceProvider), replacing inline abort_unless(isAdmin())
//                     calls on saveLanding/generate/revoke. Covered by
//                     tests/Feature/QuickLoginAdminActionsTest.php.
// v1.1 — 2026-07-23 | Added a per-token rate limit alongside the existing per-IP one,
//                     so a single leaked/guessed token can't be hammered across many IPs.
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
    private const TOKEN_KEY    = 'admin_quick_login_token';
    private const USER_ID_KEY  = 'admin_quick_login_user_id';
    private const LANDING_KEY  = 'admin_quick_login_landing';

    public const LANDING_OPTIONS = [
        'assignments.index' => 'Assignments',
        'qc.index'          => 'QC Queue',
        'archive.index'     => 'Archive',
        'revenue.index'     => 'Revenue',
        'payroll.index'     => 'Payroll',
        'team.index'        => 'Team',
    ];

    /**
     * Public route — validates token and logs in. Rate-limited both by IP
     * (protects a shared office/VPN IP from being exhausted by unrelated
     * attempts) and by the token value itself (bounds abuse of one specific
     * leaked/guessed token across many IPs — an IP-only limit wouldn't
     * catch that).
     */
    public function login(string $token)
    {
        $ipKey    = 'quick-login-ip:' . request()->ip();
        $tokenKey = 'quick-login-token:' . hash('sha256', $token);

        if (RateLimiter::tooManyAttempts($ipKey, 10) || RateLimiter::tooManyAttempts($tokenKey, 10)) {
            abort(429, 'Too many attempts. Try again later.');
        }

        RateLimiter::hit($ipKey, 60);
        RateLimiter::hit($tokenKey, 60);

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

        RateLimiter::clear($ipKey);
        RateLimiter::clear($tokenKey);

        // Clear any active impersonation so the bookmark always lands you as yourself
        session()->forget('impersonator_id');

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->route(self::landingRoute());
    }

    /** Admin: save the landing-page preference. */
    public function saveLanding(\Illuminate\Http\Request $request)
    {
        $this->authorize('manage-quick-login');

        $landing = $request->input('landing', 'assignments.index');
        if (! array_key_exists($landing, self::LANDING_OPTIONS)) {
            $landing = 'assignments.index';
        }

        Setting::setValue(self::LANDING_KEY, $landing);

        return back()->with('success', 'Quick-login landing page saved.');
    }

    /** Admin: generate (or regenerate) the quick-login token. */
    public function generate()
    {
        $this->authorize('manage-quick-login');

        $token = Str::random(64);

        Setting::setValue(self::TOKEN_KEY,   Crypt::encryptString($token));
        Setting::setValue(self::USER_ID_KEY, (string) auth()->id());

        return back()->with('success', 'Quick-login link generated.');
    }

    /** Admin: revoke the token, immediately invalidating any saved bookmarks. */
    public function revoke()
    {
        $this->authorize('manage-quick-login');

        Setting::setValue(self::TOKEN_KEY,   '');
        Setting::setValue(self::USER_ID_KEY, '');

        return back()->with('success', 'Quick-login link revoked.');
    }

    /**
     * Log the configured quick-login admin back in directly, server-side —
     * no token is read from or written into a URL, so nothing sensitive
     * reaches the browser bar, history, or access/proxy logs. Used by
     * CheckSessionTimeout so a timed-out admin is restored without ever
     * round-tripping through the public /ql/{token} endpoint.
     */
    public static function loginConfiguredAdmin(): bool
    {
        $userId = Setting::getValue(self::USER_ID_KEY);
        if (! $userId) {
            return false;
        }

        $user = User::find($userId);
        if (! $user || ! $user->isAdmin()) {
            return false;
        }

        session()->forget('impersonator_id');
        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return true;
    }

    /** The route name the quick-login flow lands on. */
    public static function landingRoute(): string
    {
        $landing = Setting::getValue(self::LANDING_KEY, 'assignments.index');
        if (! array_key_exists($landing, self::LANDING_OPTIONS)) {
            $landing = 'assignments.index';
        }

        return $landing;
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
