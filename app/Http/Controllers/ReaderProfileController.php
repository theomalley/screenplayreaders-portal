<?php

// v1.25 — 2026-11-22 | BUG FIX: update() set video_consulting_enabled, requests_bypass_capacity,
//                      and is_1099 unconditionally — unlike is_test in the same method, which was
//                      already correctly gated to admins only. video_consulting_enabled is
//                      documented (see v1.x history) as an admin-only per-reader kill switch, and
//                      editReader/readers.edit can be granted to editors via the Permissions
//                      screen, not just admins — closes the gap by requiring isAdmin() for all
//                      three, matching is_test's existing pattern.
//                      Also added a uniqueness check on `initials` across both reader_profiles
//                      and editor_profiles — initials are the primary display identifier
//                      throughout the app, and nothing previously stopped two people (reader or
//                      editor) from colliding on the same ones.
// v1.24 — 2026-11-22 | updateVideoConsultingRates() now also saves a per-person fee override
//                      (video_consulting_fee_override_enabled/_amount/_percent) — lets the
//                      admin replace this reader's SR fee (normally VideoConsultingDuration's
//                      own base_rate/base_rate_percent) with a flat $/% of their own, down to
//                      0/0. See VideoConsultingDuration::effectiveBaseRate().
// v1.23 — 2026-11-22 | Add updateVideoConsultingHolidays() — admin override of a reader's US
//                      federal holiday blackout accordion (ReaderProfile.
//                      video_consulting_holiday_blackouts, App\Support\FederalHolidays).
// v1.22 — 2026-11-22 | updateVideoConsultingAvailability() now also saves
//                      video_consulting_available — an admin can see/override a reader's own
//                      "Available for video calls" pause toggle from here too. Doesn't touch
//                      the day loop below, so toggling it never affects the saved schedule.
// v1.21 — 2026-11-15 | parseVideoConsultingOverride() moved to the shared
//                      App\Support\VideoConsultingAccess::parseOverrideInput() — admins/editors
//                      taking video consulting themselves now go through EditorProfileController's
//                      parallel methods, which needed the same parsing.
// v1.20 — 2026-11-14 | Admin/editor can now also edit a reader's video consulting weekly
//                      availability + blackout dates from this edit page
//                      (updateVideoConsultingAvailability()/storeVideoConsultingBlackout()) —
//                      previously only the reader's own self-serve "Consulting" page could set
//                      these. That page's Availability section moved entirely onto this one
//                      (see profile/edit.blade.php + resources/views/partials/
//                      video-consulting-availability.blade.php, shared by both contexts);
//                      blackout removal reuses the existing destroy route/policy for both,
//                      with ReaderBlackoutDatePolicy now also allowing admin/editor.
// v1.19 — 2026-11-14 | CORRECTION: Removed storeVideoConsultingDuration() (added in v1.19 earlier
//                      today) — creating/editing a duration's sitewide base_rate/min/max/label
//                      belongs solely in the admin video consulting settings (the theme's
//                      Tools -> SR Video Consulting page), never on a reader's own profile page,
//                      per explicit user feedback ("that will only get confusing"). This page
//                      keeps only updateVideoConsultingRates() — picking from the durations that
//                      already exist and setting *this reader's* rate within their bounds.
// v1.18 — 2026-11-14 | Admins can now set a reader's per-duration video consulting rate
//                      directly from this edit page (updateVideoConsultingRates()) — previously
//                      only the reader's own self-serve /video-consulting-rate page could set
//                      it, so an admin enabling a reader via video_consulting_enabled had no way
//                      to make them actually bookable without the reader separately logging in
//                      and setting a rate themselves. Same per-duration min/max bounds as the
//                      self-serve form (VideoConsultingRateController::update()).
// v1.17 — 2026-11-08 | video_consulting_enabled is now a tri-state override (null/true/false)
//                      parsed from a 3-way select, not a checkbox — null means "follow the
//                      sitewide setting" instead of being AND'd with it. See
//                      App\Support\VideoConsultingAccess::enabledFor().
// v1.16 — 2026-11-07 | Add video_consulting_enabled toggle to store()/update() — admin-only
//                      per-reader kill switch for the video consulting feature. Defaults to
//                      enabled for new readers. See App\Support\VideoConsultingAccess.
// v1.15 — 2026-10-22 | Add watermark_enabled toggle to store()/update() — admin-configurable
//                      per-reader setting controlling whether this reader's viewed/downloaded
//                      scripts are watermarked. Defaults to enabled (checked) for new readers.
// v1.14 — 2026-07-23 | Authorization moved to UserPolicy (app/Policies) abilities
//                     (viewAnyReaders, createReader, editReader, deleteReader), replacing
//                     inline abort_unless(canManageAssignments())/Permission::check() calls.
//                     Covered by tests/Feature/ReaderProfileControllerTest.php.
// v1.13 — 2026-07-21 | Add photo/about_photo upload and tier assignment to store() (create form)
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
use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderBlackoutDate;
use App\Models\ReaderVideoConsultingRate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Support\Html;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ReaderProfileController extends Controller
{
    public function index()
    {
        $this->authorize('viewAnyReaders', User::class);

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
        $this->authorize('createReader', User::class);

        return view('readers.create');
    }

    public function store(Request $request)
    {
        $this->authorize('createReader', User::class);

        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'email'                      => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'                   => ['required', 'confirmed', Password::defaults()],
            'initials'                   => [
                'required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/',
                function ($attribute, $value, $fail) {
                    if (\App\Models\ReaderProfile::initialsTaken($value)) {
                        $fail('Those initials are already in use by another reader or editor.');
                    }
                },
            ],
            'first_name'                 => ['required', 'string', 'max:100'],
            'last_name'                  => ['required', 'string', 'max:100'],
            'max_concurrent_assignments' => ['nullable', 'integer', 'min:0', 'max:20'],
            'paypal_email'               => ['nullable', 'email', 'max:255'],
            'photo'                      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=600'],
            'about_photo'                => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=600'],
            'availability'               => ['required', 'in:available,unavailable'],
            'availability_message'       => ['nullable', 'string', 'max:500'],
            'upload_warning'             => ['nullable', 'string', 'max:1000'],
            // FIX: was synced with zero validation — a typo'd/non-existent tier ID
            // would throw a raw foreign-key QueryException (500) instead of a clean
            // validation error.
            'tiers'                      => ['nullable', 'array'],
            'tiers.*'                    => ['integer', 'exists:tiers,id'],
        ]);

        $data['requests_bypass_capacity']  = $request->boolean('requests_bypass_capacity');
        $data['watermark_enabled']         = $request->boolean('watermark_enabled');
        $data['video_consulting_enabled']  = \App\Support\VideoConsultingAccess::parseOverrideInput($request->input('video_consulting_enabled'));

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'role'     => 'reader',
        ]);

        $readerProfile = $user->readerProfile()->create([
            'initials'                   => $data['initials'],
            'first_name'                 => $data['first_name'],
            'last_name'                  => $data['last_name'],
            'max_concurrent_assignments'  => $data['max_concurrent_assignments'] ?? null,
            'requests_bypass_capacity'    => $data['requests_bypass_capacity'],
            'watermark_enabled'           => $data['watermark_enabled'],
            'video_consulting_enabled'    => $data['video_consulting_enabled'],
            'paypal_email'                => $data['paypal_email'] ?? null,
            'photo'                       => $request->hasFile('photo') ? $request->file('photo')->store('reader-photos', 'public') : null,
            'about_photo'                 => $request->hasFile('about_photo') ? $request->file('about_photo')->store('reader-photos', 'public') : null,
            'availability'                => $data['availability'],
            'availability_message'        => $data['availability_message'] ?? null,
        ]);

        $readerProfile->tiers()->sync($data['tiers'] ?? []);

        return redirect()->route('team.index')->with('success', 'Reader created.');
    }

    public function edit(User $user)
    {
        $this->authorize('editReader', User::class);
        abort_unless($user->isReader(), 404);

        $profile = $user->readerProfile;

        return view('profile.edit', [
            'user'                      => $user,
            'profile'                   => $profile,
            'isSelf'                    => false,
            'pendingAboutPhotoUrl'      => $profile?->about_photo_pending ? asset('storage/' . $profile->about_photo_pending) : null,
            'pendingBioContent'         => $profile?->bio_pending,
            'videoConsultingDurations'  => VideoConsultingDuration::active()->orderBy('minutes')->get(),
            'videoConsultingRates'      => ReaderVideoConsultingRate::where('reader_id', $user->id)->pluck('rate', 'duration_id'),
            'videoConsultingRules'      => ReaderAvailabilityRule::where('reader_id', $user->id)->get()->keyBy('day_of_week'),
            'videoConsultingBlackouts'  => ReaderBlackoutDate::where('reader_id', $user->id)->orderBy('start_at')->get(),
            'videoConsultingMinLeadHours' => (int) ($profile?->video_consulting_min_lead_hours ?? 0),
            'videoConsultingLeadHoursBounds' => \App\Models\Setting::getVideoConsultingLeadHoursBounds(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('editReader', User::class);
        abort_unless($user->isReader(), 404);

        if (auth()->user()->isAdmin() && $request->input('_action') === 'role_change') {
            abort_unless($request->input('role') === 'editor', 422);

            $shared = \App\Models\ReaderProfile::sharedArrayFrom($user->readerProfile, $user);

            $user->update(['role' => 'editor']);
            $user->editorProfile()->updateOrCreate(['user_id' => $user->id], $shared);

            return redirect()->route('admin.editors.edit', $user)->with('success', 'Role changed to editor.');
        }

        $data = $request->validate([
            'initials'                   => [
                'required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/',
                function ($attribute, $value, $fail) use ($user) {
                    if (\App\Models\ReaderProfile::initialsTaken($value, $user->id)) {
                        $fail('Those initials are already in use by another reader or editor.');
                    }
                },
            ],
            'first_name'                 => ['required', 'string', 'max:100'],
            'last_name'                  => ['required', 'string', 'max:100'],
            'title'                      => ['nullable', 'string', 'max:100'],
            'max_concurrent_assignments' => ['nullable', 'integer', 'min:0', 'max:20'],
            'paypal_email'               => ['nullable', 'email', 'max:255'],
            'photo'                      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=600'],
            'about_photo'                => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=600'],
            'email'                      => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password'                   => ['nullable', 'confirmed', Password::defaults()],
            'availability'               => ['required', 'in:available,unavailable'],
            'availability_message'       => ['nullable', 'string', 'max:500'],
            'upload_warning'             => ['nullable', 'string', 'max:1000'],
            'bio'                        => ['nullable', 'string', 'max:5000'],
            'favorite_genres'            => ['nullable', 'array', 'max:5'],
            'favorite_genres.*'          => ['nullable', 'string', 'max:60'],
            'favorite_films'             => ['nullable', 'array', 'max:5'],
            'favorite_films.*'           => ['nullable', 'string', 'max:60'],
            'custom_message'             => ['nullable', 'string', 'max:200'],
            // FIX: was synced with zero validation — a typo'd/non-existent tier ID
            // would throw a raw foreign-key QueryException (500) instead of a clean
            // validation error.
            'tiers'                      => ['nullable', 'array'],
            'tiers.*'                    => ['integer', 'exists:tiers,id'],
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

        $data['favorite_genres'] = \App\Models\ReaderProfile::parseFavoritesInput($data['favorite_genres'] ?? []);
        $data['favorite_films']  = \App\Models\ReaderProfile::parseFavoritesInput($data['favorite_films'] ?? []);

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

        // FIX: these three are admin-only toggles (video_consulting_enabled is a
        // documented admin-only kill switch; is_1099/requests_bypass_capacity are
        // equally sensitive) — previously set unconditionally here while is_test
        // (above) was already correctly gated. editReader/readers.edit can be granted
        // to editors via the Permissions screen, not just admins.
        if (auth()->user()->isAdmin()) {
            $data['requests_bypass_capacity'] = $request->boolean('requests_bypass_capacity');
            $data['is_1099']                  = $request->boolean('is_1099');
            if ($user->isReader()) {
                $data['video_consulting_enabled'] = \App\Support\VideoConsultingAccess::parseOverrideInput($request->input('video_consulting_enabled'));
            }
        }
        if ($user->isReader()) {
            $data['watermark_enabled'] = $request->boolean('watermark_enabled');
        }

        $readerProfile = $user->readerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            collect($data)->except(['email', 'password', 'password_confirmation', 'tiers'])->toArray()
        );

        $readerProfile->tiers()->sync($data['tiers'] ?? []);

        return redirect()->route('readers.edit', $user)->with('success', 'Reader profile updated.');
    }

    /**
     * Per-reader SR pay-rate overrides (admin-only — mirrors EditorProfileController::
     * updateRates()). Each field is nullable: blank clears the override and the reader
     * falls back to the sitewide Ratebook default (see ReaderProfile::effectiveRate()).
     */
    public function updateRates(Request $request, User $user)
    {
        $this->authorize('updateReaderRates', User::class);
        abort_unless($user->isReader(), 404);

        $data = $request->validate([
            'rate_script_coverage'   => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'rate_notes_only'        => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'rate_deep_dive'         => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'rate_proofreading'      => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'rate_oversized_121_160' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'rate_rush'              => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'rate_request'           => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        foreach ($data as $key => $value) {
            $data[$key] = ($value === '' || $value === null) ? null : $value;
        }

        $user->readerProfile()->updateOrCreate(['user_id' => $user->id], $data);

        return redirect()->route('readers.edit', $user)->with('success', 'Rates updated.');
    }

    /**
     * Admin-set per-duration video consulting rate override, mirroring the reader's own
     * self-serve form (VideoConsultingRateController::update()) field-for-field — same
     * per-duration min/max bounds, same blank-clears-the-rate behavior — just operating on
     * $user instead of auth()->user(). Lets an admin make a reader actually bookable the
     * moment they flip video_consulting_enabled, without waiting on the reader to log in and
     * set their own rate.
     */
    public function updateVideoConsultingRates(Request $request, User $user)
    {
        $this->authorize('updateReaderRates', User::class);
        abort_unless($user->isReader(), 404);

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

        $user->readerProfile()->updateOrCreate(['user_id' => $user->id], [
            'video_consulting_fee_override_enabled' => $feeOverrideEnabled,
            'video_consulting_fee_override_amount'  => $feeOverrideAmount,
            'video_consulting_fee_override_percent' => $feeOverridePercent,
        ]);

        return redirect()->route('readers.edit', $user)->with('success', 'Video consulting rates updated.');
    }

    /**
     * Admin/editor override of a reader's weekly video consulting availability, mirroring
     * VideoConsultingAvailabilityController::update() field-for-field but targeting $user
     * instead of auth()->user() — same 9 AM-5 PM default-for-an-unset-day behavior, same
     * delete-the-rule-if-disabled-or-incomplete behavior.
     */
    public function updateVideoConsultingAvailability(Request $request, User $user): RedirectResponse
    {
        $this->authorize('editReader', User::class);
        abort_unless($user->isReader(), 404);

        $leadHoursBounds = \App\Models\Setting::getVideoConsultingLeadHoursBounds();

        $data = $request->validate([
            'days'              => ['array'],
            'days.*.enabled'    => ['nullable', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time'   => ['nullable', 'date_format:H:i'],
            'min_lead_hours'    => ['nullable', 'integer', "min:{$leadHoursBounds['min']}", "max:{$leadHoursBounds['max']}"],
        ]);

        $user->readerProfile()->updateOrCreate(['user_id' => $user->id], [
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

        return redirect()->route('readers.edit', $user)->with('success', 'Availability updated.');
    }

    /**
     * Admin override of a reader's US federal holiday blackout accordion, mirroring
     * VideoConsultingAvailabilityController::updateHolidayBlackouts() field-for-field.
     */
    public function updateVideoConsultingHolidays(Request $request, User $user): RedirectResponse
    {
        $this->authorize('editReader', User::class);
        abort_unless($user->isReader(), 404);

        $validKeys = array_keys(\App\Support\FederalHolidays::HOLIDAYS);
        $submitted = $request->input('holidays', []);
        $blackedOut = array_values(array_intersect($validKeys, is_array($submitted) ? $submitted : []));

        $user->readerProfile()->updateOrCreate(['user_id' => $user->id], [
            'video_consulting_holiday_blackouts' => $blackedOut,
        ]);

        return redirect()->route('readers.edit', $user)->with('success', 'Holiday blackouts updated.');
    }

    /**
     * Admin/editor override — add a blackout date on behalf of a reader, mirroring
     * VideoConsultingAvailabilityController::storeBlackout() but setting reader_id from $user
     * instead of auth()->user(). Removal reuses that controller's existing destroyBlackout()/
     * route unchanged — ReaderBlackoutDatePolicy::delete() already allows any admin/editor.
     */
    public function storeVideoConsultingBlackout(Request $request, User $user): RedirectResponse
    {
        $this->authorize('editReader', User::class);
        abort_unless($user->isReader(), 404);

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

        return redirect()->route('readers.edit', $user)->with('success', 'Blackout added.');
    }

    public function destroy(User $user)
    {
        $this->authorize('deleteReader', User::class);
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
