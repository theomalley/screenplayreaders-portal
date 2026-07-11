<?php

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
        $customItems = RateItem::orderBy('sort_order')->orderBy('id')->get();

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

        return view('ratebook.index', compact('rates', 'labels', 'editorRates', 'myEditorRates', 'customItems'));
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
        }

        $validated = $request->validate($rules);

        foreach ($keys as $key) {
            Setting::setValue($key, $validated[$key]);
            Setting::setRateLabel($key, $validated["label_{$key}"]);
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
