<?php

// v1.6 — 2026-07-17 | Add admin-editable Retail Price column (what customers are charged)
//                      alongside each core rate row, visible to editors and admins.
// v1.5 — 2026-07-12 | Editable [[shortcode]] token per rate row; auto-migrates existing
//                      [[old_name]] tokens in the saved Reader Manual content on rename.
// v1.4 — 2026-07-11 | Editable label text for core rates; add/edit/delete for custom rate items
// v1.3 — 2026-05-28 | Per-editor rates only; remove global rate fallback
// v1.1 — 2026-05-22 | Permission::check for access; editor commission/weekly flat rates added

namespace App\Http\Controllers;

use App\Models\RateItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Http\Request;

class RatebookController extends Controller
{
    public function index()
    {
        abort_unless(Permission::check('ratebook'), 403);

        $user  = auth()->user();
        $rates = Setting::ratesForForms();
        $labels = Setting::rateLabelsForForms();
        $shortcodes = Setting::rateShortcodesForForms();
        $customItems = RateItem::orderBy('sort_order')->orderBy('id')->get();

        $retailPrices = Setting::retailPricesForForms();
        $wordCounts = Setting::getWordCounts();
        $wordCountTotals = $this->wordCountTotals($wordCounts);

        $editorRates   = null;  // admin: all editors with their effective rates
        $myEditorRates = null;  // editor: own effective rates

        if ($user->isAdmin()) {
            $editorRates = User::where('role', 'editor')
                ->with('editorProfile')
                ->orderBy('name')
                ->get()
                ->map(function ($e) {
                    $p = $e->editorProfile;
                    return [
                        'id'          => $e->id,
                        'name'        => $p?->displayName() ?? $e->name,
                        'commission'  => $p?->editor_commission,
                        'weekly_flat' => $p?->editor_weekly_flat,
                    ];
                });
        } elseif ($user->isEditor()) {
            $p = $user->editorProfile;
            $myEditorRates = [
                'commission'  => $p?->editor_commission,
                'weekly_flat' => $p?->editor_weekly_flat,
            ];
        }

        return view('ratebook.index', compact(
            'rates', 'labels', 'shortcodes', 'editorRates', 'myEditorRates', 'customItems', 'retailPrices',
            'wordCounts', 'wordCountTotals'
        ));
    }

    /**
     * Per assignment-type total minimum word count (logline + synopsis + notes), including
     * only the fields that type's submission form actually shows/requires — must stay in sync
     * with the field-visibility rules in StoreCoverageSubmissionRequest::checkSrWordCounts()
     * and ::wdRules(). Keyed by the ratebook's rate-key ('book' has no rate key, so it's keyed
     * literally as 'book').
     */
    private function wordCountTotals(array $wc): array
    {
        // Types whose submission form shows a Logline / Synopsis field — Notes-Only and Budget
        // show neither; only these get that component added to their total.
        $srLoglineTypes  = ['script_coverage', 'short', 'deep_dive', 'book'];
        $srSynopsisTypes = ['script_coverage', 'book'];

        $srTotal = fn (string $type, string $notesKey) =>
            (in_array($type, $srLoglineTypes, true)  ? $wc['wc_sr_logline']  : 0)
            + (in_array($type, $srSynopsisTypes, true) ? $wc['wc_sr_synopsis'] : 0)
            + ($wc[$notesKey] ?? 0);

        // WD: Logline and Synopsis are required for both assignment types, so both always count.
        $wdTotal = fn (string $notesKey) => $wc['wc_wd_logline'] + $wc['wc_wd_synopsis'] + ($wc[$notesKey] ?? 0);

        return [
            'rate_sr_script_coverage'    => $srTotal('script_coverage', 'wc_sr_notes_script_coverage'),
            'rate_sr_notes_only'         => $srTotal('notes_only', 'wc_sr_notes_notes_only'),
            'rate_sr_short'              => $srTotal('short', 'wc_sr_notes_short'),
            'rate_sr_deep_dive'          => $srTotal('deep_dive', 'wc_sr_notes_deep_dive'),
            'rate_sr_budget'             => $srTotal('budget', 'wc_sr_notes_budget'),
            'book'                       => $srTotal('book', 'wc_sr_notes_book'),
            'rate_wd_coverage'           => $wdTotal('wc_wd_notes_coverage'),
            'rate_wd_development_notes' => $wdTotal('wc_wd_notes_development_notes'),
        ];
    }

    public function update(Request $request)
    {
        abort_unless(Permission::check('ratebook.edit'), 403);

        $keys = array_keys(Setting::RATE_DEFAULTS);

        $rules = collect($keys)->mapWithKeys(fn ($key) =>
            [$key => ['required', 'numeric', 'min:0', 'max:9999.99']]
        )->toArray();

        foreach ($keys as $key) {
            $rules["label_{$key}"] = ['required', 'string', 'max:255'];
            $rules["shortcode_{$key}"] = ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'];
        }

        foreach ($keys as $key) {
            $rules["retail_price_{$key}"] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules, [
            'shortcode_*.regex' => 'Shortcode must start with a letter and contain only lowercase letters, numbers, and underscores.',
        ]);

        $newShortcodes = collect($keys)->mapWithKeys(fn ($key) => [$key => $validated["shortcode_{$key}"]]);
        $dupes = $newShortcodes->duplicates();
        if ($dupes->isNotEmpty()) {
            return back()->withErrors(['shortcodes' => 'Shortcode names must be unique — duplicated: ' . $dupes->unique()->implode(', ')])->withInput();
        }

        $oldShortcodes = Setting::rateShortcodesForForms();
        $manualContent = Setting::getValue('reader_manual_content', '');
        $manualChanged = false;

        foreach ($keys as $key) {
            Setting::setValue($key, $validated[$key]);
            Setting::setRateLabel($key, $validated["label_{$key}"]);

            $newShortcode = $newShortcodes[$key];
            $oldShortcode = $oldShortcodes[$key];
            if ($newShortcode !== $oldShortcode) {
                Setting::setRateShortcode($key, $newShortcode);
                // Rewrite any existing [[old_name]] tokens already saved in the Reader Manual
                // so the rename doesn't silently break previously-published content.
                $manualContent = str_replace('[[' . $oldShortcode . ']]', '[[' . $newShortcode . ']]', $manualContent, $count);
                if ($count) {
                    $manualChanged = true;
                }
            }
        }

        if ($manualChanged) {
            Setting::setValue('reader_manual_content', $manualContent);
        }

        foreach ($keys as $key) {
            Setting::setRetailPrice($key, $validated["retail_price_{$key}"] ?? null);
        }

        return back()->with('success', 'Rates saved.');
    }

    public function storeItem(Request $request)
    {
        abort_unless(Permission::check('ratebook.edit'), 403);

        $data = $request->validate([
            'label'  => 'required|string|max:255',
            'amount' => 'required|numeric|min:0|max:9999.99',
        ]);

        RateItem::create([
            'label'      => $data['label'],
            'amount'     => $data['amount'],
            'sort_order' => (RateItem::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Rate item added.');
    }

    public function updateItem(Request $request, RateItem $rateItem)
    {
        abort_unless(Permission::check('ratebook.edit'), 403);

        $data = $request->validate([
            'label'  => 'required|string|max:255',
            'amount' => 'required|numeric|min:0|max:9999.99',
        ]);

        $rateItem->update($data);

        return back()->with('success', 'Rate item updated.');
    }

    public function destroyItem(RateItem $rateItem)
    {
        abort_unless(Permission::check('ratebook.edit'), 403);

        $rateItem->delete();

        return back()->with('success', 'Rate item deleted.');
    }
}
