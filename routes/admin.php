<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProjectController;
use Illuminate\Support\Facades\Route;

// Admin Routes
Route::middleware(['auth', 'role:SystemAdmin'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
});

// Project Routes - Accessible by System Admins and Project Owners
Route::middleware(['auth'])->group(function () {
    
    // Main Project Resource Routes
    Route::resource('admin/projects', ProjectController::class)->except(['destroy'])->names('admin.projects');
    
    // Custom Project Routes
    Route::prefix('admin/projects')->name('admin.projects.')->group(function () {
        
        // Project Statistics
        Route::get('{project}/statistics', [ProjectController::class, 'statistics'])
            ->name('statistics');
        
        // Project Status Management
        Route::patch('{project}/archive', [ProjectController::class, 'archive'])
            ->name('archive');
        Route::patch('{project}/restore', [ProjectController::class, 'restore'])
            ->name('restore');
        
        // Team Management Routes
        Route::prefix('{project}/members')->name('members.')->group(function () {
            Route::post('/', [ProjectController::class, 'addMember'])
                ->name('store');
            Route::patch('{member}', [ProjectController::class, 'updateMember'])
                ->name('update');
            Route::delete('{member}', [ProjectController::class, 'removeMember'])
                ->name('destroy');
        });
        
        // Soft Delete with Confirmation (separate from resource destroy)
        Route::delete('{project}/delete', [ProjectController::class, 'destroy'])
            ->name('destroy');
    });
});

// Alternative Route Structure (if you prefer nested admin routes)
/*
Route::middleware(['auth'])->group(function () {
    
    // Public Project Access (for all authenticated users)
    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::get('{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('{project}/statistics', [ProjectController::class, 'statistics'])->name('statistics');
    });
    
    // Project Management (System Admins and Project Owners only)
    Route::middleware(['can:manage-projects'])->group(function () {
        Route::prefix('projects')->name('projects.')->group(function () {
            
            // CRUD Operations
            Route::get('create', [ProjectController::class, 'create'])->name('create');
            Route::post('/', [ProjectController::class, 'store'])->name('store');
            Route::get('{project}/edit', [ProjectController::class, 'edit'])->name('edit');
            Route::put('{project}', [ProjectController::class, 'update'])->name('update');
            Route::delete('{project}', [ProjectController::class, 'destroy'])->name('destroy');
            
            // Status Management
            Route::patch('{project}/archive', [ProjectController::class, 'archive'])->name('archive');
            Route::patch('{project}/restore', [ProjectController::class, 'restore'])->name('restore');
            
            // Team Management
            Route::post('{project}/members', [ProjectController::class, 'addMember'])->name('members.store');
            Route::patch('{project}/members/{member}', [ProjectController::class, 'updateMember'])->name('members.update');
            Route::delete('{project}/members/{member}', [ProjectController::class, 'removeMember'])->name('members.destroy');
        });
    });
});
*/

// Route Names Reference:
/*
Available Routes:

Main Project Routes:
- projects.index         GET     /projects                      - List projects
- projects.create        GET     /projects/create               - Show create form
- projects.store         POST    /projects                      - Store new project
- projects.show          GET     /projects/{project}            - Show project details
- projects.edit          GET     /projects/{project}/edit       - Show edit form
- projects.update        PUT     /projects/{project}            - Update project

Custom Project Routes:
- projects.statistics    GET     /projects/{project}/statistics - Project statistics
- projects.archive       PATCH   /projects/{project}/archive    - Archive project
- projects.restore       PATCH   /projects/{project}/restore    - Restore project
- projects.destroy       DELETE  /projects/{project}/delete     - Delete project

Team Management Routes:
- projects.members.store   POST   /projects/{project}/members           - Add team member
- projects.members.update  PATCH  /projects/{project}/members/{member}  - Update member
- projects.members.destroy DELETE /projects/{project}/members/{member}  - Remove member

Usage in Blade/Vue:
- route('projects.index')
- route('projects.show', $project->id)
- route('projects.edit', $project)
- route('projects.members.store', $project->id)
- route('projects.members.update', [$project->id, $member->id])
*/