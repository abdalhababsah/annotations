<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\TaskController as StaffTaskController;

Route::middleware(['auth'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');

    // Attempt (annotator)
    Route::get('/projects/{project}/attempt/next',      [StaffTaskController::class, 'nextAttempt'])->name('attempt.next');
    Route::get('/projects/{project}/attempt/{task}',    [StaffTaskController::class, 'showAttempt'])->name('attempt.show');
    Route::post('/projects/{project}/attempt/{task}/draft',  [StaffTaskController::class, 'saveAttemptDraft'])->name('attempt.draft');
    Route::post('/projects/{project}/attempt/{task}/submit', [StaffTaskController::class, 'submitAttempt'])->name('attempt.submit');
    Route::post('/projects/{project}/attempt/{task}/skip',   [StaffTaskController::class, 'skipAttempt'])->name('attempt.skip');

    // Review (reviewer)
    Route::get('/projects/{project}/review/next',       [StaffTaskController::class, 'nextReview'])->name('review.next');
    Route::get('/projects/{project}/review/{review}',    [StaffTaskController::class, 'showReview'])->name('review.show');
    Route::post('/projects/{project}/review/{review}/draft',   [StaffTaskController::class, 'saveReviewDraft'])->name('review.draft');
    Route::post('/projects/{project}/review/{review}/approve', [StaffTaskController::class, 'approveReview'])->name('review.approve');
    Route::post('/projects/{project}/review/{review}/skip',    [StaffTaskController::class, 'skipReview'])->name('review.skip');

    // Success page after submit/approve/skip
    Route::get('/projects/{project}/flow/success', [StaffTaskController::class, 'success'])->name('flow.success');
});
