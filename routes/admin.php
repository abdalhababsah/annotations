<?php

use App\Http\Controllers\Admin\AudioFileController;
use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectMembersController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
// routes/web.php
use App\Http\Controllers\Admin\ProjectTasksController;



// Admin Routes - System Admin Only
Route::middleware(['auth', 'role:SystemAdmin'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('{user}', [UserController::class, 'update'])->name('update');
        Route::post('{user}/toggle-active', [UserController::class, 'toggleActive'])->name('toggle-active');
        Route::post('{user}/toggle-verified', [UserController::class, 'toggleVerified'])->name('toggle-verified');
        Route::post('{user}/send-password-reset', [UserController::class, 'sendPasswordReset'])->name('send-password-reset');
    });
});

// Project Routes - Accessible by System Admins and Project Owners
Route::middleware(['auth', 'role:SystemAdmin'])->prefix('admin')->name('admin.')->group(function () {

    // =====================================================
    // PROJECT ROUTES
    // =====================================================
    Route::prefix('projects')->name('projects.')->group(function () {


        // ===== MAIN PROJECT CRUD ROUTES =====
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::get('/create', [ProjectController::class, 'create'])->name('create');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::patch('/{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');

        // ===== PROJECT CREATION WIZARD ROUTES =====
        // Step 1: Basic Info
        Route::post('/store-step-one', [ProjectController::class, 'storeStepOne'])->name('store-step-one');

        // Step 2: Annotation Dimensions
        Route::get('/{project}/step-two', [ProjectController::class, 'createStepTwo'])->name('create.step-two');
        Route::post('/{project}/store-step-two', [ProjectController::class, 'storeStepTwo'])->name('store-step-two');

        // Step 3: Review & Finalize
        Route::get('/{project}/step-three', [ProjectController::class, 'createStepThree'])->name('create.step-three');
        Route::post('/{project}/finalize', [ProjectController::class, 'finalizeProject'])->name('finalize');

        // ===== SEGMENTATION LABEL ROUTES =====
        Route::post('/labels', [ProjectController::class, 'createSegmentationLabel'])->name('labels.create');

        // ===== PROJECT STATUS MANAGEMENT ROUTES =====
        Route::post('/{project}/quick-activate', [ProjectController::class, 'quickActivate'])->name('quick-activate');
        Route::patch('/{project}/status', [ProjectController::class, 'updateStatus'])->name('update-status');
        Route::patch('/{project}/archive', [ProjectController::class, 'archive'])->name('archive');
        Route::patch('/{project}/restore', [ProjectController::class, 'restore'])->name('restore');

        // ===== PROJECT UTILITY ROUTES =====
        Route::post('/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('duplicate');
        Route::get('/{project}/setup-status', [ProjectController::class, 'getSetupStatus'])->name('setup-status');

        // ===== TEAM MANAGEMENT ROUTES =====
        Route::get('/{project}/available-users', [ProjectController::class, 'getAvailableUsers'])->name('available-users');


        Route::get('{project}/members', [ProjectMembersController::class, 'index'])
            ->name('members.index');

        // Data endpoints
        Route::get('{project}/members/available-users', [ProjectMembersController::class, 'availableUsers'])
            ->name('members.available-users');

        Route::post('{project}/members', [ProjectMembersController::class, 'store'])
            ->name('members.store');

        Route::patch('{project}/members/{member}', [ProjectMembersController::class, 'update'])
            ->name('members.update');

        Route::delete('{project}/members/{member}', [ProjectMembersController::class, 'destroy'])
            ->name('members.destroy');


        // ===== AUDIO FILES (per project) =====
        Route::prefix('{project}/audio-files')->name('audio-files.')->group(function () {
            Route::get('/', [AudioFileController::class, 'index'])->name('index');
            Route::get('/create', [AudioFileController::class, 'create'])->name('create');
            Route::post('/', [AudioFileController::class, 'store'])->name('store');
            Route::post('/import', [AudioFileController::class, 'import'])->name('import');
            Route::patch('/{audioFile}', [AudioFileController::class, 'update'])->name('update');
            Route::delete('/{audioFile}', [AudioFileController::class, 'destroy'])->name('destroy');
            Route::delete('/', [AudioFileController::class, 'bulkDestroy'])->name('bulk-destroy');
        });

        // ===== PROJECT TASK ROUTES =====
        Route::prefix('{project}/tasks')->name('tasks.')->group(function () {
            Route::get('/manage', [ProjectTasksController::class, 'index'])->name('manage');
            Route::get('/export', [ProjectTasksController::class, 'export'])->name('export');



            Route::get('/', [TaskController::class, 'index'])->name('index');
            Route::get('/{task}', [TaskController::class, 'show'])->name('show');
            Route::patch('/{task}/assign', [TaskController::class, 'assign'])->name('assign');
            Route::patch('/{task}/unassign', [TaskController::class, 'unassign'])->name('unassign');
            Route::post('/bulk-assign', [TaskController::class, 'bulkAssign'])->name('bulk-assign');



        });

        // Add this inside the projects prefix group, after the task routes

        // ===== PROJECT BATCH ROUTES =====
        Route::prefix('{project}/batches')->name('batches.')->group(function () {
            // Main CRUD routes
            Route::get('/', [BatchController::class, 'index'])->name('index');
            Route::get('/create', [BatchController::class, 'create'])->name('create');
            Route::post('/', [BatchController::class, 'store'])->name('store');
            Route::get('/{batch}', [BatchController::class, 'show'])->name('show');
            Route::get('/{batch}/edit', [BatchController::class, 'edit'])->name('edit');
            Route::patch('/{batch}', [BatchController::class, 'update'])->name('update');
            Route::delete('/{batch}', [BatchController::class, 'destroy'])->name('destroy');

            // Batch status management
            Route::post('/{batch}/publish', [BatchController::class, 'publish'])->name('publish');
            Route::post('/{batch}/pause', [BatchController::class, 'pause'])->name('pause');
            Route::post('/{batch}/resume', [BatchController::class, 'resume'])->name('resume');

            // Task management within batches
            Route::post('/{batch}/add-tasks', [BatchController::class, 'addTasks'])->name('add-tasks');
            Route::delete('/{batch}/tasks/{task}', [BatchController::class, 'removeTask'])->name('remove-task');

            // Batch utilities
            Route::post('/{batch}/duplicate', [BatchController::class, 'duplicate'])->name('duplicate');
            Route::get('/{batch}/progress', [BatchController::class, 'progress'])->name('progress');
            Route::get('/statistics', [BatchController::class, 'statistics'])->name('statistics');

            // Bulk operations
            Route::post('/bulk-create', [BatchController::class, 'bulkCreate'])->name('bulk-create');
        });
    });
});
