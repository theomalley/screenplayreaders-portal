<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CoverageSubmissionController;
use App\Http\Controllers\FilenamesController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QcController;
use App\Http\Controllers\RatebookController;
use App\Http\Controllers\EditorProfileController;
use App\Http\Controllers\ReaderProfileController;
use App\Http\Controllers\SettingController;
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
    Route::post('assignments/{assignment}/cancel', [AssignmentController::class, 'cancel'])->name('assignments.cancel');
    Route::patch('assignments/{assignment}/status', [AssignmentController::class, 'updateStatus'])->name('assignments.updateStatus');
    Route::patch('assignments/{assignment}/notes', [AssignmentController::class, 'updateNotes'])->name('assignments.updateNotes');

    Route::get('assignments/{assignment}/coverage', [CoverageSubmissionController::class, 'show'])->name('coverage.show');
    Route::post('assignments/{assignment}/coverage', [CoverageSubmissionController::class, 'store'])->name('coverage.store');
    Route::get('coverage/submitted', [CoverageSubmissionController::class, 'submitted'])->name('coverage.submitted');
    Route::get('assignments/{assignment}/coverage-preview', [CoverageSubmissionController::class, 'coveragePreview'])->name('coverage.preview');

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

    Route::get('/ratebook', [RatebookController::class, 'index'])->name('ratebook.index');
    Route::patch('/ratebook', [RatebookController::class, 'update'])->name('ratebook.update');

    Route::get('/qc', [QcController::class, 'index'])->name('qc.index');
    Route::get('/qc/{assignment}', [QcController::class, 'show'])->name('qc.show');
    Route::post('/qc/{assignment}/regenerate-pdf', [QcController::class, 'regeneratePdf'])->name('qc.regenerate-pdf');
    Route::post('/qc/{assignment}/approve', [QcController::class, 'approve'])->name('qc.approve');
    Route::post('/qc/{assignment}/draft-now', [QcController::class, 'draftNow'])->name('qc.draft-now');
    Route::post('/qc/{assignment}/draft-all', [QcController::class, 'draftAll'])->name('qc.draft-all');

    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');

    Route::get('/admin/editors', [EditorProfileController::class, 'index'])->name('admin.editors.index');
    Route::get('/admin/editors/create', [EditorProfileController::class, 'create'])->name('admin.editors.create');
    Route::post('/admin/editors', [EditorProfileController::class, 'store'])->name('admin.editors.store');
    Route::get('/admin/editors/{user}/edit', [EditorProfileController::class, 'edit'])->name('admin.editors.edit');
    Route::patch('/admin/editors/{user}', [EditorProfileController::class, 'update'])->name('admin.editors.update');
    Route::delete('/admin/editors/{user}', [EditorProfileController::class, 'destroy'])->name('admin.editors.destroy');

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
