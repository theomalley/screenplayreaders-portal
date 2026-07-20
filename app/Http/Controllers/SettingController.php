<?php

// v2.20 — 2026-07-20 | Remove updateTier2ReleaseHours() — superseded by the new dynamic Tiers
//                      settings page (Tools > Settings > Tiers, App\Http\Controllers\TierController),
//                      where per-tier timeout_hours/escalates_to_tier_id now live.
// v2.19 — 2026-07-11 | Add updateTier2ReleaseHours() — admin-configurable hours before an
//                      unaccepted tier-1 assignment also opens to tier-2 readers.
// v2.18 — 2026-06-24 | Split settings into 4 tabbed sub-pages: General, Assignments & Coverage,
//                      Emails & Notifications, Orders & Payments. Each loads only its own data.
// v2.17 — 2026-06-23 | Add discount coupon settings — admin-configurable type, amount, duration,
//                      product restrictions, usage limits for post-coverage coupons.
// v2.16 — 2026-06-16 | updateCapacityOverride() saves capacity_override_excludes_rush_requests;
//                      index() passes $capacityOverrideExcludesRushRequests to view.
// v2.15 — 2026-06-15 | Add updateNotificationHistoryRetention() — admin-configurable
//                      expiry (in days) for Notification History rows, enforced by the
//                      notifications:prune-history scheduled command.
// v2.14 — 2026-06-15 | Add updateBlockedReaderLimits() — admin-configurable cap on how many
//                      readers a customer can block per order (1-reader vs 2R/3R tiers).
// v2.13 — 2026-06-12 | testCompletionDraft(): substitute a placeholder for {{woodiscountcode}}
//                      (no real coupon created for the test draft).
// v2.12 — 2026-06-12 | Completion draft email template — admin-editable body + send-test-draft action.
// v2.11 — 2026-06-10 | Reader download watermark — admin-configurable field toggles + custom text.
// v2.10 — 2026-06-07 | Pay period start/end day+time — admin-configurable via settings.
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

