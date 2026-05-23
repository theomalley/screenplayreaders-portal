<?php

// v1.1 — 2026-05-23 | Add settings index page; redirect to settings after upload
// v1.0 — 2026-05-17 | Portal-wide settings: logo upload

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $metaFile = storage_path('app/portal-logo-path.txt');
        $logoUrl  = is_readable($metaFile) ? asset('storage/' . trim(file_get_contents($metaFile))) : null;

        return view('settings.index', compact('logoUrl'));
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $request->validate(['logo' => 'required|image|max:4096']);

        $metaFile = storage_path('app/portal-logo-path.txt');

        if (is_readable($metaFile)) {
            $old = trim(file_get_contents($metaFile));
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $ext = strtolower($request->file('logo')->getClientOriginalExtension());
        $filename = 'portal/portal-logo.' . $ext;
        Storage::disk('public')->putFileAs('portal', $request->file('logo'), 'portal-logo.' . $ext);
        file_put_contents($metaFile, $filename);

        return redirect()->route('settings.index')->with('success', 'Logo updated.');
    }
}
