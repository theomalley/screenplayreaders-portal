<?php

// v1.2 — 2026-05-28 | Per-role editor rates; readers can view (no editor rates shown)
// v1.1 — 2026-05-22 | Permission::check for access; editor commission/weekly flat rates added

namespace App\Http\Controllers;

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

        $editorRates   = null;  // admin: all editors with their effective rates
        $myEditorRates = null;  // editor: own effective rates

        if ($user->isAdmin()) {
            $editorRates = User::where('role', 'editor')
                ->with('editorProfile')
                ->orderBy('name')
                ->get()
                ->map(function ($e) use ($rates) {
                    $p = $e->editorProfile;
                    return [
                        'id'           => $e->id,
                        'name'         => $p?->displayName() ?? $e->name,
                        'commission'   => $p?->editor_commission !== null
                            ? ['value' => $p->editor_commission, 'custom' => true]
                            : ['value' => $rates['rate_editor_commission'], 'custom' => false],
                        'weekly_flat'  => $p?->editor_weekly_flat !== null
                            ? ['value' => $p->editor_weekly_flat, 'custom' => true]
                            : ['value' => $rates['rate_editor_weekly_flat'], 'custom' => false],
                    ];
                });
        } elseif ($user->isEditor()) {
            $p = $user->editorProfile;
            $myEditorRates = [
                'commission'  => $p?->editor_commission !== null
                    ? $p->editor_commission
                    : $rates['rate_editor_commission'],
                'weekly_flat' => $p?->editor_weekly_flat !== null
                    ? $p->editor_weekly_flat
                    : $rates['rate_editor_weekly_flat'],
            ];
        }

        return view('ratebook.index', compact('rates', 'editorRates', 'myEditorRates'));
    }

    public function update(Request $request)
    {
        abort_unless(Permission::check('ratebook.edit'), 403);

        $rules = collect(Setting::RATE_DEFAULTS)->mapWithKeys(fn ($_, $key) =>
            [$key => ['required', 'numeric', 'min:0', $key === 'rate_editor_commission' ? 'max:100' : 'max:9999.99']]
        )->toArray();

        $validated = $request->validate($rules);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value);
        }

        return back()->with('success', 'Rates saved.');
    }
}
