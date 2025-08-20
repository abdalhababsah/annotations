<?php

use App\Http\Controllers\ProjectOwner\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:ProjectOwner'])->group(function () {
    Route::get('/projects/dashboard', [DashboardController::class, 'index'])->name('project_owner.dashboard');
});
