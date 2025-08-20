<?php

namespace App\Http\Controllers\ProjectOwner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Add any project owner specific data
        $projects = auth()->user()->ownedProjects()->with('projectMemberships')->get();
        
        return Inertia::render('ProjectOwner/Dashboard', [
            'projects' => $projects
        ]);
    }
}
