<?php

// v1.7 — 2026-06-03 | Add custom_message to update validation/save
// v1.6 — 2026-05-27 | Enforce Password::defaults() (min 12, mixed case, numbers, symbols) on create/update
// v1.5 — 2026-05-27 | Gate edit/delete on readers.edit / readers.delete permissions; redirect to team.index
// v1.4 — 2026-05-25 | Add requests_bypass_capacity to store/update.
// v1.3 — 2026-05-24 | Add MIME allowlist to photo upload; delete photo file on reader destroy.
// v1.2 — 2026-05-24 | Add availability + availability_message to store/update
// v1.1 — 2026-05-18 | Full CRUD: index, create, store, edit, update, destroy

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

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
            'password'                   => ['required', 'confirmed', Password::defaults()],
            'initials'                   => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'first_name'                 => ['required', 'string', 'max:100'],
            'last_name'                  => ['required', 'string', 'max:100'],
            'max_concurrent_assignments' => ['required', 'integer', 'min:0', 'max:20'],
            'paypal_email'               => ['nullable', 'email', 'max:255'],
            'availability'               => ['required', 'in:available,unavailable'],
            'availability_message'       => ['nullable', 'string', 'max:500'],
            'upload_warning'             => ['nullable', 'string', 'max:1000'],
        ]);

        $data['requests_bypass_capacity'] = $request->boolean('requests_bypass_capacity');

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
            'max_concurrent_assignments'  => $data['max_concurrent_assignments'],
            'requests_bypass_capacity'    => $data['requests_bypass_capacity'],
            'paypal_email'                => $data['paypal_email'] ?? null,
            'availability'                => $data['availability'],
            'availability_message'        => $data['availability_message'] ?? null,
        ]);

        return redirect()->route('team.index')->with('success', 'Reader created.');
    }

    public function edit(User $user)
    {
        abort_unless(Permission::check('readers.edit'), 403);
        abort_unless($user->isReader(), 404);

        return view('readers.edit', [
            'user'    => $user,
            'profile' => $user->readerProfile,
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(Permission::check('readers.edit'), 403);
        abort_unless($user->isReader(), 404);

        $data = $request->validate([
            'initials'                   => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'first_name'                 => ['required', 'string', 'max:100'],
            'last_name'                  => ['required', 'string', 'max:100'],
            'title'                      => ['nullable', 'string', 'max:100'],
            'max_concurrent_assignments' => ['required', 'integer', 'min:0', 'max:20'],
            'paypal_email'               => ['nullable', 'email', 'max:255'],
            'photo'                      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=600'],
            'email'                      => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password'                   => ['nullable', 'confirmed', Password::defaults()],
            'availability'               => ['required', 'in:available,unavailable'],
            'availability_message'       => ['nullable', 'string', 'max:500'],
            'upload_warning'             => ['nullable', 'string', 'max:1000'],
            'bio'                        => ['nullable', 'string', 'max:5000'],
            'custom_message'             => ['nullable', 'string', 'max:200'],
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

        $data['requests_bypass_capacity'] = $request->boolean('requests_bypass_capacity');
        $data['is_1099']                  = $request->boolean('is_1099');

        $user->readerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            collect($data)->except(['email', 'password', 'password_confirmation'])->toArray()
        );

        return redirect()->route('team.index')->with('success', 'Reader profile updated.');
    }

    public function destroy(User $user)
    {
        abort_unless(Permission::check('readers.delete'), 403);
        abort_unless($user->isReader(), 404);

        if ($user->assignments()->where('status', Assignment::STATUS_ASSIGNED)->exists()) {
            return back()->with('error', 'Cannot delete a reader with active assignments.');
        }

        if ($user->readerProfile?->photo) {
            Storage::disk('public')->delete($user->readerProfile->photo);
        }

        $user->delete();

        return redirect()->route('team.index')->with('success', 'Reader deleted.');
    }
}
