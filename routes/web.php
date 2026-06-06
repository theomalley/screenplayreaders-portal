<?php

use App\Http\Controllers\AdminApprovalController;
use App\Http\Controllers\FollowupFormController;
use App\Http\Controllers\FollowupQuestionController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AssignmentNoteController;
use App\Http\Controllers\AssignmentEditorNoteController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CoverageSubmissionController;
use App\Http\Controllers\EditorPayController;
use App\Http\Controllers\EditorPaymentsController;
use App\Http\Controllers\EditorProfileController;
use App\Http\Controllers\FilenamesController;
use App\Http\Controllers\OrderLogController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QcController;
use App\Http\Controllers\RatebookController;
use App\Http\Controllers\ReaderPayController;
use App\Http\Controllers\ReaderProfileController;
use App\Http\Controllers\ReaderEarningsController;
use App\Http\Controllers\EditorEarningsController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayoutScheduleController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\WooOrderController;
use App\Http\Controllers\Marketing\EmailCampaignController;
use App\Http\Controllers\Marketing\EmailTemplateController;
use Illuminate\Support\Facades\Route;

// Public followup form — no auth required
Route::get('/followup/{token}',  [FollowupFormController::class, 'show'])->name('followup.show');
Route::post('/followup/{token}', [FollowupFormController::class, 'submit'])->name('followup.submit');

