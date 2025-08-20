<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    $user = auth()->user();
    
    if ($user->isSystemAdmin()) {
        return redirect()->route('admin.dashboard');
    }
    
    if ($user->isProjectOwner()) {
        return redirect()->route('project_owner.dashboard');
    }
    
    // Default user dashboard
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/project_owner.php';
