<?php

use App\Http\Controllers\AdminApprovalController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AssignmentController;
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
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\WooOrderController;
use Illuminate\Support\Facades\Route;

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
    Route::patch('/profile/bio', [ProfileController::class, 'updateBio'])->name('profile.bio');
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
    Route::get('assignments/{assignment}/coverage-pdf', [AssignmentController::class, 'streamCoverage'])->name('assignments.streamCoverage');
    Route::post('assignments/{assignment}/script', [AssignmentController::class, 'uploadScript'])->name('assignments.uploadScript');
    Route::post('assignments/{assignment}/remove-pages', [AssignmentController::class, 'removePages'])->name('assignments.removePages');
    Route::post('assignments/{assignment}/add-reader', [AssignmentController::class, 'addReader'])->name('assignments.addReader');
    Route::post('assignments/{assignment}/accept', [AssignmentController::class, 'accept'])->name('assignments.accept');
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

    Route::post('/admin/approvals/bio/{user}/approve',   [AdminApprovalController::class, 'approveBio'])->name('admin.approvals.bio.approve');
    Route::post('/admin/approvals/bio/{user}/reject',    [AdminApprovalController::class, 'rejectBio'])->name('admin.approvals.bio.reject');
    Route::post('/admin/approvals/photo/{user}/approve', [AdminApprovalController::class, 'approvePhoto'])->name('admin.approvals.photo.approve');
    Route::post('/admin/approvals/photo/{user}/reject',  [AdminApprovalController::class, 'rejectPhoto'])->name('admin.approvals.photo.reject');

    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::post('/announcements/{announcement}/read', [AnnouncementController::class, 'markRead'])->name('announcements.mark-read');
    Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->name('announcements.dismiss');

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
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    Route::get('/woo-orders', [WooOrderController::class, 'index'])->name('woo-orders.index');
    Route::get('/woo-orders/{id}', [WooOrderController::class, 'show'])->name('woo-orders.show')->whereNumber('id');
    Route::post('/woo-orders/{id}/refund', [WooOrderController::class, 'refund'])->name('woo-orders.refund')->whereNumber('id');
    Route::post('/woo-orders/{id}/resend-email', [WooOrderController::class, 'resendEmail'])->name('woo-orders.resend-email')->whereNumber('id');

    Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue.index');
    Route::get('/reader-earnings', [ReaderEarningsController::class, 'index'])->name('reader-earnings.index');
    Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');
    Route::get('/reader-pay', [ReaderPayController::class, 'index'])->name('reader-pay.index');
    Route::post('/reader-pay/{reader}/mark-paid', [ReaderPayController::class, 'markPaid'])->name('reader-pay.mark-paid');
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
});

require __DIR__.'/auth.php';
