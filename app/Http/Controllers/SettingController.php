<?php

// v2.9 — 2026-06-03 | Word count minimums — per-field admin settings + global enable/disable.
// v2.8 — 2026-05-31 | Followup form HTML — before/after injection via settings.
// v2.7 — 2026-05-30 | Email notification text settings (headers + body messages per notification type).
// v2.6 — 2026-05-29 | QC saved replies — admin-customizable quick-insert notes for Send Back modal.
// v2.5 — 2026-05-28 | Dev autofill toggle per role (admin/editor/reader) for coverage forms.
// v2.4 — 2026-05-28 | App timezone setting (admin-configurable; used for assignment date display and input).
// v2.3 — 2026-05-27 | Age thresholds use hours (max 8760); On Desk column on all assignment tables.
// v2.2 — 2026-05-27 | Add age-threshold settings (per assignment type, configurable colour bands).
// v2.1 — 2026-05-27 | Gate logo/login-logo/favicon/session-timeout/invoice as admin-only.
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
use App\Models\User;
use App\Services\HelpScoutService;
use App\Support\FilenameGenerator;
use App\Support\Permission;
use Illuminate\Http\JsonResponse;
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
        $ageThresholds        = Setting::getAgeThresholds();
        $ageThresholdTypes    = Setting::AGE_THRESHOLD_TYPES;
        $appTimezone          = Setting::getAppTimezone();
        $devAutofill          = [
            'admin'  => (bool) Setting::getValue('dev_autofill_admin',  false),
            'editor' => (bool) Setting::getValue('dev_autofill_editor', false),
            'reader' => (bool) Setting::getValue('dev_autofill_reader', false),
        ];
        $qcSavedReplies       = Setting::getSavedReplies();
        $emailNotifTexts      = Setting::getEmailNotificationTexts();
        $followupBeforeHtml   = Setting::getValue('followup_before_html', '');
        $followupAfterHtml    = Setting::getValue('followup_after_html', '');
        $followupHeading      = Setting::getValue('followup_heading', '');
        $wordCounts           = $isAdmin ? Setting::getWordCounts() : null;

        return view('settings.index', compact(
            'logoUrl', 'loginLogoUrl', 'faviconUrl',
            'capacityOverride', 'sessionTimeout',
            'isAdmin', 'permissionsGrid', 'filenameSuffixes', 'coverageSuccessHtml',
            'srInvoiceAddress', 'invoiceEmailBody', 'portalTheme',
            'ageThresholds', 'ageThresholdTypes', 'appTimezone',
            'devAutofill', 'qcSavedReplies', 'emailNotifTexts',
            'followupBeforeHtml', 'followupAfterHtml', 'followupHeading',
            'wordCounts',
        ));
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

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
        abort_unless(auth()->user()->isAdmin(), 403);

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
        abort_unless(auth()->user()->isAdmin(), 403);

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
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate(['session_timeout_minutes' => 'required|integer|min:5|max:1440']);

        $value = (int) $request->input('session_timeout_minutes');
        Setting::setValue('session_timeout_minutes', $value);

        return redirect()->route('settings.index')->with('success', "Session timeout set to {$value} minute" . ($value === 1 ? '' : 's') . '.');
    }

    public function updateInvoiceSettings(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

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

        $request->validate(['portal_theme' => 'required|in:default,midnight,forest,warm,ocean,slate,rose,dusk,crimson,steel,teal,mocha,arctic,noir']);

        Setting::setValue('portal_theme', $request->input('portal_theme'));

        return back()->with('success', 'Theme updated.');
    }

    public function updateAgeThresholds(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $types = array_keys(Setting::AGE_THRESHOLD_TYPES);
        $rules = [];
        foreach ($types as $type) {
            $rules["yellow_{$type}"] = 'required|integer|min:1|max:8760';
            $rules["orange_{$type}"] = 'required|integer|min:1|max:8760';
            $rules["red_{$type}"]    = 'required|integer|min:1|max:8760';
        }
        $data = $request->validate($rules);

        foreach ($types as $type) {
            Setting::setValue("age_yellow_{$type}", (int) $data["yellow_{$type}"]);
            Setting::setValue("age_orange_{$type}", (int) $data["orange_{$type}"]);
            Setting::setValue("age_red_{$type}",    (int) $data["red_{$type}"]);
        }

        return back()->with('success', 'Age thresholds saved.');
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

    public function updateDevAutofill(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        Setting::setValue('dev_autofill_admin',  $request->boolean('dev_autofill_admin')  ? '1' : '0');
        Setting::setValue('dev_autofill_editor', $request->boolean('dev_autofill_editor') ? '1' : '0');
        Setting::setValue('dev_autofill_reader', $request->boolean('dev_autofill_reader') ? '1' : '0');

        return back()->with('success', 'Autofill settings saved.');
    }

    public function updateQcSavedReplies(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'replies'           => 'nullable|array',
            'replies.*.name'    => 'required|string|max:100',
            'replies.*.body'    => 'required|string|max:2000',
        ]);

        $replies = collect($request->input('replies', []))
            ->map(fn($r) => ['name' => trim($r['name']), 'body' => trim($r['body'])])
            ->filter(fn($r) => $r['name'] !== '' && $r['body'] !== '')
            ->values()
            ->all();

        Setting::setSavedReplies($replies);

        return back()->with('success', 'QC saved replies updated.');
    }

    public function updateEmailNotificationTexts(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $keys  = array_keys(Setting::EMAIL_NOTIFICATION_DEFAULTS);
        $rules = array_fill_keys($keys, 'required|string|max:500');
        $data  = $request->validate($rules);

        foreach ($data as $key => $value) {
            Setting::setValue($key, trim($value));
        }

        return back()->with('success', 'Email notification text saved.');
    }

    public function updateTimezone(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate(['app_timezone' => ['required', 'timezone']]);

        Setting::setValue('app_timezone', $request->input('app_timezone'));

        return back()->with('success', 'Timezone updated.');
    }

    public function emailAllReaders(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $emails = User::where('role', 'reader')
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->values()
            ->all();

        if (empty($emails)) {
            return response()->json(['error' => 'No reader email addresses found.'], 422);
        }

        try {
            $url = app(HelpScoutService::class)->createReaderBroadcastDraft($emails);
            return response()->json(['url' => $url]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateFollowupHtml(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'followup_heading'     => 'nullable|string|max:200',
            'followup_before_html' => 'nullable|string',
            'followup_after_html'  => 'nullable|string',
        ]);

        Setting::setValue('followup_heading',     trim($request->input('followup_heading', '')));
        Setting::setValue('followup_before_html', trim($request->input('followup_before_html', '')));
        Setting::setValue('followup_after_html',  trim($request->input('followup_after_html', '')));

        return back()->with('success', 'Followup form HTML saved.');
    }

    public function updateWordCounts(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $keys  = array_keys(Setting::WORD_COUNT_DEFAULTS);
        $rules = ['wc_enabled' => 'required|boolean'];
        foreach ($keys as $key) {
            if ($key === 'wc_enabled') continue;
            $rules[$key] = 'required|integer|min:0|max:99999';
        }

        $data = $request->validate($rules);

        foreach ($data as $key => $value) {
            Setting::setValue($key, (int) $value);
        }

        return back()->with('success', 'Word count minimums saved.');
    }

    public function resetAllLastSeen(): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        \App\Models\User::query()->update(['last_seen_at' => null]);

        return back()->with('success', 'All last-seen times cleared.');
    }

    public function resetMyLastSeen(): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        auth()->user()->update(['last_seen_at' => null]);

        return back()->with('success', 'Your last-seen time cleared.');
    }
}
