<?php

// v1.4 — 2026-05-24 | Global capacity override setting.
// v1.3 — 2026-05-24 | Coverage submission success page: admin-editable custom HTML
// v1.2 — 2026-05-23 | Separate login logo upload; nav logo no longer clickable
// v1.1 — 2026-05-23 | Add settings index page; redirect to settings after upload
// v1.0 — 2026-05-17 | Portal-wide settings: logo upload

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $metaFile     = storage_path('app/portal-logo-path.txt');
        $logoUrl      = is_readable($metaFile) ? asset('storage/' . trim(file_get_contents($metaFile))) : null;

        $loginMetaFile = storage_path('app/portal-login-logo-path.txt');
        $loginLogoUrl  = is_readable($loginMetaFile) ? asset('storage/' . trim(file_get_contents($loginMetaFile))) : null;

        $capacityOverride = (int) Setting::getValue('capacity_override', 0);

        return view('settings.index', compact('logoUrl', 'loginLogoUrl', 'capacityOverride'));
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

    public function uploadLoginLogo(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $request->validate(['login_logo' => 'required|image|max:4096']);

        $metaFile = storage_path('app/portal-login-logo-path.txt');

        if (is_readable($metaFile)) {
            $old = trim(file_get_contents($metaFile));
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $ext = strtolower($request->file('login_logo')->getClientOriginalExtension());
        $filename = 'portal/portal-login-logo.' . $ext;
        Storage::disk('public')->putFileAs('portal', $request->file('login_logo'), 'portal-login-logo.' . $ext);
        file_put_contents($metaFile, $filename);

        return redirect()->route('settings.index')->with('success', 'Login logo updated.');
    }

    public function updateCapacityOverride(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $request->validate(['capacity_override' => 'nullable|integer|min:0|max:99']);

        $value = (int) $request->input('capacity_override', 0);
        Setting::setValue('capacity_override', $value);

        return redirect()->route('settings.index')->with('success', $value > 0
            ? "Capacity override set to {$value} assignment" . ($value === 1 ? '' : 's') . ' for all readers.'
            : 'Capacity override cleared — individual reader limits apply.');
    }

    public function editCoverageSuccess(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $content = Setting::getValue('coverage_success_html', '');
        return view('settings.coverage-success', compact('content'));
    }

    public function updateCoverageSuccess(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate(['content' => ['nullable', 'string']]);
        Setting::setValue('coverage_success_html', trim($request->input('content', '')));

        return back()->with('success', 'Coverage submission page updated.');
    }
}
