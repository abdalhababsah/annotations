<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectMembersController;
use App\Http\Controllers\Admin\TaskController;
use Illuminate\Support\Facades\Route;

// Admin Routes - System Admin Only
Route::middleware(['auth', 'role:SystemAdmin'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
});

// Project Routes - Accessible by System Admins and Project Owners
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

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

        // ===== PROJECT TASK ROUTES =====
        Route::prefix('{project}/tasks')->name('tasks.')->group(function () {
            Route::get('/', [TaskController::class, 'index'])->name('index');
            Route::get('/{task}', [TaskController::class, 'show'])->name('show');
            Route::patch('/{task}/assign', [TaskController::class, 'assign'])->name('assign');
            Route::patch('/{task}/unassign', [TaskController::class, 'unassign'])->name('unassign');
            Route::post('/bulk-assign', [TaskController::class, 'bulkAssign'])->name('bulk-assign');
        });
    });
});

// =====================================================
// ROUTE SUMMARY AND DOCUMENTATION
// =====================================================

/*
COMPLETE PROJECT ROUTES LIST:

## MAIN PROJECT MANAGEMENT
GET    /admin/projects                           → index (List all projects)
GET    /admin/projects/create                    → create (Show create form - Step 1)
GET    /admin/projects/{project}                 → show (Show project details)
GET    /admin/projects/{project}/edit            → edit (Edit project form)
PATCH  /admin/projects/{project}                 → update (Update project)
DELETE /admin/projects/{project}                 → destroy (Delete project)

## PROJECT CREATION WIZARD (3-Step Process)
POST   /admin/projects/store-step-one            → storeStepOne (Save basic info)
GET    /admin/projects/{project}/step-two        → createStepTwo (Configure dimensions)
POST   /admin/projects/{project}/store-step-two  → storeStepTwo (Save dimensions)
GET    /admin/projects/{project}/step-three      → createStepThree (Review & finalize)
POST   /admin/projects/{project}/finalize        → finalizeProject (Activate project)

## PROJECT STATUS MANAGEMENT
POST   /admin/projects/{project}/quick-activate  → quickActivate (Quick activation for ready projects)
PATCH  /admin/projects/{project}/status          → updateStatus (Update project status)
POST   /admin/projects/{project}/archive         → archive (Archive project)
POST   /admin/projects/{project}/restore         → restore (Restore from archive)

## PROJECT UTILITIES
POST   /admin/projects/{project}/duplicate       → duplicate (Duplicate project structure)
GET    /admin/projects/{project}/setup-status    → getSetupStatus (API: Check setup status)

## TEAM MANAGEMENT
GET    /admin/projects/{project}/available-users → getAvailableUsers (API: Get available users)
POST   /admin/projects/{project}/members         → addMember (Add team member)
DELETE /admin/projects/{project}/members/{member} → removeMember (Remove team member)

## TASK MANAGEMENT (Within Projects)
GET    /admin/projects/{project}/tasks           → TaskController@index (List project tasks)
GET    /admin/projects/{project}/tasks/{task}    → TaskController@show (Show task details)
PATCH  /admin/projects/{project}/tasks/{task}/assign → TaskController@assign (Assign task)
PATCH  /admin/projects/{project}/tasks/{task}/unassign → TaskController@unassign (Unassign task)
POST   /admin/projects/{project}/tasks/bulk-assign → TaskController@bulkAssign (Bulk assign tasks)

## ROUTE NAMING CONVENTION
All routes are prefixed with 'admin.projects.' for consistency:
- admin.projects.index
- admin.projects.create
- admin.projects.store-step-one
- admin.projects.create.step-two
- admin.projects.store-step-two
- admin.projects.create.step-three
- admin.projects.finalize
- admin.projects.show
- admin.projects.edit
- admin.projects.update
- admin.projects.destroy
- admin.projects.quick-activate
- admin.projects.update-status
- admin.projects.archive
- admin.projects.restore
- admin.projects.duplicate
- admin.projects.setup-status
- admin.projects.available-users
- admin.projects.add-member
- admin.projects.remove-member
- admin.projects.tasks.index
- admin.projects.tasks.show
- admin.projects.tasks.assign
- admin.projects.tasks.unassign
- admin.projects.tasks.bulk-assign

## MIDDLEWARE
- 'auth' - All routes require authentication
- Individual permission checking is handled within controllers using helper methods:
  - canEditProject()
  - canDeleteProject()
  - canManageTeam()
  - canViewProject()

## PROJECT CREATION FLOW
1. GET  /admin/projects/create (Step 1: Basic Info Form)
2. POST /admin/projects/store-step-one (Save & redirect to Step 2)
3. GET  /admin/projects/{project}/step-two (Step 2: Dimensions Form)
4. POST /admin/projects/{project}/store-step-two (Save & redirect to Step 3)
5. GET  /admin/projects/{project}/step-three (Step 3: Review Form)
6. POST /admin/projects/{project}/finalize (Activate & redirect to project)

## PROJECT STATUS WORKFLOW
- draft → active (via finalize or quick-activate)
- active → paused/completed/archived (via update-status)
- archived → active (via restore, requires dimensions)
- Any status can go to archived (via archive)

## SETUP VALIDATION
- Projects without dimensions cannot be activated
- All activation routes validate dimension existence
- Setup status can be checked via setup-status API endpoint
*/