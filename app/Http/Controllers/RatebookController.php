<?php

// v1.0 — 2026-05-17 | Ratebook: view rates (admin + editor), edit rates (admin only)

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class RatebookController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $rates = Setting::ratesForForms();

        return view('ratebook.index', compact('rates'));
    }

    public function update(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $validated = $request->validate(
            collect(Setting::RATE_DEFAULTS)->mapWithKeys(
                fn ($default, $key) => [$key => ['required', 'numeric', 'min:0', 'max:9999.99']]
            )->toArray()
        );

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value);
        }

        return back()->with('success', 'Rates saved.');
    }
}
