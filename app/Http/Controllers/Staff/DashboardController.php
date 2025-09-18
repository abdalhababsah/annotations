<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\Project;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\TaskService;

class DashboardController extends Controller
{
    public function __construct(private TaskService $tasks) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Active projects where the user is an active member - include the missing fields
        $projects = Project::query()
            ->select([
                'id', 'name', 'status', 'project_type', 'description', 
                'task_time_minutes', 'review_time_minutes', 'annotation_guidelines'
            ])
            ->with([
                'members' => fn ($q) => $q->where('user_id', $user->id)->where('is_active', true),
                'batches:id,project_id,status,total_tasks,completed_tasks',
            ])
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('is_active', true))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $cards = $projects->map(function (Project $project) use ($user) {
            $membership = $project->members->first();
            $role = $membership?->role;
            $isAnnotator = $role === 'annotator';
            $isReviewer  = $role === 'reviewer';

            $hasActiveBatches = $project->batches->contains(
                fn ($b) => in_array($b->status, ['published', 'in_progress'], true)
            );

            // CONTINUE buttons (per-project)
            $continueAttempt = $isAnnotator
                ? $this->tasks->firstActiveTaskForProject($project->id, $user->id)
                : null;

            $continueReview = $isReviewer
                ? $this->tasks->firstActiveReviewForProject($project->id, $user->id)
                : null;

            // Availability (new/next)
            $resumeAttemptExists = !!$continueAttempt;

            // Skips per user
            $skippedIds = \App\Models\SkipActivity::query()
                ->where('user_id', $user->id)
                ->where('project_id', $project->id)
                ->where('activity_type', 'task')
                ->pluck('task_id');

            $pendingAvailableExists = $project->tasks()
                ->whereHas('batch', fn ($q) => $q->whereIn('status', ['published', 'in_progress']))
                ->where('status', 'pending')
                ->whereNotIn('id', $skippedIds)
                ->exists();

            $canAttempt = $isAnnotator && $hasActiveBatches && ($resumeAttemptExists || $pendingAvailableExists);

            // Reviews
            $resumeReviewExists = !!$continueReview;

            $submittedExists = Annotation::query()
                ->where('status', 'submitted')
                ->whereHas('task', function ($q) use ($project) {
                    $q->where('project_id', $project->id)
                      ->whereHas('batch', fn ($b) => $b->whereIn('status', ['published', 'in_progress']));
                })
                ->exists();

            $canReview = $isReviewer && $hasActiveBatches && ($resumeReviewExists || $submittedExists);

            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'project_type' => $project->project_type,
                'task_time_minutes' => $project->task_time_minutes,
                'review_time_minutes' => $project->review_time_minutes,
                'annotation_guidelines' => $project->annotation_guidelines,
                'roles' => [
                    'annotator' => $isAnnotator,
                    'reviewer'  => $isReviewer,
                ],
                'has_active_batches' => $hasActiveBatches,
                'can_attempt' => $canAttempt,
                'can_review'  => $canReview,

                // Continue info for UI to render a "Continue" state
                'continue' => [
                    'attempt' => $continueAttempt ? [
                        'task_id'   => $continueAttempt->id,
                        'project_id'=> $project->id,
                        'route'     => route('staff.attempt.show', [$project->id, $continueAttempt->id], false),
                    ] : null,
                    'review' => $continueReview ? [
                        'review_id' => $continueReview->id,
                        'project_id'=> $project->id,
                        'route'     => route('staff.review.show', [$project->id, $continueReview->id], false),
                    ] : null,
                ],
            ];
        })->values();

        // Aggregate stats
        $startDay   = now()->startOfDay();
        $startWeek  = now()->startOfWeek();
        $startMonth = now()->startOfMonth();

        $annotatorProjectIds = $projects
            ->filter(fn ($p) => ($p->members->first()?->role) === 'annotator')
            ->pluck('id');

        $reviewerProjectIds = $projects
            ->filter(fn ($p) => ($p->members->first()?->role) === 'reviewer')
            ->pluck('id');

        $annotatorStats = null;
        if ($annotatorProjectIds->isNotEmpty()) {
            $baseAnn = Annotation::query()
                ->where('annotator_id', $user->id)
                ->whereHas('task', fn ($q) => $q->whereIn('project_id', $annotatorProjectIds));

            $todayAttempted   = (clone $baseAnn)->where('started_at', '>=', $startDay)->count();
            $todaySubmitted   = (clone $baseAnn)->where('submitted_at', '>=', $startDay)->count();
            $todayTimeSeconds = (int) (clone $baseAnn)->where('submitted_at', '>=', $startDay)->sum('total_time_spent');

            $weekAttempted    = (clone $baseAnn)->where('started_at', '>=', $startWeek)->count();
            $monthAttempted   = (clone $baseAnn)->where('started_at', '>=', $startMonth)->count();
            $allSubmitted     = (clone $baseAnn)->whereNotNull('submitted_at')->count();

            $avgTimeToday     = $todaySubmitted > 0 ? intdiv($todayTimeSeconds, $todaySubmitted) : 0;

            $annotatorStats = [
                'today_attempted'   => $todayAttempted,
                'today_submitted'   => $todaySubmitted,
                'today_time_seconds'=> $todayTimeSeconds,
                'today_avg_seconds' => $avgTimeToday,
                'week_attempted'    => $weekAttempted,
                'month_attempted'   => $monthAttempted,
                'all_time_submitted'=> $allSubmitted,
            ];
        }

        $reviewerStats = null;
        if ($reviewerProjectIds->isNotEmpty()) {
            $baseRev = Review::query()
                ->where('reviewer_id', $user->id)
                ->whereHas('annotation.task', fn ($q) => $q->whereIn('project_id', $reviewerProjectIds));

            $todayStarted       = (clone $baseRev)->where('started_at', '>=', $startDay)->count();
            $todayApproved      = (clone $baseRev)->where('action', 'approved')->where('completed_at', '>=', $startDay)->count();
            $todayReviewSeconds = (int) (clone $baseRev)->where('action', 'approved')->where('completed_at', '>=', $startDay)->sum('review_time_spent');

            $weekApproved       = (clone $baseRev)->where('action', 'approved')->where('completed_at', '>=', $startWeek)->count();
            $monthApproved      = (clone $baseRev)->where('action', 'approved')->where('completed_at', '>=', $startMonth)->count();
            $allApproved        = (clone $baseRev)->where('action', 'approved')->whereNotNull('completed_at')->count();

            $avgReviewToday     = $todayApproved > 0 ? intdiv($todayReviewSeconds, $todayApproved) : 0;

            $reviewerStats = [
                'today_started'        => $todayStarted,
                'today_approved'       => $todayApproved,
                'today_time_seconds'   => $todayReviewSeconds,
                'today_avg_seconds'    => $avgReviewToday,
                'week_approved'        => $weekApproved,
                'month_approved'       => $monthApproved,
                'all_time_approved'    => $allApproved,
            ];
        }

        return Inertia::render('Staff/Dashboard', [
            'projects' => $cards,
            'stats'    => [
                'has_annotator' => $annotatorProjectIds->isNotEmpty(),
                'has_reviewer'  => $reviewerProjectIds->isNotEmpty(),
                'annotator'     => $annotatorStats,
                'reviewer'      => $reviewerStats,
            ],
        ]);
    }
}