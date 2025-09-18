<?php
// app/Http/Controllers/TaskClaimController.php

namespace App\Http\Controllers;

use App\Services\TaskService;
use Illuminate\Http\Request;
use DomainException;

class TaskClaimController extends Controller
{
    public function __construct(private TaskService $taskService) {}

    public function select(Request $request, int $taskId)
    {
        try {
            $task = $this->taskService->claimOrFail($taskId, $request->user());
            // Success → go to attempt/label page with project context
            return redirect()
                ->route('staff.attempt.show', [$task->project_id, $task->id])
                ->with('success', 'Task claimed successfully.');
        } catch (DomainException $e) {
            // Fail → go dashboard
            return redirect()->route('dashboard')->with('error', $e->getMessage());
        }
    }
}
