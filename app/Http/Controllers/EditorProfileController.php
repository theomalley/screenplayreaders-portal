<?php

// v1.16 — 2026-07-23 | Authorization moved to UserPolicy (app/Policies) abilities
//                     (viewAnyEditors, createEditor, editEditor, updateEditorRates,
//                     manageEditorCommissions, deleteEditor), replacing inline
//                     abort_unless(isAdmin())/Permission::check() calls. The "is $user
//                     actually an editor/admin" checks stay inline as 404s (unchanged).
//                     Covered by tests/Feature/EditorProfileControllerTest.php.
// v1.15 — 2026-07-20 | Role change (editor→reader): replace hardcoded 'tier_1' => true (a
//                      dropped column) with syncing the reader into the first non-onboarding
//                      dynamic Tier.
// v1.14 — 2026-07-13 | Fix: custom_message (Logline) was missing from update() validation, so admin-edit saves silently dropped it
// v1.13 — 2026-07-08 | Save is_1099 flag when admin edits editor profile
// v1.12 — 2026-06-23 | Save is_test flag on user when admin edits editor profile
// v1.11 — 2026-06-23 | Role change (editor→reader) preserves all shared profile data; removed duplicate role-change block
// v1.10 — 2026-06-12 | Sanitize bio on save: HTML allowlist for admins, plain text for everyone else
// v1.9 — 2026-06-03 | Add custom_message to update validation/save
// v1.8 — 2026-05-28 | Separate updateRates() to prevent profile/rates forms from nulling each other's fields
// v1.7 — 2026-05-28 | Rate fields on create; remove global rate fallback concept
// v1.5 — 2026-05-28 | Add timezone field to editor/admin profile update
// v1.4 — 2026-05-27 | Allow admin to edit admin accounts via the same editor form
// v1.3 — 2026-05-27 | Enforce Password::defaults() (min 12, mixed case, numbers, symbols) on create/update
// v1.2 — 2026-05-27 | Gate edit/delete on editors.edit / editors.delete permissions; redirect to team.index
// v1.1 — 2026-05-25 | Add saveCommissions() for per-product commission config
// v1.0.2 — 2026-05-24 | Add MIME allowlist to photo upload.
// v1.0.1 — 2026-05-24 | Force redeploy with editor_profiles migration.
// v1.0 — 2026-05-24 | CRUD for editor accounts and profiles — admin only.

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\EditorProductCommission;
use App\Models\User;
use App\Support\Html;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class EditorProfileController extends Controller
{
    public function index()
    {
        $this->authorize('viewAnyEditors', User::class);

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
        $this->authorize('createEditor', User::class);

        return view('admin.editors.create');
    }

    public function store(Request $request)
    {
        $this->authorize('createEditor', User::class);

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'email'                => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'             => ['required', 'confirmed', Password::defaults()],
            'initials'             => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'first_name'           => ['required', 'string', 'max:100'],
            'last_name'            => ['required', 'string', 'max:100'],
            'paypal_email'         => ['nullable', 'email', 'max:255'],
            'availability'         => ['required', 'in:available,unavailable'],
            'availability_message' => ['nullable', 'string', 'max:500'],
            'upload_warning'       => ['nullable', 'string', 'max:1000'],
            'editor_commission'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'editor_weekly_flat'   => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
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
            'editor_commission'    => $data['editor_commission'] ?? null,
            'editor_weekly_flat'   => $data['editor_weekly_flat'] ?? null,
        ]);

        return redirect()->route('team.index')->with('success', 'Editor created.');
    }

    public function edit(User $user)
    {
        $this->authorize('editEditor', User::class);
        abort_unless($user->isEditor() || $user->isAdmin(), 404);

        $profile = $user->editorProfile;

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
        $this->authorize('editEditor', User::class);
        abort_unless($user->isEditor() || $user->isAdmin(), 404);

        if (auth()->user()->isAdmin() && $request->input('_action') === 'role_change') {
            abort_unless($user->isEditor() && $request->input('role') === 'reader', 422);

            $shared = \App\Models\EditorProfile::sharedArrayFrom($user->editorProfile, $user);

            $user->update(['role' => 'reader']);
            $readerProfile = $user->readerProfile()->updateOrCreate(['user_id' => $user->id], $shared);

            // Default a newly-converted reader into the first non-onboarding tier.
            if ($defaultTier = \App\Models\Tier::ordered()->where('is_onboarding', false)->first()) {
                $readerProfile->tiers()->syncWithoutDetaching([$defaultTier->id]);
            }

            return redirect()->route('readers.edit', $user)->with('success', 'Role changed to reader.');
        }

        $data = $request->validate([
            'initials'             => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'first_name'           => ['required', 'string', 'max:100'],
            'last_name'            => ['required', 'string', 'max:100'],
            'title'                => ['nullable', 'string', 'max:100'],
            'paypal_email'         => ['nullable', 'email', 'max:255'],
            'photo'                => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=600'],
            'email'                => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password'             => ['nullable', 'confirmed', Password::defaults()],
            'availability'         => ['required', 'in:available,unavailable'],
            'availability_message' => ['nullable', 'string', 'max:500'],
            'upload_warning'       => ['nullable', 'string', 'max:1000'],
            'bio'                  => ['nullable', 'string', 'max:5000'],
            'custom_message'       => ['nullable', 'string', 'max:200'],
            'timezone'             => ['nullable', 'timezone'],
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

        $data['is_1099'] = $request->boolean('is_1099');

        $user->editorProfile()->updateOrCreate(
            ['user_id' => $user->id],
            collect($data)->except(['email', 'password', 'password_confirmation'])->toArray()
        );

        $label = $user->isAdmin() ? 'Admin' : 'Editor';
        return redirect()->route('admin.editors.edit', $user)->with('success', "{$label} profile updated.");
    }

    public function updateRates(Request $request, User $user)
    {
        $this->authorize('updateEditorRates', User::class);
        abort_unless($user->isEditor(), 404);

        $data = $request->validate([
            'editor_commission'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'editor_weekly_flat' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $user->editorProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        return redirect()->route('admin.editors.edit', $user)->with('success', 'Rates updated.');
    }

    public function saveCommissions(Request $request, User $user)
    {
        $this->authorize('manageEditorCommissions', User::class);
        abort_unless($user->isEditor(), 404);

        $profile = $user->editorProfile;
        if (! $profile) {
            return back()->with('error', 'Editor profile not found.');
        }

        $submittedCommissions = $request->input('commissions', []);

        foreach (EditorProductCommission::allProducts() as $productId => $product) {
            $enabled      = isset($submittedCommissions[$productId]['enabled']);
            $rawAmount    = $submittedCommissions[$productId]['amount'] ?? '';
            $customAmount = ($rawAmount !== '' && is_numeric($rawAmount)) ? (float) $rawAmount : null;

            EditorProductCommission::updateOrCreate(
                ['editor_profile_id' => $profile->id, 'woo_product_id' => $productId],
                [
                    'product_label'      => $product['label'],
                    'commission_enabled' => $enabled,
                    'custom_amount'      => $customAmount,
                ]
            );
        }

        return redirect()->route('admin.editors.edit', $user)
            ->with('success', 'Commission config saved.');
    }

    public function destroy(User $user)
    {
        $this->authorize('deleteEditor', User::class);
        abort_unless($user->isEditor(), 404);

        if ($user->assignments()->where('status', Assignment::STATUS_ASSIGNED)->exists()) {
            return back()->with('error', 'Cannot delete an editor with active assignments.');
        }

        if ($user->editorProfile?->photo) {
            Storage::disk('public')->delete($user->editorProfile->photo);
        }

        $user->delete();

        return redirect()->route('team.index')->with('success', 'Editor deleted.');
    }
}
