<?php

// v1.0 — 2026-05-22 | Admin view for editing filename suffix conventions per service type

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\FilenameGenerator;
use Illuminate\Http\Request;

class FilenamesController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $suffixes = FilenameGenerator::allSuffixes();

        return view('admin.filenames', compact('suffixes'));
    }

    public function update(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $rules = [];
        foreach (array_keys(FilenameGenerator::SUFFIX_DEFAULTS) as $key) {
            $rules[$key] = ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]*$/'];
        }
        $request->validate($rules);

        foreach (array_keys(FilenameGenerator::SUFFIX_DEFAULTS) as $key) {
            Setting::setValue($key, $request->input($key) ?? FilenameGenerator::SUFFIX_DEFAULTS[$key]);
        }

        return back()->with('success', 'Filename conventions saved.');
    }
}
