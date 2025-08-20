<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Add any data you want to pass to the admin dashboard
        return Inertia::render('Admin/Dashboard');
    }
}
