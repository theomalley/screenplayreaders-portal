<?php

// v1.0 — 2026-06-04 | Admin impersonation — view portal as any non-admin user

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    public function start(User $user)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($user->isAdmin(), 403);
        abort_if($user->id === auth()->id(), 422);
        abort_unless(! session()->has('impersonator_id'), 422); // no nesting

        session(['impersonator_id' => auth()->id()]);

        Auth::loginUsingId($user->id, false);

        // Redirect to a page that is always accessible for the role,
        // bypassing configurable permission gates that may block the user.
        $landing = $user->role === 'reader'
            ? route('reader-earnings.index')   // always accessible, no permission gate
            : route('assignments.index');       // editors always have assignments permission

        return redirect($landing);
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
