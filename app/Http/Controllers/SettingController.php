<?php

// v1.0 — 2026-05-17 | Portal-wide settings: logo upload

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function uploadLogo(Request $request)
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

        return back();
    }
}
