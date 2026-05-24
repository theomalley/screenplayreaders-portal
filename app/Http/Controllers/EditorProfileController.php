<?php

// v1.0.2 — 2026-05-24 | Add MIME allowlist to photo upload.
// v1.0.1 — 2026-05-24 | Force redeploy with editor_profiles migration.
// v1.0 — 2026-05-24 | CRUD for editor accounts and profiles — admin only.

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class EditorProfileController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $editors = User::where('role', 'editor')
            ->with('editorProfile')
            ->withCount([
                'assignments as active_count'    => fn($q) => $q->where('status', Assignment::STATUS_ASSIGNED),
                'assignments as completed_count' => fn($q) => $q->where('status', Assignment::STATUS_COMPLETED),
                'assignments as total_count',
            ])
            ->orderBy('name')
            ->get();

        return view('admin.editors.index', compact('editors'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('admin.editors.create');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'email'                => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'             => ['required', 'string', 'min:8', 'confirmed'],
            'initials'             => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'first_name'           => ['required', 'string', 'max:100'],
            'last_name'            => ['required', 'string', 'max:100'],
            'paypal_email'         => ['nullable', 'email', 'max:255'],
            'availability'         => ['required', 'in:available,unavailable'],
            'availability_message' => ['nullable', 'string', 'max:500'],
            'upload_warning'       => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'editor',
        ]);

        $user->editorProfile()->create([
            'initials'             => $data['initials'],
            'first_name'           => $data['first_name'],
            'last_name'            => $data['last_name'],
            'paypal_email'         => $data['paypal_email'] ?? null,
            'availability'         => $data['availability'],
            'availability_message' => $data['availability_message'] ?? null,
            'upload_warning'       => $data['upload_warning'] ?? null,
        ]);

        return redirect()->route('admin.editors.index')->with('success', 'Editor created.');
    }

    public function edit(User $user)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless($user->isEditor(), 404);

        return view('admin.editors.edit', [
            'user'    => $user,
            'profile' => $user->editorProfile,
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless($user->isEditor(), 404);

        $data = $request->validate([
            'initials'             => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'first_name'           => ['required', 'string', 'max:100'],
            'last_name'            => ['required', 'string', 'max:100'],
            'paypal_email'         => ['nullable', 'email', 'max:255'],
            'photo'                => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'email'                => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password'             => ['nullable', 'string', 'min:8', 'confirmed'],
            'availability'         => ['required', 'in:available,unavailable'],
            'availability_message' => ['nullable', 'string', 'max:500'],
            'upload_warning'       => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('photo')) {
            $profile = $user->editorProfile;
            if ($profile?->photo) {
                Storage::disk('public')->delete($profile->photo);
            }
            $data['photo'] = $request->file('photo')->store('editor-photos', 'public');
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

        $user->editorProfile()->updateOrCreate(
            ['user_id' => $user->id],
            collect($data)->except(['email', 'password', 'password_confirmation'])->toArray()
        );

        return redirect()->route('admin.editors.index')->with('success', 'Editor profile updated.');
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless($user->isEditor(), 404);

        if ($user->assignments()->where('status', Assignment::STATUS_ASSIGNED)->exists()) {
            return back()->with('error', 'Cannot delete an editor with active assignments.');
        }

        if ($user->editorProfile?->photo) {
            Storage::disk('public')->delete($user->editorProfile->photo);
        }

        $user->delete();

        return redirect()->route('admin.editors.index')->with('success', 'Editor deleted.');
    }
}