use App\Models\Assignment;
use App\Models\FollowupToken;
use App\Models\Setting;
use App\Models\User;
use App\Services\HelpScoutService;
use App\Support\FilenameGenerator;
use App\Support\PayPeriod;
use App\Support\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $isAdmin       = auth()->user()->isAdmin();
        $portalTheme   = Setting::getValue('portal_theme', 'default');
        $appTimezone   = Setting::getAppTimezone();
        $sessionTimeout = (int) Setting::getValue('session_timeout_minutes', 120);

        $metaFile     = storage_path('app/portal-logo-path.txt');
        $logoUrl      = is_readable($metaFile) ? asset('storage/' . trim(file_get_contents($metaFile))) : null;

        $loginMetaFile = storage_path('app/portal-login-logo-path.txt');
        $loginLogoUrl  = is_readable($loginMetaFile) ? asset('storage/' . trim(file_get_contents($loginMetaFile))) : null;

        $faviconMetaFile = storage_path('app/portal-favicon-path.txt');
        $faviconUrl      = is_readable($faviconMetaFile) ? asset('storage/' . trim(file_get_contents($faviconMetaFile))) : null;

        return view('settings.general', compact(
            'isAdmin', 'portalTheme', 'appTimezone', 'sessionTimeout',
            'logoUrl', 'loginLogoUrl', 'faviconUrl',
        ));
    }

    public function assignments(): View
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $isAdmin = auth()->user()->isAdmin();

        $capacityOverride                     = (int) Setting::getValue('capacity_override', 0);
        $capacityOverrideExcludesRushRequests = (bool) Setting::getValue('capacity_override_excludes_rush_requests', true);
        $ageThresholds     = Setting::getAgeThresholds();
        $ageThresholdTypes = Setting::AGE_THRESHOLD_TYPES;
        $watermarkSettings = $isAdmin ? Setting::getWatermarkSettings() : null;
        $filenameSuffixes  = $isAdmin ? FilenameGenerator::allSuffixes() : null;
        $qcSavedReplies    = Setting::getSavedReplies();
        $coverageSuccessHtml = $isAdmin ? Setting::getValue('coverage_success_html', '') : null;
        $devAutofill = [
            'admin'  => (bool) Setting::getValue('dev_autofill_admin',  false),
            'editor' => (bool) Setting::getValue('dev_autofill_editor', false),
            'reader' => (bool) Setting::getValue('dev_autofill_reader', false),
        ];
        $wordCounts          = $isAdmin ? Setting::getWordCounts() : null;
        $blockedReaderLimits = $isAdmin ? Setting::getBlockedReaderLimits() : null;

        return view('settings.assignments', compact(
            'isAdmin', 'capacityOverride', 'capacityOverrideExcludesRushRequests',
            'ageThresholds', 'ageThresholdTypes', 'watermarkSettings', 'filenameSuffixes',
            'qcSavedReplies', 'coverageSuccessHtml', 'devAutofill', 'wordCounts', 'blockedReaderLimits',
        ));
    }

    public function emails(): View
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $isAdmin              = auth()->user()->isAdmin();
        $emailNotifTexts      = Setting::getEmailNotificationTexts();
        $completionDraftBody  = $isAdmin ? Setting::getCompletionDraftBody() : null;
        $testHelpscoutConvId  = $isAdmin ? Setting::getTestHelpscoutConversationId() : null;
        $followupResponseDraftBody = $isAdmin ? Setting::getFollowupResponseDraftBody() : null;
        $followupBeforeHtml   = Setting::getValue('followup_before_html', '');
        $followupAfterHtml    = Setting::getValue('followup_after_html', '');
        $followupHeading      = Setting::getValue('followup_heading', '');
        $notificationHistoryRetentionDays = $isAdmin ? Setting::getNotificationHistoryRetentionDays() : null;

        return view('settings.emails', compact(
            'isAdmin', 'emailNotifTexts', 'completionDraftBody', 'testHelpscoutConvId',
            'followupResponseDraftBody',
            'followupBeforeHtml', 'followupAfterHtml', 'followupHeading', 'notificationHistoryRetentionDays',
        ));
    }

    public function orders(): View
    {
        abort_unless(auth()->user()->canManageAssignments(), 403);

        $isAdmin         = auth()->user()->isAdmin();
        $appTimezone     = Setting::getAppTimezone();
        $payPeriod       = $isAdmin ? Setting::getPayPeriod() : null;
        $payoutSchedule  = $isAdmin ? Setting::getPayoutSchedule() : null;
        $nextPayout      = $isAdmin ? PayPeriod::nextPayoutDate() : null;
        $srInvoiceAddress = Setting::getValue('sr_invoice_address', '');
        $invoiceEmailBody = Setting::getValue('invoice_email_body', '');
        $discountCoupon   = $isAdmin ? Setting::getDiscountCouponSettings() : null;
        $permissionsGrid  = $isAdmin ? Permission::all() : null;
        $orderLogEditorSettings = $isAdmin ? Setting::getOrderLogEditorSettings() : null;
        $orderLogColumns  = Setting::ORDER_LOG_COLUMNS;
        $editors          = $isAdmin ? User::where('role', 'editor')->where('is_test', false)->with('editorProfile')->orderBy('name')->get() : null;
        $defaultEditorId  = $isAdmin ? Setting::getValue('default_editor_id') : null;

        return view('settings.orders', compact(
            'isAdmin', 'appTimezone', 'payPeriod', 'payoutSchedule', 'nextPayout',
            'srInvoiceAddress', 'invoiceEmailBody', 'discountCoupon',
            'permissionsGrid', 'orderLogEditorSettings', 'orderLogColumns',
            'editors', 'defaultEditorId',
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
        Setting::setValue(
            'capacity_override_excludes_rush_requests',
            $request->boolean('capacity_override_excludes_rush_requests') ? '1' : '0'
        );

        return redirect()->route('settings.assignments')->with('success', $value > 0
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

    public function updateWatermark(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'watermark_show_name'     => 'nullable|boolean',
            'watermark_show_order'    => 'nullable|boolean',
            'watermark_show_datetime' => 'nullable|boolean',
            'watermark_show_ref'      => 'nullable|boolean',
            'watermark_custom_text'   => 'nullable|string|max:200',
        ]);

        foreach (['watermark_show_name', 'watermark_show_order', 'watermark_show_datetime', 'watermark_show_ref'] as $key) {
            Setting::setValue($key, $request->boolean($key) ? '1' : '0');
        }
        Setting::setValue('watermark_custom_text', trim($request->input('watermark_custom_text', '')));

        return back()->with('success', 'Watermark settings saved.');
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

    public function updateFollowupResponseDraft(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate(['followup_response_draft_body' => 'required|string']);

        Setting::setFollowupResponseDraftBody(trim($request->input('followup_response_draft_body')));

        return redirect(route('settings.emails') . '#followup-response-email')->with('success', 'Followup response template saved.');
    }

    public function updateCompletionDraft(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'completion_draft_body'           => 'required|string',
            'test_helpscout_conversation_id'  => 'required|string|max:50',
        ]);

        Setting::setCompletionDraftBody(trim($request->input('completion_draft_body')));
        Setting::setTestHelpscoutConversationId(trim($request->input('test_helpscout_conversation_id')));

        return redirect(route('settings.emails') . '#goback-email')->with('success', 'GoBack email template saved.');
    }

    /**
     * Send the completion draft template to a HelpScout sandbox conversation so it can be
     * previewed without running a real order through writing/QC. Uses an existing is_test
     * order for the followup link (if one exists) and attaches a placeholder PDF.
     */
    public function testCompletionDraft(): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        try {
            $conversationId = Setting::getTestHelpscoutConversationId();

            $testAssignment = Assignment::where('is_test', true)
                ->whereNotNull('assigned_reader_id')
                ->first();

            if ($testAssignment) {
                $orderNumber   = $testAssignment->order_number;
                $assignmentIds = Assignment::where('order_number', $orderNumber)
                    ->whereNotNull('assigned_reader_id')
                    ->pluck('id')->values()->all();
            } else {
                $orderNumber   = 'TEST-DRAFT-PREVIEW';
                $assignmentIds = [];
            }

            $followupUrl = FollowupToken::urlForOrder($orderNumber, $assignmentIds);

            $helpScout = new HelpScoutService();
            $body      = Setting::getCompletionDraftBody();
            $body      = str_replace('{{script_title}}', $testAssignment->script_title ?? 'Sample Script Title', $body);
            $body      = str_replace('{{followup_url}}', $followupUrl, $body);
            // No real coupon is created for the test draft — placeholder only.
            $body      = str_replace('{{woodiscountcode}}', 'SRZ-TEST-CODE', $body);
            $body      = $helpScout->resolveBodyVariables($body, $conversationId);

            $helpScout->createDraftReply($conversationId, $body, [$this->placeholderPdfAttachment()]);

            return response()->json(['url' => 'https://secure.helpscout.net/conversation/' . $conversationId . '/']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function testFollowupResponseDraft(): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        try {
            $conversationId = Setting::getTestHelpscoutConversationId();

            $body = Setting::getFollowupResponseDraftBody();
            $body = str_replace('{{reader_initials}}', 'KD', $body);
            $body = str_replace('{{script_title}}', 'Sample Script Title', $body);
            $body = str_replace('{{reader_response}}', nl2br(e("Thank you for your questions. Here are my thoughts on your screenplay.\n\nThe pacing in Act 2 could benefit from tighter scene transitions, and the protagonist's motivation becomes clearer if you seed it earlier in Act 1.")), $body);

            $helpScout = new HelpScoutService();
            $body      = $helpScout->resolveBodyVariables($body, $conversationId);

            $helpScout->createDraftReply($conversationId, $body);

            return response()->json(['url' => 'https://secure.helpscout.net/conversation/' . $conversationId . '/']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * A small one-page PDF used to exercise the attachment-upload path when sending test drafts.
     */
    private function placeholderPdfAttachment(): array
    {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'TEST COVERAGE', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Ln(10);
        $pdf->MultiCell(0, 8, "This is a placeholder attachment generated by 'Send Test Draft'.\nNo real coverage content.");

        return [
            'fileName' => 'TEST-Coverage.pdf',
            'mimeType' => 'application/pdf',
            'data'     => base64_encode($pdf->Output('S')),
        ];
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

    public function updateBlockedReaderLimits(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'max_blockable_1r'    => 'required|integer|min:0|max:10',
            'max_blockable_multi' => 'required|integer|min:0|max:10',
        ]);

        Setting::setValue('max_blockable_1r',    (int) $request->input('max_blockable_1r'));
        Setting::setValue('max_blockable_multi', (int) $request->input('max_blockable_multi'));

        return back()->with('success', 'Block-reader limits saved.');
    }

    public function updateNotificationHistoryRetention(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate([
            'notification_history_retention_days' => 'required|integer|min:0|max:3650',
        ]);

        Setting::setValue('notification_history_retention_days', (int) $request->input('notification_history_retention_days'));

        return back()->with('success', 'Notification history retention saved.');
    }

    public function uploadPortalPhoto(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate(['photo' => 'required|image|max:4096']);

        $path    = $request->file('photo')->store('editor-photos', 'public');
        $profile = auth()->user()->editorProfile;

        if ($profile) {
            if ($profile->photo) \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->photo);
            $profile->update(['photo' => $path]);
        } else {
            auth()->user()->editorProfile()->create(['photo' => $path]);
        }

        return back()->with('success', 'Portal profile photo updated.');
    }

    public function uploadAboutPhoto(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate(['about_photo' => 'required|image|max:4096']);

        $path    = $request->file('about_photo')->store('editor-photos', 'public');
        $profile = auth()->user()->editorProfile;

        if ($profile) {
            if ($profile->about_photo) \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->about_photo);
            $profile->update(['about_photo' => $path]);
        } else {
            auth()->user()->editorProfile()->create(['about_photo' => $path]);
        }

        return back()->with('success', 'About page photo updated.');
    }

    public function updatePayPeriod(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $validDays = implode(',', range(0, 6));

        $request->validate([
            'period_start_day'  => ['required', "in:{$validDays}"],
            'period_start_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'period_end_day'    => ['required', "in:{$validDays}"],
            'period_end_time'   => ['required', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        foreach (['period_start_time', 'period_end_time'] as $field) {
            [$hh, $mm] = explode(':', $request->input($field));
            abort_if((int) $hh > 23 || (int) $mm > 59, 422, 'Invalid time value.');
        }

        Setting::setValue('period_start_day',  $request->input('period_start_day'));
        Setting::setValue('period_start_time', $request->input('period_start_time'));
        Setting::setValue('period_end_day',    $request->input('period_end_day'));
        Setting::setValue('period_end_time',   $request->input('period_end_time'));

        // Keep payout_day/payout_time in sync so any legacy references stay accurate.
        Setting::setValue('payout_day',  $request->input('period_start_day'));
        Setting::setValue('payout_time', $request->input('period_start_time'));

        return back()->with('success', 'Pay period updated.');
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

    public function updateDefaultEditor(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'editor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'editor')],
        ]);

        if (empty($data['editor_id'])) {
            Setting::where('key', 'default_editor_id')->delete();
        } else {
            Setting::setValue('default_editor_id', $data['editor_id']);
        }

        return back()->with('success', 'Default editor updated.');
    }

    public function updateOrderLogEditor(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'blocked_product_ids' => ['nullable', 'string', 'max:500'],
            'hidden_columns'      => ['nullable', 'array'],
            'hidden_columns.*'    => ['string', Rule::in(array_keys(Setting::ORDER_LOG_COLUMNS))],
        ]);

        Setting::setValue('order_log_editor_hide_zero_dollar',    $request->boolean('hide_zero_dollar')    ? '1' : '0');
        Setting::setValue('order_log_editor_hide_woo_orders',     $request->boolean('hide_woo_orders')     ? '1' : '0');
        Setting::setValue('order_log_editor_hide_invoice_orders', $request->boolean('hide_invoice_orders') ? '1' : '0');

        $ids = implode(',', array_filter(array_map(fn($v) => (string)(int)trim($v), explode(',', $data['blocked_product_ids'] ?? ''))));
        Setting::setValue('order_log_editor_blocked_product_ids', $ids);

        Setting::setValue('order_log_editor_hidden_columns', json_encode($data['hidden_columns'] ?? []));

        return back()->with('success', 'Order log editor visibility updated.');
    }

    public function updateDiscountCoupon(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'discount_coupon_prefix'               => 'required|string|min:1|max:10|regex:/^[A-Za-z0-9\-_]+$/',
            'discount_coupon_type'                 => 'required|in:fixed_cart,percent',
            'discount_coupon_amount'               => 'required|numeric|min:0|max:99999',
            'discount_coupon_duration_days'        => 'required|integer|min:1|max:3650',
            'discount_coupon_product_ids'          => 'nullable|string|max:500',
            'discount_coupon_individual_use'       => 'nullable|boolean',
            'discount_coupon_free_shipping'        => 'nullable|boolean',
            'discount_coupon_usage_limit'          => 'required|integer|min:0|max:9999',
            'discount_coupon_usage_limit_per_user' => 'required|integer|min:0|max:9999',
            'discount_coupon_description'          => 'nullable|string|max:500',
        ]);

        Setting::setValue('discount_coupon_prefix', strtoupper(trim($data['discount_coupon_prefix'])));
        Setting::setValue('discount_coupon_type',   $data['discount_coupon_type']);
        Setting::setValue('discount_coupon_amount', number_format((float) $data['discount_coupon_amount'], 2, '.', ''));
        Setting::setValue('discount_coupon_duration_days', (int) $data['discount_coupon_duration_days']);

        $ids = implode(',', array_filter(array_map(fn($v) => (string)(int)trim($v), explode(',', $data['discount_coupon_product_ids'] ?? ''))));
        Setting::setValue('discount_coupon_product_ids', $ids);

        Setting::setValue('discount_coupon_individual_use',       $request->boolean('discount_coupon_individual_use') ? '1' : '0');
        Setting::setValue('discount_coupon_free_shipping',        $request->boolean('discount_coupon_free_shipping') ? '1' : '0');
        Setting::setValue('discount_coupon_usage_limit',          (int) $data['discount_coupon_usage_limit']);
        Setting::setValue('discount_coupon_usage_limit_per_user', (int) $data['discount_coupon_usage_limit_per_user']);
        Setting::setValue('discount_coupon_description',          trim($data['discount_coupon_description'] ?? ''));

        return back()->with('success', 'Discount coupon settings saved.');
    }

    public function addCommissionProduct(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'product_id'    => ['required', 'integer', 'min:1'],
            'product_label' => ['required', 'string', 'max:100'],
            'commission'    => ['boolean'],
        ]);

        $custom = json_decode(Setting::getValue('commission_custom_products', '[]'), true) ?: [];

        foreach ($custom as $p) {
            if ((int) $p['id'] === (int) $data['product_id']) {
                return back()->with('error', 'Product ID ' . $data['product_id'] . ' already exists.');
            }
        }

        if (isset(\App\Models\EditorProductCommission::PRODUCTS[(int) $data['product_id']])) {
            return back()->with('error', 'Product ID ' . $data['product_id'] . ' is already a built-in product.');
        }

        $custom[] = [
            'id'         => (int) $data['product_id'],
            'label'      => $data['product_label'],
            'commission' => $request->boolean('commission'),
        ];

        Setting::setValue('commission_custom_products', json_encode($custom));

        return back()->with('success', 'Product "' . $data['product_label'] . '" added to commission list.');
    }

    public function removeCommissionProduct(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $productId = (int) $request->input('product_id');
        $custom    = json_decode(Setting::getValue('commission_custom_products', '[]'), true) ?: [];
        $custom    = array_values(array_filter($custom, fn($p) => (int) $p['id'] !== $productId));

        Setting::setValue('commission_custom_products', json_encode($custom));

        return back()->with('success', 'Product removed from commission list.');
    }
}
