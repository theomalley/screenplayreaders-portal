<?php

// v1.0 — 2026-07-20 | Tools > Settings > Tiers — admin-only CRUD for dynamic reader tiers:
// create/rename/reorder, timeout + escalates-to-tier, per-tier assignment-type allowlist, and
// the from-tier -> to-tier cross-visibility/accept matrix. See App\Models\Tier and
// App\Support\TierAccess for how these settings are enforced.

namespace App\Http\Controllers;

use App\Models\Tier;
use App\Models\TierCrossVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TierController extends Controller
{
    /** Assignment types shared with the create/edit assignment forms — see StoreAssignmentRequest. */
    public const ASSIGNMENT_TYPES = [
        'script_coverage'   => 'Script Coverage',
        'notes_only'        => 'Notes-Only',
        'deep_dive'         => 'Advanced Script Coverage (Deep Dive)',
        'short'             => 'Short',
        'budget'            => 'Budget Coverage',
        'book'              => 'Book',
        'coverage'          => 'Coverage (WD)',
        'development_notes' => 'Development Notes (WD)',
        'formatting'        => 'Formatting',
        'proofreading'      => 'Proofreading',
    ];

    public function index(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $tiers           = Tier::ordered()->get();
        $crossVisibility = TierCrossVisibility::all()->groupBy('from_tier_id');

        return view('settings.tiers', [
            'tiers'           => $tiers,
            'crossVisibility' => $crossVisibility,
            'assignmentTypes' => self::ASSIGNMENT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tiers,name'],
        ]);

        Tier::create([
            'name'     => $data['name'],
            'position' => (int) (Tier::max('position') ?? 0) + 1,
        ]);

        return back()->with('success', "Tier \"{$data['name']}\" created.");
    }

    public function update(Request $request, Tier $tier): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'name'                        => ['required', 'string', 'max:100', Rule::unique('tiers', 'name')->ignore($tier->id)],
            'position'                    => ['required', 'integer', 'min:0'],
            'timeout_hours'               => ['nullable', 'integer', 'min:1', 'max:8760'],
            'escalates_to_tier_id'        => ['nullable', 'integer', 'exists:tiers,id'],
            'allowed_assignment_types'    => ['nullable', 'array'],
            'allowed_assignment_types.*'  => ['string', Rule::in(array_keys(self::ASSIGNMENT_TYPES))],
        ]);

        if (!empty($data['escalates_to_tier_id']) && (int) $data['escalates_to_tier_id'] === $tier->id) {
            return back()->withErrors(['escalates_to_tier_id' => 'A tier cannot escalate to itself.'])->withInput();
        }

        // Both or neither — a timeout with no destination (or vice versa) does nothing.
        if (empty($data['timeout_hours']) || empty($data['escalates_to_tier_id'])) {
            $data['timeout_hours']        = null;
            $data['escalates_to_tier_id'] = null;
        }

        $data['allowed_assignment_types'] = !empty($data['allowed_assignment_types'])
            ? $data['allowed_assignment_types']
            : null;

        $tier->update($data);

        return back()->with('success', "Tier \"{$tier->name}\" updated.");
    }

    public function destroy(Tier $tier): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if ($tier->is_onboarding) {
            return back()->withErrors(['tier' => 'The onboarding tier cannot be deleted.']);
        }

        if ($tier->assignments()->exists() || $tier->readerProfiles()->exists()) {
            return back()->withErrors(['tier' => 'Remove all assignments and readers from this tier before deleting it.']);
        }

        if (Tier::where('escalates_to_tier_id', $tier->id)->exists()) {
            return back()->withErrors(['tier' => "Another tier still escalates into \"{$tier->name}\" — update that tier's escalation target first."]);
        }

        TierCrossVisibility::where('from_tier_id', $tier->id)->orWhere('to_tier_id', $tier->id)->delete();
        $tier->delete();

        return redirect()->route('settings.tiers')->with('success', 'Tier deleted.');
    }

    /** Bulk-saves the whole from-tier -> to-tier matrix from one form submission. */
    public function updateCrossVisibility(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $tiers   = Tier::all();
        $checked = $request->input('visibility', []);

        foreach ($tiers as $fromTier) {
            foreach ($tiers as $toTier) {
                if ($fromTier->id === $toTier->id) {
                    continue;
                }

                $isChecked = filter_var($checked[$fromTier->id][$toTier->id] ?? false, FILTER_VALIDATE_BOOLEAN);

                TierCrossVisibility::updateOrCreate(
                    ['from_tier_id' => $fromTier->id, 'to_tier_id' => $toTier->id],
                    [
                        // Onboarding-tier rows are always view-only — the UI never exposes an
                        // accept toggle there, and this is a second, server-side guarantee.
                        'can_view'   => $isChecked,
                        'can_accept' => $isChecked && ! $fromTier->is_onboarding,
                    ]
                );
            }
        }

        return back()->with('success', 'Cross-tier visibility saved.');
    }
}
