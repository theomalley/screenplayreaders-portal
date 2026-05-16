<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ProfileController;
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

    Route::resource('assignments', AssignmentController::class)->except(['show', 'destroy']);
    Route::post('assignments/{assignment}/accept', [AssignmentController::class, 'accept'])->name('assignments.accept');
    Route::post('assignments/{assignment}/cancel', [AssignmentController::class, 'cancel'])->name('assignments.cancel');
    Route::patch('assignments/{assignment}/status', [AssignmentController::class, 'updateStatus'])->name('assignments.updateStatus');
});

require __DIR__.'/auth.php';
