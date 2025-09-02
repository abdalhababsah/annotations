<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\Project;
use App\Models\Review;
use App\Models\SkipActivity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Active projects where the user is an active member
        $projects = Project::query()
            ->with([
                'members' => fn($q) => $q->where('user_id', $user->id)->where('is_active', true),
                'batches:id,project_id,status,total_tasks,completed_tasks',
            ])
            ->whereHas('members', fn($q) => $q->where('user_id', $user->id)->where('is_active', true))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        // Build project cards (full-width; no queue counts)
        $cards = $projects->map(function (Project $project) use ($user) {
            $membership = $project->members->first();
            $role = $membership?->role;

            $isAnnotator = $role === 'annotator';
            $isReviewer  = $role === 'reviewer';

            $hasActiveBatches = $project->batches->contains(
                fn ($b) => in_array($b->status, ['published', 'in_progress'], true)
            );

            // ---------- Availability (no counts, just booleans) ----------

            // Attempt availability:
            // - resume if user already has assigned/in_progress (not expired) in active batches
            $resumeAttemptExists = $project->tasks()
                ->whereHas('batch', fn($q) => $q->whereIn('status', ['published', 'in_progress']))
                ->whereIn('status', ['assigned', 'in_progress'])
                ->where('assigned_to', $user->id)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();

            // - or at least one pending task the user hasn't skipped, in active batches
            $skippedTaskIds = SkipActivity::query()
                ->where('user_id', $user->id)
                ->where('project_id', $project->id)
                ->where('activity_type', 'task')
                ->pluck('task_id');

            $pendingAvailableExists = $project->tasks()
                ->whereHas('batch', fn($q) => $q->whereIn('status', ['published', 'in_progress']))
                ->where('status', 'pending')
                ->whereNotIn('id', $skippedTaskIds)
                ->exists();

            $canAttempt = $hasActiveBatches && ($resumeAttemptExists || $pendingAvailableExists);

            // Review availability:
            // - resume if user has an active review (not expired)
            $resumeReviewExists = Review::query()
                ->whereHas('annotation.task', fn($q) => $q->where('project_id', $project->id))
                ->where('reviewer_id', $user->id)
                ->whereNull('completed_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();

            // - or at least one submitted annotation from active batches
            $submittedExists = Annotation::query()
                ->where('status', 'submitted')
                ->whereHas('task', function ($q) use ($project) {
                    $q->where('project_id', $project->id)
                      ->whereHas('batch', fn($b) => $b->whereIn('status', ['published', 'in_progress']));
                })
                ->exists();

            $canReview = $hasActiveBatches && ($resumeReviewExists || $submittedExists);

            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'roles' => [
                    'annotator' => $isAnnotator,
                    'reviewer'  => $isReviewer,
                ],
                'has_active_batches' => $hasActiveBatches,
                'can_attempt' => $isAnnotator ? $canAttempt : false,
                'can_review'  => $isReviewer  ? $canReview  : false,
            ];
        })->values();

        // -------------------- Aggregate Stats (cross-project) --------------------
        $startDay  = now()->startOfDay();
        $startWeek = now()->startOfWeek();
        $startMonth= now()->startOfMonth();

        $annotatorProjectIds = $projects
            ->filter(fn ($p) => ($p->members->first()?->role) === 'annotator')
            ->pluck('id');

        $reviewerProjectIds = $projects
            ->filter(fn ($p) => ($p->members->first()?->role) === 'reviewer')
            ->pluck('id');

        // Annotator stats
        $annotatorStats = null;
        if ($annotatorProjectIds->isNotEmpty()) {
            $baseAnn = Annotation::query()
                ->where('annotator_id', $user->id)
                ->whereHas('task', fn($q) => $q->whereIn('project_id', $annotatorProjectIds));

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

        // Reviewer stats (approve-only path)
        $reviewerStats = null;
        if ($reviewerProjectIds->isNotEmpty()) {
            $baseRev = Review::query()
                ->where('reviewer_id', $user->id)
                ->whereHas('annotation.task', fn($q) => $q->whereIn('project_id', $reviewerProjectIds));

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
