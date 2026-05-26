<?php

// v2.0 — 2026-05-26 | Portal theme setting (Default, Midnight, Forest, Warm).
// v1.9 — 2026-05-26 | Add sr_invoice_address and invoice_email_body settings for client invoicing.
// v1.8 — 2026-05-25 | Session timeout setting.
// v1.7 — 2026-05-24 | Consolidate permissions, filenames, coverage-success into settings index.
// v1.6 — 2026-05-24 | Add favicon upload.
// v1.5 — 2026-05-24 | Use MIME-derived extension for logo uploads; add image type allowlist.
// v1.4 — 2026-05-24 | Global capacity override setting.
// v1.3 — 2026-05-24 | Coverage submission success page: admin-editable custom HTML
// v1.2 — 2026-05-23 | Separate login logo upload; nav logo no longer clickable
// v1.1 — 2026-05-23 | Add settings index page; redirect to settings after upload
// v1.0 — 2026-05-17 | Portal-wide settings: logo upload

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\FilenameGenerator;
use App\Support\Permission;
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

        $capacityOverride    = (int) Setting::getValue('capacity_override', 0);
        $sessionTimeout      = (int) Setting::getValue('session_timeout_minutes', 120);

        $faviconMetaFile = storage_path('app/portal-favicon-path.txt');
        $faviconUrl      = is_readable($faviconMetaFile) ? asset('storage/' . trim(file_get_contents($faviconMetaFile))) : null;

        $isAdmin              = auth()->user()->isAdmin();
        $permissionsGrid      = $isAdmin ? Permission::all() : null;
        $filenameSuffixes     = $isAdmin ? FilenameGenerator::allSuffixes() : null;
        $coverageSuccessHtml  = $isAdmin ? Setting::getValue('coverage_success_html', '') : null;
        $srInvoiceAddress     = Setting::getValue('sr_invoice_address', '');
        $invoiceEmailBody     = Setting::getValue('invoice_email_body', '');
        $portalTheme          = Setting::getValue('portal_theme', 'default');

        return view('settings.index', compact(
            'logoUrl', 'loginLogoUrl', 'faviconUrl',
            'capacityOverride', 'sessionTimeout',
            'isAdmin', 'permissionsGrid', 'filenameSuffixes', 'coverageSuccessHtml',
            'srInvoiceAddress', 'invoiceEmailBody', 'portalTheme',
        ));
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $request->validate(['logo' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:4096']);

        $metaFile = storage_path('app/portal-logo-path.txt');

        if (is_readable($metaFile)) {
            $old = trim(file_get_contents($metaFile));
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $ext      = $request->file('logo')->extension();
        $filename = 'portal/portal-logo.' . $ext;
        Storage::disk('public')->putFileAs('portal', $request->file('logo'), 'portal-logo.' . $ext);
        file_put_contents($metaFile, $filename);

        return redirect()->route('settings.index')->with('success', 'Logo updated.');
    }

    public function uploadLoginLogo(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $request->validate(['login_logo' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:4096']);

        $metaFile = storage_path('app/portal-login-logo-path.txt');

        if (is_readable($metaFile)) {
            $old = trim(file_get_contents($metaFile));
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $ext      = $request->file('login_logo')->extension();
        $filename = 'portal/portal-login-logo.' . $ext;
        Storage::disk('public')->putFileAs('portal', $request->file('login_logo'), 'portal-login-logo.' . $ext);
        file_put_contents($metaFile, $filename);

        return redirect()->route('settings.index')->with('success', 'Login logo updated.');
    }

    public function uploadFavicon(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $request->validate(['favicon' => 'required|file|mimes:png,ico,svg,webp|max:512']);

        $metaFile = storage_path('app/portal-favicon-path.txt');

        if (is_readable($metaFile)) {
            $old = trim(file_get_contents($metaFile));
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $ext      = $request->file('favicon')->extension();
        $filename = 'portal/portal-favicon.' . $ext;
        Storage::disk('public')->putFileAs('portal', $request->file('favicon'), 'portal-favicon.' . $ext);
        file_put_contents($metaFile, $filename);

        return redirect()->route('settings.index')->with('success', 'Favicon updated.');
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

    public function updateSessionTimeout(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $request->validate(['session_timeout_minutes' => 'required|integer|min:5|max:1440']);

        $value = (int) $request->input('session_timeout_minutes');
        Setting::setValue('session_timeout_minutes', $value);

        return redirect()->route('settings.index')->with('success', "Session timeout set to {$value} minute" . ($value === 1 ? '' : 's') . '.');
    }

    public function updateInvoiceSettings(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $request->validate([
            'sr_invoice_address' => 'nullable|string|max:1000',
            'invoice_email_body' => 'nullable|string',
        ]);

        Setting::setValue('sr_invoice_address', trim($request->input('sr_invoice_address', '')));
        Setting::setValue('invoice_email_body', trim($request->input('invoice_email_body', '')));

        return back()->with('success', 'Invoice settings saved.');
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $request->validate(['portal_theme' => 'required|in:default,midnight,forest,warm']);

        Setting::setValue('portal_theme', $request->input('portal_theme'));

        return back()->with('success', 'Theme updated.');
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
