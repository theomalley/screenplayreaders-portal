<?php

// v1.12 — 2026-07-20 | Replace tier_0/tier_1/tier_2 checkbox handling with
//                      $readerProfile->tiers()->sync() against the dynamic Tier model — also
//                      fixes the old bug where tier_0 was silently cleared on every save because
//                      the form never submitted it.
// v1.11 — 2026-07-11 | Add tier_0 (onboarding) — mutually exclusive with tier_1/tier_2;
//                      checking either of those clears tier_0.
// v1.10 — 2026-06-23 | Save is_test flag on user when admin edits reader profile
// v1.9 — 2026-06-23 | Role change (reader→editor) preserves all shared profile data; removed duplicate role-change block
// v1.8 — 2026-06-12 | Sanitize bio on save: HTML allowlist for admins, plain text for everyone else
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
use App\Support\Html;
use App\Support\Permission;
use Illuminate\Http\Request;
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
            'password' => $data['password'],
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

        $profile = $user->readerProfile;

        return view('profile.edit', [
            'user'                 => $user,
            'profile'              => $profile,
            'isSelf'               => false,
            'pendingAboutPhotoUrl' => $profile?->about_photo_pending ? asset('storage/' . $profile->about_photo_pending) : null,
            'pendingBioContent'    => $profile?->bio_pending,
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(Permission::check('readers.edit'), 403);
        abort_unless($user->isReader(), 404);

        if (auth()->user()->isAdmin() && $request->input('_action') === 'role_change') {
            abort_unless($request->input('role') === 'editor', 422);

            $reader  = $user->readerProfile;
            $nameParts = explode(' ', $user->name, 2);

            $shared = [
                'initials'                   => $reader?->initials ?? strtoupper(substr($user->name, 0, 2)),
                'first_name'                 => $reader?->first_name ?? ($nameParts[0] ?? ''),
                'last_name'                  => $reader?->last_name ?? ($nameParts[1] ?? ''),
                'title'                      => $reader?->title,
                'bio'                        => $reader?->bio,
                'bio_pending'                => $reader?->bio_pending,
                'bio_rejection_note'         => $reader?->bio_rejection_note,
                'custom_message'             => $reader?->custom_message,
                'photo'                      => $reader?->photo,
                'photo_pending'              => $reader?->photo_pending,
                'photo_rejection_note'       => $reader?->photo_rejection_note,
                'about_photo'                => $reader?->about_photo,
                'about_photo_pending'        => $reader?->about_photo_pending,
                'about_photo_rejection_note' => $reader?->about_photo_rejection_note,
                'paypal_email'               => $reader?->paypal_email,
                'availability'               => $reader?->availability ?? 'available',
                'availability_message'       => $reader?->availability_message,
                'upload_warning'             => $reader?->upload_warning,
                'timezone'                   => $reader?->timezone,
            ];

            $user->update(['role' => 'editor']);
            $user->editorProfile()->updateOrCreate(['user_id' => $user->id], $shared);

            return redirect()->route('admin.editors.edit', $user)->with('success', 'Role changed to editor.');
        }

        $data = $request->validate([
            'initials'                   => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'first_name'                 => ['required', 'string', 'max:100'],
            'last_name'                  => ['required', 'string', 'max:100'],
            'title'                      => ['nullable', 'string', 'max:100'],
            'max_concurrent_assignments' => ['required', 'integer', 'min:0', 'max:20'],
            'paypal_email'               => ['nullable', 'email', 'max:255'],
            'photo'                      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=600'],
            'about_photo'                => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=600'],
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

        if ($request->hasFile('about_photo')) {
            $profile = $profile ?? $user->readerProfile;
            if ($profile?->about_photo) {
                Storage::disk('public')->delete($profile->about_photo);
            }
            $data['about_photo'] = $request->file('about_photo')->store('reader-photos', 'public');
        } else {
            unset($data['about_photo']);
        }

        $data['bio'] = auth()->user()->isAdmin()
            ? Html::sanitizeBioHtml($data['bio'] ?? null)
            : Html::sanitizeBioPlainText($data['bio'] ?? null);

        $userUpdate = [
            'name'  => $data['first_name'] . ' ' . $data['last_name'],
            'email' => $data['email'],
        ];
        if (!empty($data['password'])) {
            $userUpdate['password'] = $data['password'];
        }
        if (auth()->user()->isAdmin() && $request->has('is_test')) {
            $userUpdate['is_test'] = (bool) $request->input('is_test');
        }
        $user->update($userUpdate);

        $data['requests_bypass_capacity'] = $request->boolean('requests_bypass_capacity');
        $data['is_1099']                  = $request->boolean('is_1099');

        $readerProfile = $user->readerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            collect($data)->except(['email', 'password', 'password_confirmation'])->toArray()
        );

        $readerProfile->tiers()->sync($request->input('tiers', []));

        return redirect()->route('readers.edit', $user)->with('success', 'Reader profile updated.');
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
