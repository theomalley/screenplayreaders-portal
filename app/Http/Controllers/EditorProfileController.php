<?php

// v1.24 — 2026-11-22 | BUG FIX: update() set is_1099 and video_consulting_enabled
//                      unconditionally — unlike is_test in the same method, which was already
//                      correctly gated to admins only. editEditor/editors.edit can be granted to
//                      editors via the Permissions screen, not just admins. Also added a
//                      uniqueness check on `initials` (see ReaderProfile::initialsTaken()) —
//                      initials are the primary display identifier shared with readers, and
//                      nothing previously stopped a collision across either table.
// v1.23 — 2026-11-22 | updateVideoConsultingRates() now also saves a per-person fee override
//                      (video_consulting_fee_override_enabled/_amount/_percent), mirroring
//                      ReaderProfileController's equivalent method.
// v1.22 — 2026-11-22 | Add updateVideoConsultingHolidays() — admin override of an admin/
//                      editor's US federal holiday blackout accordion (App\Support\
//                      FederalHolidays).
// v1.21 — 2026-11-22 | updateVideoConsultingAvailability() now also saves
//                      video_consulting_available (the "Available for video calls" pause
//                      toggle), mirroring ReaderProfileController's equivalent method. Doesn't
//                      touch the day loop below.
// v1.20 — 2026-11-18 | Correction: video_consulting_reader_request_eligible is now saved for
//                      an editor target too, not just admin — editors don't automatically
//                      write coverage either, so they need the same opt-in.
// v1.19 — 2026-11-18 | update() now also saves video_consulting_reader_request_eligible when
//                      the target is an admin — the admin-only toggle for whether they can be
//                      counted as one of a customer's requested readers (default off).
// v1.18 — 2026-11-18 | updateReaderProfileShell() moved to App\Support\VideoConsultingAccess
//                      (shared with ProfileController's new self-serve enable/disable action)
//                      instead of staying a private copy here.
// v1.17 — 2026-11-15 | Admin/editor can now opt into taking video consulting bookings
//                      themselves — same settings a reader has (enable/disable override, weekly
//                      availability + blackout dates, per-duration rate), stored on the same
//                      ReaderProfile-keyed tables (see updateReaderProfileShell(),
//                      updateVideoConsultingRates(), updateVideoConsultingAvailability(),
//                      storeVideoConsultingBlackout()). App\Support\VideoConsultingAccess now
//                      admits admin/editor (opt-in only, no sitewide default).
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
use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderBlackoutDate;
use App\Models\ReaderVideoConsultingRate;
use App\Models\Setting;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Support\Html;
use App\Support\VideoConsultingAccess;
use Illuminate\Http\RedirectResponse;
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
            'initials'             => [
                'required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/',
                function ($attribute, $value, $fail) {
                    if (\App\Models\ReaderProfile::initialsTaken($value)) {
                        $fail('Those initials are already in use by another reader or editor.');
                    }
                },
            ],
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
        $readerProfile = $user->readerProfile;

        return view('profile.edit', [
            'user'                 => $user,
            'profile'              => $profile,
            'isSelf'               => false,
            'pendingAboutPhotoUrl' => $profile?->about_photo_pending ? asset('storage/' . $profile->about_photo_pending) : null,
            'pendingBioContent'    => $profile?->bio_pending,
            'videoConsultingOverrideSource'  => $readerProfile,
            'videoConsultingDurations'       => VideoConsultingDuration::active()->orderBy('minutes')->get(),
            'videoConsultingRates'           => ReaderVideoConsultingRate::where('reader_id', $user->id)->pluck('rate', 'duration_id'),
            'videoConsultingRules'           => ReaderAvailabilityRule::where('reader_id', $user->id)->get()->keyBy('day_of_week'),
            'videoConsultingBlackouts'       => ReaderBlackoutDate::where('reader_id', $user->id)->orderBy('start_at')->get(),
            'videoConsultingMinLeadHours'    => (int) ($readerProfile?->video_consulting_min_lead_hours ?? 0),
            'videoConsultingLeadHoursBounds' => Setting::getVideoConsultingLeadHoursBounds(),
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
            'initials'             => [
                'required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/',
                function ($attribute, $value, $fail) use ($user) {
                    if (\App\Models\ReaderProfile::initialsTaken($value, $user->id)) {
                        $fail('Those initials are already in use by another reader or editor.');
                    }
                },
            ],
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
            'favorite_genres'      => ['nullable', 'array', 'max:5'],
            'favorite_genres.*'    => ['nullable', 'string', 'max:60'],
            'favorite_films'       => ['nullable', 'array', 'max:5'],
            'favorite_films.*'     => ['nullable', 'string', 'max:60'],
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

        // FIX: is_1099 and video_consulting_enabled are admin-only toggles — previously
        // set unconditionally here while is_test (above) was already correctly gated.
        // editEditor/editors.edit can be granted to editors via the Permissions screen,
        // not just admins.
        if (auth()->user()->isAdmin()) {
            $data['is_1099'] = $request->boolean('is_1099');
        }

        $user->editorProfile()->updateOrCreate(
            ['user_id' => $user->id],
            collect($data)->except(['email', 'password', 'password_confirmation', 'favorite_genres', 'favorite_films'])->toArray()
        );

        // $user here is always an editor or admin (see abort_unless() above) — both get the
        // reader_request_eligible control (see profile/edit.blade.php); only a plain reader
        // is automatically eligible and skips it.
        $shellUpdate = [
            'favorite_genres' => \App\Models\ReaderProfile::parseFavoritesInput($data['favorite_genres'] ?? []),
            'favorite_films'  => \App\Models\ReaderProfile::parseFavoritesInput($data['favorite_films'] ?? []),
            'video_consulting_reader_request_eligible' => $request->boolean('video_consulting_reader_request_eligible'),
        ];
        if (auth()->user()->isAdmin()) {
            $shellUpdate['video_consulting_enabled'] = VideoConsultingAccess::parseOverrideInput($request->input('video_consulting_enabled'));
        }
        VideoConsultingAccess::updateReaderProfileShell($user, $shellUpdate);

        $label = $user->isAdmin() ? 'Admin' : 'Editor';
        return redirect()->route('admin.editors.edit', $user)->with('success', "{$label} profile updated.");
    }

    public function updateRates(Request $request, User $user)
    {
        $this->authorize('updateEditorRates', User::class);
        abort_unless($user->isEditor(), 404);

        $data = $request->validate([
            'editor_commission'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'editor_weekly_flat'              => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'video_consulting_commission_cap' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
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

    /**
     * Admin-set per-duration video consulting rate for an admin/editor taking calls
     * themselves — mirrors ReaderProfileController::updateVideoConsultingRates() field-for-field,
     * operating on the same ReaderProfile-keyed ReaderVideoConsultingRate rows (see
     * updateReaderProfileShell()).
     */
    public function updateVideoConsultingRates(Request $request, User $user): RedirectResponse
    {
        $this->authorize('updateEditorRates', User::class);
        abort_unless($user->isAdmin() || $user->isEditor(), 404);

        $durations = VideoConsultingDuration::active()->get()->keyBy('id');
        $submitted = $request->input('vc_rates', []);

        $errors = [];
        $toSave = [];

        foreach ($submitted as $durationId => $rawRate) {
            $duration = $durations->get((int) $durationId);
            if (! $duration) {
                continue;
            }

            $rawRate = trim((string) $rawRate);
            if ($rawRate === '') {
                $toSave[$duration->id] = null; // opt out of this duration
                continue;
            }

            if (! is_numeric($rawRate)) {
                $errors["vc_rates.{$durationId}"] = "Enter a valid number for {$duration->label}.";
                continue;
            }

            $rate = round((float) $rawRate, 2);
            if ($rate < (float) $duration->min_reader_rate || $rate > (float) $duration->max_reader_rate) {
                $errors["vc_rates.{$durationId}"] = "{$duration->label} rate must be between \${$duration->min_reader_rate} and \${$duration->max_reader_rate}.";
                continue;
            }

            $toSave[$duration->id] = $rate;
        }

        $feeOverrideEnabled = $request->boolean('fee_override_enabled');
        $feeOverrideAmount = is_numeric($request->input('fee_override_amount')) ? round((float) $request->input('fee_override_amount'), 2) : null;
        $feeOverridePercent = is_numeric($request->input('fee_override_percent')) ? round((float) $request->input('fee_override_percent'), 2) : null;

        if ($feeOverrideEnabled) {
            if ($feeOverrideAmount === null || $feeOverrideAmount < 0) {
                $errors['fee_override_amount'] = 'Enter a valid SR fee amount (0 or more).';
            }
            if ($feeOverridePercent === null || $feeOverridePercent < 0 || $feeOverridePercent > 100) {
                $errors['fee_override_percent'] = 'Enter a valid SR fee percent (0-100).';
            }
        }

        if (! empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        foreach ($toSave as $durationId => $rate) {
            if ($rate === null) {
                ReaderVideoConsultingRate::where('reader_id', $user->id)->where('duration_id', $durationId)->delete();
            } else {
                ReaderVideoConsultingRate::updateOrCreate(
                    ['reader_id' => $user->id, 'duration_id' => $durationId],
                    ['rate' => $rate]
                );
            }
        }

        VideoConsultingAccess::updateReaderProfileShell($user, [
            'video_consulting_fee_override_enabled' => $feeOverrideEnabled,
            'video_consulting_fee_override_amount'  => $feeOverrideAmount,
            'video_consulting_fee_override_percent' => $feeOverridePercent,
        ]);

        return redirect()->route('admin.editors.edit', $user)->with('success', 'Video consulting rates updated.');
    }

    /**
     * Admin/editor override of an admin/editor's weekly video consulting availability —
     * mirrors ReaderProfileController::updateVideoConsultingAvailability() field-for-field.
     * Blackout removal reuses the existing destroy route/policy unchanged
     * (ReaderBlackoutDatePolicy::delete() already allows any admin/editor).
     */
    public function updateVideoConsultingAvailability(Request $request, User $user): RedirectResponse
    {
        $this->authorize('editEditor', User::class);
        abort_unless($user->isAdmin() || $user->isEditor(), 404);

        $leadHoursBounds = Setting::getVideoConsultingLeadHoursBounds();

        $data = $request->validate([
            'days'              => ['array'],
            'days.*.enabled'    => ['nullable', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time'   => ['nullable', 'date_format:H:i'],
            'min_lead_hours'    => ['nullable', 'integer', "min:{$leadHoursBounds['min']}", "max:{$leadHoursBounds['max']}"],
        ]);

        VideoConsultingAccess::updateReaderProfileShell($user, [
            'video_consulting_min_lead_hours' => (int) ($data['min_lead_hours'] ?? $leadHoursBounds['min']),
            'video_consulting_available'      => $request->boolean('video_consulting_available'),
        ]);

        foreach (range(0, 6) as $dayOfWeek) {
            $day = $data['days'][$dayOfWeek] ?? null;
            $enabled = (bool) ($day['enabled'] ?? false);
            $start = $day['start_time'] ?? null;
            $end = $day['end_time'] ?? null;

            if (! $enabled || ! $start || ! $end) {
                ReaderAvailabilityRule::where('reader_id', $user->id)->where('day_of_week', $dayOfWeek)->delete();
                continue;
            }

            if ($start >= $end) {
                return back()->withErrors(["days.{$dayOfWeek}.end_time" => 'End time must be after start time.'])->withInput();
            }

            ReaderAvailabilityRule::updateOrCreate(
                ['reader_id' => $user->id, 'day_of_week' => $dayOfWeek],
                ['start_time' => $start, 'end_time' => $end, 'enabled' => true]
            );
        }

        return redirect()->route('admin.editors.edit', $user)->with('success', 'Availability updated.');
    }

    /**
     * Admin override of an admin/editor's US federal holiday blackout accordion, mirroring
     * ReaderProfileController::updateVideoConsultingHolidays() field-for-field.
     */
    public function updateVideoConsultingHolidays(Request $request, User $user): RedirectResponse
    {
        $this->authorize('editEditor', User::class);
        abort_unless($user->isAdmin() || $user->isEditor(), 404);

        $validKeys = array_keys(\App\Support\FederalHolidays::HOLIDAYS);
        $submitted = $request->input('holidays', []);
        $blackedOut = array_values(array_intersect($validKeys, is_array($submitted) ? $submitted : []));

        VideoConsultingAccess::updateReaderProfileShell($user, [
            'video_consulting_holiday_blackouts' => $blackedOut,
        ]);

        return redirect()->route('admin.editors.edit', $user)->with('success', 'Holiday blackouts updated.');
    }

    /** Admin/editor override — add a blackout date on behalf of an admin/editor taking calls. */
    public function storeVideoConsultingBlackout(Request $request, User $user): RedirectResponse
    {
        $this->authorize('editEditor', User::class);
        abort_unless($user->isAdmin() || $user->isEditor(), 404);

        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at'   => ['required', 'date', 'after:start_at'],
            'reason'   => ['nullable', 'string', 'max:255'],
        ]);

        ReaderBlackoutDate::create([
            'reader_id' => $user->id,
            'start_at'  => $data['start_at'],
            'end_at'    => $data['end_at'],
            'reason'    => $data['reason'] ?? null,
        ]);

        return redirect()->route('admin.editors.edit', $user)->with('success', 'Blackout added.');
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
