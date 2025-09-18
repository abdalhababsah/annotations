<?php
// app/Http/Middleware/EnsureNoActiveTask.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Task;
use App\Models\Review;

class EnsureNoActiveTask
{
    /**
     * Handle an incoming request.
     *
     * Applied to routes that would claim/select a new task.
     * If the user already has an active task (assigned/in_progress, not expired)
     * or an active review (not completed, not expired), redirect to that item.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Any active task already claimed by this user?
        $activeTask = Task::query()
            ->with(['project:id,name'])
            ->whereIn('status', ['assigned', 'in_progress'])
            ->where('assigned_to', $user->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('assigned_at')
            ->first();

        if ($activeTask) {
            return redirect()
                ->route('staff.attempt.show', [$activeTask->project_id, $activeTask->id])
                ->with('info', 'You already have an active task. Finish or release it before claiming another.');
        }

        // Any active review for this user?
        $activeReview = Review::query()
            ->with(['annotation.task.project'])
            ->where('reviewer_id', $user->id)
            ->whereNull('completed_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('started_at')
            ->first();

        if ($activeReview) {
            $projectId = optional(optional($activeReview->annotation)->task)->project_id;
            if ($projectId) {
                return redirect()
                    ->route('staff.review.show', [$projectId, $activeReview->id])
                    ->with('info', 'You already have an active review. Finish or release it before claiming another task.');
            }
        }

        return $next($request);
    }
}
