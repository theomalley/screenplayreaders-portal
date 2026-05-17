<?php

// v1.0 — 2026-05-17 | Admin/editor: view and edit reader profiles

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ReaderProfileController extends Controller
{
    public function edit(User $user)
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);
        abort_unless($user->isReader(), 404);

        return view('readers.edit', [
            'user'    => $user,
            'profile' => $user->readerProfile,
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);
        abort_unless($user->isReader(), 404);

        $data = $request->validate([
            'initials'                   => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'first_name'                 => ['required', 'string', 'max:100'],
            'last_name'                  => ['required', 'string', 'max:100'],
            'max_concurrent_assignments' => ['required', 'integer', 'min:0', 'max:20'],
            'paypal_email'               => ['nullable', 'email', 'max:255'],
        ]);

        $user->readerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        return redirect()->route('assignments.index')
            ->with('success', 'Reader profile updated.');
    }
}
