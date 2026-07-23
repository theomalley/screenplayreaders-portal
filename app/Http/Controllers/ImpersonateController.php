<?php

// v1.1 — 2026-07-23 | Role/target checks moved to UserPolicy::impersonate() (app/Policies),
//                     replacing two inline abort calls. The session-state preconditions
//                     (no self-impersonation, no nesting) stay inline — they're not really
//                     "authorization" so much as guard conditions. Covered by
//                     tests/Feature/ImpersonateControllerTest.php.
// v1.0 — 2026-06-04 | Admin impersonation — view portal as any non-admin user

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    public function start(User $user)
    {
        $this->authorize('impersonate', $user);
        abort_if($user->id === auth()->id(), 422);
        abort_unless(! session()->has('impersonator_id'), 422); // no nesting

        session(['impersonator_id' => auth()->id()]);

        Auth::loginUsingId($user->id, false);

        return redirect()->route('assignments.index');
    }

    public function stop()
    {
        $adminId = session('impersonator_id');
        abort_unless($adminId, 403);

        $admin = User::find($adminId);
        abort_unless($admin?->isAdmin(), 403);

        session()->forget('impersonator_id');

        Auth::loginUsingId($adminId, false);

        return redirect()->route('team.index')
            ->with('success', 'Returned to your admin account.');
    }
}
