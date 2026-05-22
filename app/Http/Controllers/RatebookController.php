<?php

// v1.1 — 2026-05-22 | Permission::check for access; editor commission/weekly flat rates added

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Permission;
use Illuminate\Http\Request;

class RatebookController extends Controller
{
    public function index()
    {
        abort_unless(Permission::check('ratebook'), 403);

        $rates = Setting::ratesForForms();

        return view('ratebook.index', compact('rates'));
    }

    public function update(Request $request)
    {
        abort_unless(Permission::check('ratebook.edit'), 403);

        $rules = collect(Setting::RATE_DEFAULTS)->mapWithKeys(function ($default, $key) {
            if ($key === 'rate_editor_commission') {
                return [$key => ['required', 'numeric', 'min:0', 'max:100']];
            }
            return [$key => ['required', 'numeric', 'min:0', 'max:9999.99']];
        })->toArray();

        $validated = $request->validate($rules);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value);
        }

        return back()->with('success', 'Rates saved.');
    }
}
