<?php

// v1.0 — 2026-05-21 | Reader Manual page; admin-only editing via Setting store

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class ManualController extends Controller
{
    public function show()
    {
        $html = Setting::getValue('reader_manual_html', '');
        return view('manual.show', compact('html'));
    }

    public function update(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'html' => ['nullable', 'string'],
        ]);

        Setting::setValue('reader_manual_html', $request->input('html', ''));

        return back()->with('success', 'Reader Manual updated.');
    }
}
