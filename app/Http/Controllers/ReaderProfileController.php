<?php

// v1.1 — 2026-05-18 | Full CRUD: index, create, store, edit, update, destroy

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ReaderProfileController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $readers = User::where('role', 'reader')
            ->with('readerProfile')
            ->withCount([
                'assignments as active_count'    => fn($q) => $q->where('status', Assignment::STATUS_ASSIGNED),
                'assignments as completed_count' => fn($q) => $q->where('status', Assignment::STATUS_COMPLETED),
                'assignments as total_count',
            ])
            ->orderBy('name')
            ->get();

        return view('readers.index', compact('readers'));
    }

    public function create()
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        return view('readers.create');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'email'                      => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'                   => ['required', 'string', 'min:8', 'confirmed'],
            'initials'                   => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'first_name'                 => ['required', 'string', 'max:100'],
            'last_name'                  => ['required', 'string', 'max:100'],
            'max_concurrent_assignments' => ['required', 'integer', 'min:0', 'max:20'],
            'paypal_email'               => ['nullable', 'email', 'max:255'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'reader',
        ]);

        $user->readerProfile()->create([
            'initials'                   => $data['initials'],
            'first_name'                 => $data['first_name'],
            'last_name'                  => $data['last_name'],
            'max_concurrent_assignments' => $data['max_concurrent_assignments'],
            'paypal_email'               => $data['paypal_email'] ?? null,
        ]);

        return redirect()->route('readers.index')->with('success', 'Reader created.');
    }

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
            'photo'                      => ['nullable', 'image', 'max:4096'],
            'email'                      => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password'                   => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($request->hasFile('photo')) {
            $profile = $user->readerProfile;
            if ($profile?->photo) {
                Storage::disk('public')->delete($profile->photo);
            }
            $data['photo'] = $request->file('photo')->store('reader-photos', 'public');
        } else {
            unset($data['photo']);
        }

        $userUpdate = [
            'name'  => $data['first_name'] . ' ' . $data['last_name'],
            'email' => $data['email'],
        ];
        if (!empty($data['password'])) {
            $userUpdate['password'] = Hash::make($data['password']);
        }
        $user->update($userUpdate);

        $user->readerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            collect($data)->except(['email', 'password', 'password_confirmation'])->toArray()
        );

        return redirect()->route('readers.index')->with('success', 'Reader profile updated.');
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);
        abort_unless($user->isReader(), 404);

        if ($user->assignments()->where('status', Assignment::STATUS_ASSIGNED)->exists()) {
            return back()->with('error', 'Cannot delete a reader with active assignments.');
        }

        $user->delete();

        return redirect()->route('readers.index')->with('success', 'Reader deleted.');
    }
}