// Tokenized admin quick-login (public, rate-limited)
Route::get('/ql/{token}', [\App\Http\Controllers\QuickLoginController::class, 'login'])->name('quick-login');

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return redirect()->route('assignments.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo');
    Route::post('/profile/about-photo', [ProfileController::class, 'uploadAboutPhoto'])->name('profile.about-photo');
    Route::patch('/profile/bio', [ProfileController::class, 'updateBio'])->name('profile.bio');
    Route::patch('/profile/custom-message', [ProfileController::class, 'updateCustomMessage'])->name('profile.custom-message');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/availability', [AvailabilityController::class, 'edit'])->name('availability.edit');
    Route::patch('/availability', [AvailabilityController::class, 'update'])->name('availability.update');

    Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
    Route::post('assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::get('assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::get('assignments/{assignment}/edit', [AssignmentController::class, 'edit'])->name('assignments.edit');
    Route::patch('assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
    Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
    Route::get('assignments/{assignment}/script', [AssignmentController::class, 'streamScript'])->name('assignments.streamScript');
    Route::get('assignments/{assignment}/script/download', [AssignmentController::class, 'downloadScript'])->name('assignments.downloadScript');
    Route::get('assignments/{assignment}/coverage-pdf', [AssignmentController::class, 'streamCoverage'])->name('assignments.streamCoverage');
    Route::post('assignments/{assignment}/script', [AssignmentController::class, 'uploadScript'])->name('assignments.uploadScript');
    Route::post('assignments/{assignment}/remove-pages', [AssignmentController::class, 'removePages'])->name('assignments.removePages');
    Route::post('assignments/{assignment}/add-reader', [AssignmentController::class, 'addReader'])->name('assignments.addReader');
    Route::post('assignments/{assignment}/accept', [AssignmentController::class, 'accept'])->name('assignments.accept');
    Route::post('assignments/{assignment}/over-120', [AssignmentController::class, 'over120'])->name('assignments.over-120');
    Route::post('assignments/{assignment}/followup-token', [AssignmentController::class, 'generateFollowupToken'])->name('assignments.followup-token');
    Route::post('assignments/{assignment}/followup-reset', [AssignmentController::class, 'resetFollowupToken'])->name('assignments.followup-reset');
    Route::post('assignments/{assignment}/decline', [AssignmentController::class, 'decline'])->name('assignments.decline');
    Route::post('assignments/{assignment}/cancel', [AssignmentController::class, 'cancel'])->name('assignments.cancel');
    Route::patch('assignments/{assignment}/status', [AssignmentController::class, 'updateStatus'])->name('assignments.updateStatus');
    Route::patch('assignments/{assignment}/notes', [AssignmentController::class, 'updateNotes'])->name('assignments.updateNotes');

    Route::get('assignments/{assignment}/coverage', [CoverageSubmissionController::class, 'show'])->name('coverage.show');
    Route::post('assignments/{assignment}/coverage', [CoverageSubmissionController::class, 'store'])->name('coverage.store');
    Route::post('assignments/{assignment}/coverage/draft', [CoverageSubmissionController::class, 'saveDraft'])->name('coverage.draft');
    Route::get('coverage/submitted', [CoverageSubmissionController::class, 'submitted'])->name('coverage.submitted');
    Route::get('assignments/{assignment}/coverage-preview', [CoverageSubmissionController::class, 'coveragePreview'])->name('coverage.preview');

    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team/{user}/toggle-visibility', [TeamController::class, 'toggleVisibility'])->name('team.toggle-visibility');

    Route::get('/readers', [ReaderProfileController::class, 'index'])->name('readers.index');
    Route::get('/readers/create', [ReaderProfileController::class, 'create'])->name('readers.create');
    Route::post('/readers', [ReaderProfileController::class, 'store'])->name('readers.store');
    Route::get('/readers/{user}/edit', [ReaderProfileController::class, 'edit'])->name('readers.edit');
    Route::patch('/readers/{user}', [ReaderProfileController::class, 'update'])->name('readers.update');
    Route::delete('/readers/{user}', [ReaderProfileController::class, 'destroy'])->name('readers.destroy');

    Route::get('/manual', [ManualController::class, 'show'])->name('manual.show');
    Route::get('/manual/frame', [ManualController::class, 'frame'])->name('manual.frame');
    Route::patch('/manual', [ManualController::class, 'update'])->name('manual.update');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings/logo', [SettingController::class, 'uploadLogo'])->name('settings.logo');
    Route::post('/settings/login-logo', [SettingController::class, 'uploadLoginLogo'])->name('settings.login-logo');
    Route::post('/settings/favicon', [SettingController::class, 'uploadFavicon'])->name('settings.favicon');
    Route::get('/settings/coverage-success', [SettingController::class, 'editCoverageSuccess'])->name('settings.coverage-success');
    Route::patch('/settings/coverage-success', [SettingController::class, 'updateCoverageSuccess'])->name('settings.coverage-success.update');
    Route::patch('/settings/capacity-override', [SettingController::class, 'updateCapacityOverride'])->name('settings.capacity-override');
    Route::patch('/settings/session-timeout', [SettingController::class, 'updateSessionTimeout'])->name('settings.session-timeout');
    Route::patch('/settings/invoice', [SettingController::class, 'updateInvoiceSettings'])->name('settings.invoice');
    Route::patch('/settings/theme', [SettingController::class, 'updateTheme'])->name('settings.theme');
    Route::patch('/settings/age-thresholds', [SettingController::class, 'updateAgeThresholds'])->name('settings.age-thresholds');
    Route::patch('/settings/timezone', [SettingController::class, 'updateTimezone'])->name('settings.timezone');
    Route::patch('/settings/dev-autofill', [SettingController::class, 'updateDevAutofill'])->name('settings.dev-autofill');
    Route::patch('/settings/qc-saved-replies', [SettingController::class, 'updateQcSavedReplies'])->name('settings.qc-saved-replies');
    Route::patch('/settings/email-notifications', [SettingController::class, 'updateEmailNotificationTexts'])->name('settings.email-notifications');
    Route::patch('/settings/followup-html', [SettingController::class, 'updateFollowupHtml'])->name('settings.followup-html');
    Route::patch('/settings/word-counts', [SettingController::class, 'updateWordCounts'])->name('settings.word-counts');
    Route::post('/settings/portal-photo', [SettingController::class, 'uploadPortalPhoto'])->name('settings.portal-photo');
    Route::post('/settings/about-photo', [SettingController::class, 'uploadAboutPhoto'])->name('settings.about-photo');
    Route::post('/settings/reset-last-seen-all', [SettingController::class, 'resetAllLastSeen'])->name('settings.reset-last-seen-all');
    Route::post('/settings/reset-last-seen-me', [SettingController::class, 'resetMyLastSeen'])->name('settings.reset-last-seen-me');
    Route::post('/settings/quick-login/generate', [\App\Http\Controllers\QuickLoginController::class, 'generate'])->name('quick-login.generate');
    Route::post('/settings/quick-login/landing', [\App\Http\Controllers\QuickLoginController::class, 'saveLanding'])->name('quick-login.landing');
    Route::delete('/settings/quick-login', [\App\Http\Controllers\QuickLoginController::class, 'revoke'])->name('quick-login.revoke');
    Route::post('/settings/email-all-readers', [SettingController::class, 'emailAllReaders'])->name('settings.email-all-readers');

    // Assignment notes (reader → admin messaging)
    Route::post('/assignments/{assignment}/notes',          [AssignmentNoteController::class, 'store'])->name('assignment-notes.store');
    Route::post('/assignment-notes/{note}/reply',           [AssignmentNoteController::class, 'reply'])->name('assignment-notes.reply');
    Route::post('/assignment-notes/{note}/dismiss',         [AssignmentNoteController::class, 'dismiss'])->name('assignment-notes.dismiss');
    Route::post('/assignment-note-replies/{reply}/dismiss', [AssignmentNoteController::class, 'dismissReply'])->name('assignment-note-replies.dismiss');

    // Assignment editor notes (admin/editor internal — not visible to readers)
    Route::post('/assignments/{assignment}/editor-notes',        [AssignmentEditorNoteController::class, 'store'])->name('assignment-editor-notes.store');
    Route::delete('/assignment-editor-notes/{note}',             [AssignmentEditorNoteController::class, 'destroy'])->name('assignment-editor-notes.destroy');

    // Personal reading notes (private per-user — for note-taking while reading a script)
    Route::get('/assignments/{assignment}/reading-notes',   [\App\Http\Controllers\ReaderScriptNoteController::class, 'index'])->name('reading-notes.index');
    Route::post('/assignments/{assignment}/reading-notes',  [\App\Http\Controllers\ReaderScriptNoteController::class, 'store'])->name('reading-notes.store');
    Route::delete('/reading-notes/{note}',                  [\App\Http\Controllers\ReaderScriptNoteController::class, 'destroy'])->name('reading-notes.destroy');

    // Followup question management (admin/editor)
    Route::get('/followup-history/{orderNumber}',       [FollowupQuestionController::class, 'history'])->name('followups.history');
    Route::delete('/followup-tokens/{followupToken}',   [FollowupQuestionController::class, 'destroyToken'])->name('followupTokens.destroy');
    Route::patch('/followups/{followup}',          [FollowupQuestionController::class, 'update'])->name('followups.update');
    Route::delete('/followups/{followup}',         [FollowupQuestionController::class, 'destroy'])->name('followups.destroy');
    Route::post('/followups/{followup}/complete',  [FollowupQuestionController::class, 'complete'])->name('followups.complete');
    // Reader response
    Route::post('/followups/{followup}/respond',   [FollowupQuestionController::class, 'respond'])->name('followups.respond');

    Route::post('/admin/approvals/bio/{user}/approve',   [AdminApprovalController::class, 'approveBio'])->name('admin.approvals.bio.approve');
    Route::post('/admin/approvals/bio/{user}/reject',    [AdminApprovalController::class, 'rejectBio'])->name('admin.approvals.bio.reject');
    Route::post('/admin/approvals/photo/{user}/approve',       [AdminApprovalController::class, 'approvePhoto'])->name('admin.approvals.photo.approve');
    Route::post('/admin/approvals/photo/{user}/reject',        [AdminApprovalController::class, 'rejectPhoto'])->name('admin.approvals.photo.reject');
    Route::post('/admin/approvals/about-photo/{user}/approve', [AdminApprovalController::class, 'approveAboutPhoto'])->name('admin.approvals.about-photo.approve');
    Route::post('/admin/approvals/about-photo/{user}/reject',  [AdminApprovalController::class, 'rejectAboutPhoto'])->name('admin.approvals.about-photo.reject');

    Route::get('/announcements/history', [AnnouncementController::class, 'history'])->name('announcements.history');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::post('/announcements/{announcement}/read', [AnnouncementController::class, 'markRead'])->name('announcements.mark-read');
    Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->name('announcements.dismiss');

    Route::post('/admin/impersonate/{user}', [\App\Http\Controllers\ImpersonateController::class, 'start'])->name('impersonate.start');
    Route::post('/admin/impersonate-stop', [\App\Http\Controllers\ImpersonateController::class, 'stop'])->name('impersonate.stop');

    Route::get('/admin/test-data', [\App\Http\Controllers\TestDataController::class, 'index'])->name('test-data.index');
    Route::post('/admin/test-data/script', [\App\Http\Controllers\TestDataController::class, 'saveTestScript'])->name('test-data.script');
    Route::post('/admin/test-data/seed', [\App\Http\Controllers\TestDataController::class, 'seed'])->name('test-data.seed');
    Route::post('/admin/test-data/reset', [\App\Http\Controllers\TestDataController::class, 'reset'])->name('test-data.reset');
    Route::delete('/admin/test-data', [\App\Http\Controllers\TestDataController::class, 'destroy'])->name('test-data.destroy');
    Route::post('/admin/test-data/auto-reset', [\App\Http\Controllers\TestDataController::class, 'toggleAutoReset'])->name('test-data.auto-reset');

    Route::get('/staff/{user}/card', [\App\Http\Controllers\StaffCardController::class, 'card'])->name('staff.card');
    Route::get('/staff/{user}/reader-card', [\App\Http\Controllers\StaffCardController::class, 'readerCard'])->name('staff.reader-card');
    Route::get('/staff/{user}/draft-email', [\App\Http\Controllers\StaffCardController::class, 'draftEmail'])->name('staff.draft-email');

    Route::get('/ratebook', [RatebookController::class, 'index'])->name('ratebook.index');
    Route::patch('/ratebook', [RatebookController::class, 'update'])->name('ratebook.update');

    Route::get('/qc', [QcController::class, 'index'])->name('qc.index');
    Route::get('/qc/{assignment}', [QcController::class, 'show'])->name('qc.show');
    Route::post('/qc/{assignment}/regenerate-pdf', [QcController::class, 'regeneratePdf'])->name('qc.regenerate-pdf');
    Route::post('/qc/{assignment}/approve', [QcController::class, 'approve'])->name('qc.approve');
    Route::post('/qc/{assignment}/send-back', [QcController::class, 'sendBack'])->name('qc.send-back');
    Route::post('/qc/{assignment}/draft-now', [QcController::class, 'draftNow'])->name('qc.draft-now');
    Route::post('/qc/{assignment}/draft-all', [QcController::class, 'draftAll'])->name('qc.draft-all');

    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');

    Route::get('/order-log', [OrderLogController::class, 'index'])->name('order-log.index');
    Route::get('/order-log/create', [OrderLogController::class, 'create'])->name('order-log.create');
    Route::post('/order-log', [OrderLogController::class, 'store'])->name('order-log.store');
    Route::get('/order-log/{orderLog}/edit', [OrderLogController::class, 'edit'])->name('order-log.edit');
    Route::get('/order-log/{orderLog}/invoice-pdf', [OrderLogController::class, 'invoicePdf'])->name('order-log.invoice-pdf');
    Route::patch('/order-log/{orderLog}', [OrderLogController::class, 'update'])->name('order-log.update');
    Route::delete('/order-log/{orderLog}', [OrderLogController::class, 'destroy'])->name('order-log.destroy');

    // Clients + Invoicing
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

    Route::get('/invoicing', [InvoiceController::class, 'index'])->name('invoicing.index');
    Route::get('/invoicing/create', [InvoiceController::class, 'create'])->name('invoicing.create');
    Route::post('/invoicing', [InvoiceController::class, 'store'])->name('invoicing.store');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::patch('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
    Route::post('/invoices/{invoice}/resend', [InvoiceController::class, 'resend'])->name('invoices.resend');
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('/invoices/{invoice}/mark-outstanding', [InvoiceController::class, 'markOutstanding'])->name('invoices.mark-outstanding');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    Route::get('/woo-orders', [WooOrderController::class, 'index'])->name('woo-orders.index');
    Route::get('/woo-orders/{id}', [WooOrderController::class, 'show'])->name('woo-orders.show')->whereNumber('id');
    Route::post('/woo-orders/{id}/refund', [WooOrderController::class, 'refund'])->name('woo-orders.refund')->whereNumber('id');
    Route::post('/woo-orders/{id}/resend-email', [WooOrderController::class, 'resendEmail'])->name('woo-orders.resend-email')->whereNumber('id');

    Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue.index');
    Route::get('/revenue/by-customer', [RevenueController::class, 'byCustomer'])->name('revenue.by-customer');
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('/payroll/export-1099', [PayrollController::class, 'export1099'])->name('payroll.export-1099');
    Route::patch('/payroll/schedule', [PayoutScheduleController::class, 'update'])->name('payroll.schedule.update');
    Route::patch('/payroll/schedule/override', [PayoutScheduleController::class, 'setOverride'])->name('payroll.schedule.override');
    Route::get('/reader-earnings', [ReaderEarningsController::class, 'index'])->name('reader-earnings.index');
    Route::get('/editor-earnings', [EditorEarningsController::class, 'index'])->name('editor-earnings.index');
    Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');
    Route::get('/reader-pay', [ReaderPayController::class, 'index'])->name('reader-pay.index');
    Route::post('/reader-pay/{reader}/mark-paid', [ReaderPayController::class, 'markPaid'])->name('reader-pay.mark-paid');
    Route::post('/reader-pay/{reader}/clear-unpaid', [ReaderPayController::class, 'clearUnpaidBatch'])->name('reader-pay.clear-unpaid');
    Route::post('/reader-pay/{reader}/remove-batch', [ReaderPayController::class, 'removeHistoryBatch'])->name('reader-pay.remove-batch');
    Route::post('/reader-pay/{reader}/mark-unpaid', [ReaderPayController::class, 'markUnpaid'])->name('reader-pay.mark-unpaid');
    Route::post('/reader-pay/{reader}/adjustment', [ReaderPayController::class, 'addAdjustment'])->name('reader-pay.add-adjustment');
    Route::delete('/reader-pay/adjustment/{adjustment}', [ReaderPayController::class, 'deleteAdjustment'])->name('reader-pay.delete-adjustment');

    Route::get('/payments', [PaymentsController::class, 'index'])->name('payments.index');

    Route::get('/admin/editors', [EditorProfileController::class, 'index'])->name('admin.editors.index');
    Route::get('/admin/editors/create', [EditorProfileController::class, 'create'])->name('admin.editors.create');
    Route::post('/admin/editors', [EditorProfileController::class, 'store'])->name('admin.editors.store');
    Route::get('/admin/editors/{user}/edit', [EditorProfileController::class, 'edit'])->name('admin.editors.edit');
    Route::patch('/admin/editors/{user}', [EditorProfileController::class, 'update'])->name('admin.editors.update');
    Route::patch('/admin/editors/{user}/rates', [EditorProfileController::class, 'updateRates'])->name('admin.editors.updateRates');
    Route::patch('/admin/editors/{user}/commissions', [EditorProfileController::class, 'saveCommissions'])->name('admin.editors.commissions');
    Route::delete('/admin/editors/{user}', [EditorProfileController::class, 'destroy'])->name('admin.editors.destroy');

    Route::get('/editor-pay', [EditorPayController::class, 'index'])->name('editor-pay.index');
    Route::post('/editor-pay/mark-paid', [EditorPayController::class, 'markPaid'])->name('editor-pay.mark-paid');
    Route::post('/editor-pay/adjustment', [EditorPayController::class, 'addAdjustment'])->name('editor-pay.add-adjustment');
    Route::delete('/editor-pay/adjustment/{adjustment}', [EditorPayController::class, 'deleteAdjustment'])->name('editor-pay.delete-adjustment');

    Route::get('/editor-payments', [EditorPaymentsController::class, 'index'])->name('editor-payments.index');

    Route::get('/admin/permissions', [PermissionsController::class, 'index'])->name('admin.permissions');
    Route::post('/admin/permissions', [PermissionsController::class, 'update'])->name('admin.permissions.update');

    Route::get('/admin/filenames', [FilenamesController::class, 'index'])->name('admin.filenames');
    Route::patch('/admin/filenames', [FilenamesController::class, 'update'])->name('admin.filenames.update');

    // --- Admin Drive connection test (dev utility — admin/editor only) ---
    Route::get('/admin/drive-test', function () {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);
        return view('admin.drive-test');
    })->name('admin.drive-test');

    Route::post('/admin/drive-test', function (\Illuminate\Http\Request $request, \App\Services\GoogleDriveService $drive) {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);
        $request->validate(['script' => 'required|file|mimes:pdf|max:51200']);
        $fileId   = $drive->uploadScript('drive-test', $request->file('script')->getPathname());
        $viewLink = $drive->viewLink($fileId);
        $dlUrl    = $drive->downloadUrl($fileId);
        return back()->with(compact('fileId', 'viewLink', 'dlUrl'));
    })->name('admin.drive-test.post');

    // --- Marketing (admin only) ---
    Route::prefix('marketing')->name('marketing.')->group(function () {
        // Email Templates
        Route::get('/email-templates',                          [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::post('/email-templates',                         [EmailTemplateController::class, 'store'])->name('email-templates.store');
        Route::get('/email-templates/{emailTemplate}',          [EmailTemplateController::class, 'show'])->name('email-templates.show');
        Route::patch('/email-templates/{emailTemplate}',        [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::delete('/email-templates/{emailTemplate}',       [EmailTemplateController::class, 'destroy'])->name('email-templates.destroy');

        // Static endpoints must come before {emailCampaign} to avoid route conflicts
        Route::post('/email-campaigns/preview',      [EmailCampaignController::class, 'preview'])->name('email-campaigns.preview');
        Route::post('/email-campaigns/upload-image', [EmailCampaignController::class, 'uploadImage'])->name('email-campaigns.upload-image');
        Route::patch('/email-campaigns/reorder',     [EmailCampaignController::class, 'reorder'])->name('email-campaigns.reorder');

        Route::get('/email-campaigns',                    [EmailCampaignController::class, 'index'])->name('email-campaigns.index');
        Route::get('/email-campaigns/create',             [EmailCampaignController::class, 'create'])->name('email-campaigns.create');
        Route::post('/email-campaigns',                   [EmailCampaignController::class, 'store'])->name('email-campaigns.store');
        Route::get('/email-campaigns/{emailCampaign}/edit',    [EmailCampaignController::class, 'edit'])->name('email-campaigns.edit');
        Route::patch('/email-campaigns/{emailCampaign}',       [EmailCampaignController::class, 'update'])->name('email-campaigns.update');
        Route::delete('/email-campaigns/{emailCampaign}',      [EmailCampaignController::class, 'destroy'])->name('email-campaigns.destroy');

        Route::post('/email-campaigns/{emailCampaign}/duplicate',  [EmailCampaignController::class, 'duplicate'])->name('email-campaigns.duplicate');
        Route::post('/email-campaigns/{emailCampaign}/send-test',  [EmailCampaignController::class, 'sendTest'])->name('email-campaigns.send-test');
        Route::post('/email-campaigns/{emailCampaign}/send-live',  [EmailCampaignController::class, 'sendLive'])->name('email-campaigns.send-live');
        Route::post('/email-campaigns/{emailCampaign}/status',     [EmailCampaignController::class, 'updateStatus'])->name('email-campaigns.status');
    });
});

require __DIR__.'/auth.php';
