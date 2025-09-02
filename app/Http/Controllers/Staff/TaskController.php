<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\AnnotationDimension;
use App\Models\AnnotationValue;
use App\Models\Project;
use App\Models\Review;
use App\Models\SkipActivity;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /* =========================================================
     * Helpers
     * ========================================================= */
    protected function userMembershipOrAbort(Project $project, int $userId)
    {
        $member = $project->members()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        abort_unless($member, 403, 'You are not a member of this project.');
        return $member;
    }

    protected function ensureActiveBatches(Project $project): bool
    {
        return $project->batches()
            ->whereIn('status', ['published', 'in_progress'])
            ->exists();
    }

    /* =========================================================
     * Attempt (Annotator)
     * ========================================================= */
    public function nextAttempt(Request $request, Project $project)
    {
        $user = $request->user();
        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['annotator', 'project_admin']), 403, 'Not allowed.');

        // 1) Resume if assigned/in_progress and not expired
        $resume = Task::query()
            ->where('project_id', $project->id)
            ->whereHas('batch', fn($q) => $q->whereIn('status', ['published','in_progress']))
            ->whereIn('status', ['assigned','in_progress'])
            ->where('assigned_to', $user->id)
            ->where(function($q){ $q->whereNull('expires_at')->orWhere('expires_at','>', now()); })
            ->orderBy('assigned_at')
            ->first();

        if ($resume) {
            return redirect()->route('staff.attempt.show', [$project->id, $resume->id]);
        }

        // 2) Otherwise, pick next pending (excluding skipped)
        if (!$this->ensureActiveBatches($project)) {
            return back()->with('warning', 'No published or in-progress batches.');
        }

        $skipped = SkipActivity::getSkippedTasksForUser($user->id, $project->id);

        $next = Task::query()
            ->where('project_id', $project->id)
            ->whereHas('batch', fn($q) => $q->whereIn('status', ['published','in_progress']))
            ->where('status', 'pending')
            ->whereNotIn('id', $skipped)
            ->orderBy('id')
            ->first();

        if (!$next) {
            return back()->with('info', 'No available tasks right now.');
        }

        // Assignment happens when the user actually opens the task page (showAttempt)
        return redirect()->route('staff.attempt.show', [$project->id, $next->id]);
    }

    public function showAttempt(Request $request, Project $project, Task $task): Response
    {
        $user = $request->user();
        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['annotator', 'project_admin']), 403, 'Not allowed.');
        abort_unless($task->project_id === $project->id, 404);

        // Only from active batches
        abort_unless($task->batch && in_array($task->batch->status, ['published','in_progress']), 403, 'Task batch is not active.');

        // If expired, reset and send back to next
        if ($task->isExpired()) {
            $task->handleExpiration();
            return redirect()->route('staff.attempt.next', $project->id)
                ->with('warning', 'Task expired and returned to queue.');
        }

        // JIT assign if pending (if already assigned to another user, block)
        if ($task->status === 'pending') {
            DB::transaction(function () use ($task, $user, $project) {
                $locked = Task::where('id', $task->id)->lockForUpdate()->first();
                if ($locked->status !== 'pending') {
                    abort(409, 'Task just changed state.');
                }
                $locked->update([
                    'status' => 'assigned',
                    'assigned_to' => $user->id,
                    'assigned_at' => now(),
                    'expires_at' => now()->addMinutes($project->task_time_minutes ?? 30),
                ]);
                // If batch was published, move to in_progress
                if ($locked->batch && $locked->batch->status === 'published') {
                    $locked->batch->update(['status' => 'in_progress']);
                }
            });
            $task->refresh();
        } elseif ($task->assigned_to && $task->assigned_to !== $user->id) {
            abort(403, 'Task assigned to another user.');
        }

        // Load dimensions + values
        $dimensions = AnnotationDimension::where('project_id', $project->id)
            ->with(['dimensionValues' => fn($q) => $q->orderBy('display_order')])
            ->orderBy('display_order')
            ->get();

        // Load (latest) annotation draft by this user for this task
        $annotation = $task->annotations()
            ->where('annotator_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $annotationValues = $annotation
            ? $annotation->annotationValues()->get(['dimension_id','selected_value','numeric_value','notes'])
            : collect();

        return Inertia::render('Staff/AttemptTask', [
            'project'     => [
                'id' => $project->id,
                'name' => $project->name,
                'task_time_minutes' => $project->task_time_minutes,
            ],
            'task'        => [
                'id' => $task->id,
                'status' => $task->status,
                'expires_at' => optional($task->expires_at)?->toIso8601String(),
                'audio' => [
                    'id' => $task->audioFile?->id,
                    'filename' => $task->audioFile?->original_filename,
                    'url' => $task->audioFile?->url,
                    'duration' => $task->audioFile?->duration,
                ],
            ],
            'dimensions'  => $dimensions->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'description' => $d->description,
                'dimension_type' => $d->dimension_type,
                'scale_min' => $d->scale_min,
                'scale_max' => $d->scale_max,
                'is_required' => (bool)$d->is_required,
                'values' => $d->dimensionValues->map(fn($v)=>['value'=>$v->value,'label'=>$v->label ?: $v->value]),
            ]),
            'draft'       => [
                'annotation_id' => $annotation?->id,
                'values' => $annotationValues,
            ],
        ]);
    }

    public function saveAttemptDraft(Request $request, Project $project, Task $task)
    {
        $user = $request->user();
        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['annotator', 'project_admin']), 403, 'Not allowed.');
        abort_unless($task->project_id === $project->id, 404);
        abort_unless($task->assigned_to === $user->id, 403, 'Not your task.');

        if ($task->isExpired()) {
            $task->handleExpiration();
            return back()->with('warning', 'Task expired. Draft not saved.');
        }

        $payload = $request->validate([
            'values' => 'required|array', // [{dimension_id, selected_value?, numeric_value?, notes?}]
            'spent_seconds' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($task, $user, $payload) {
            $annotation = Annotation::firstOrCreate(
                ['task_id' => $task->id, 'annotator_id' => $user->id],
                ['status' => 'draft', 'started_at' => now()]
            );

            // Keep status draft if not yet submitted
            if ($annotation->status !== 'submitted') {
                $annotation->update([
                    'status' => 'draft',
                    'total_time_spent' => $payload['spent_seconds'] ?? $annotation->total_time_spent,
                ]);
            }

            // Upsert values
            $byDim = collect($payload['values'])->keyBy('dimension_id');
            $dimensionIds = $byDim->keys()->all();

            // delete values for removed dimensions (safety)
            AnnotationValue::where('annotation_id', $annotation->id)
                ->whereNotIn('dimension_id', $dimensionIds)->delete();

            foreach ($byDim as $dimensionId => $v) {
                AnnotationValue::updateOrCreate(
                    ['annotation_id' => $annotation->id, 'dimension_id' => $dimensionId],
                    [
                        'selected_value' => $v['selected_value'] ?? null,
                        'numeric_value'  => $v['numeric_value'] ?? null,
                        'notes'          => $v['notes'] ?? null,
                    ]
                );
            }
        });

        return back()->with('success', 'Draft saved.');
    }

    public function submitAttempt(Request $request, Project $project, Task $task)
    {
        $user = $request->user();
        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['annotator', 'project_admin']), 403, 'Not allowed.');
        abort_unless($task->project_id === $project->id, 404);
        if ($task->assigned_to !== $user->id) {
            return back()->with('error', 'This task is not assigned to you.');
        }

        if ($task->isExpired()) {
            $task->handleExpiration();
            return redirect()->route('staff.attempt.next', $project->id)
                ->with('warning', 'Task expired and returned to queue.');
        }

        $payload = $request->validate([
            'values' => 'required|array',
            'spent_seconds' => 'nullable|integer|min:0',
        ]);
        $spent = (int)($payload['spent_seconds'] ?? 0);

        DB::transaction(function () use ($task, $user, $payload, $spent) {
            $annotation = Annotation::firstOrCreate(
                ['task_id' => $task->id, 'annotator_id' => $user->id],
                ['status' => 'draft', 'started_at' => now()]
            );

            // Save values
            $byDim = collect($payload['values'])->keyBy('dimension_id');
            $dimensionIds = $byDim->keys()->all();

            AnnotationValue::where('annotation_id', $annotation->id)
                ->whereNotIn('dimension_id', $dimensionIds)->delete();

            foreach ($byDim as $dimensionId => $v) {
                AnnotationValue::updateOrCreate(
                    ['annotation_id' => $annotation->id, 'dimension_id' => $dimensionId],
                    [
                        'selected_value' => $v['selected_value'] ?? null,
                        'numeric_value'  => $v['numeric_value'] ?? null,
                        'notes'          => $v['notes'] ?? null,
                    ]
                );
            }

            // Submit annotation
            $annotation->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'total_time_spent' => $spent ?: $annotation->total_time_spent,
            ]);

            // Move task into under_review
            $task->update([
                'status' => 'under_review',
                'started_at' => $task->started_at ?: now(),
                'completed_at' => now(),
            ]);
        });

        // Success page
        return redirect()->route('staff.flow.success', [
            'project' => $project->id,
            'kind'    => 'attempt_submitted',
            'seconds' => $spent,
        ])->with('success', 'Submission sent for review.');
    }

    public function skipAttempt(Request $request, Project $project, Task $task)
    {
        $user = $request->user();
        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['annotator', 'project_admin']), 403, 'Not allowed.');
        abort_unless($task->project_id === $project->id, 404);

        $payload = $request->validate([
            'reason' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($task, $user, $payload) {
            $task->skipByUser($user, $payload['reason'] ?? 'skip', $payload['description'] ?? null);
        });

        return redirect()->route('staff.flow.success', [
            'project' => $project->id,
            'kind'    => 'attempt_skipped',
            'seconds' => 0,
        ])->with('info', 'Task skipped.');
    }

    /* =========================================================
     * Review (Reviewer) - Approve only (no reject)
     * ========================================================= */
    public function nextReview(Request $request, Project $project)
    {
        $user = $request->user();
        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['reviewer', 'project_admin']), 403, 'Not allowed.');

        // Resume active review if exists
        $active = Review::query()
            ->whereHas('annotation.task', fn($q) => $q->where('project_id', $project->id))
            ->where('reviewer_id', $user->id)
            ->whereNull('completed_at')
            ->where(function($q){ $q->whereNull('expires_at')->orWhere('expires_at','>', now()); })
            ->orderBy('started_at')
            ->first();

        if ($active) {
            return redirect()->route('staff.review.show', [$project->id, $active->id]);
        }

        if (!$this->ensureActiveBatches($project)) {
            return back()->with('warning', 'No published or in-progress batches.');
        }

        // Next submitted annotation
        $annotation = $project->getNextReviewForUser($user->id);
        if (!$annotation) {
            return back()->with('info', 'No items ready for review.');
        }

        // Assign review now
        $review = $project->assignReviewToUser($annotation->id, $user->id);
        if (!$review) {
            return back()->with('error', 'Could not assign review.');
        }

        return redirect()->route('staff.review.show', [$project->id, $review->id]);
    }

    public function showReview(Request $request, Project $project, Review $review): Response
    {
        $user = $request->user();
        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['reviewer', 'project_admin']), 403, 'Not allowed.');
        abort_unless($review->annotation->task->project_id === $project->id, 404);
        abort_unless($review->reviewer_id === $user->id, 403, 'Not your review.');

        if ($review->isExpired()) {
            $review->handleExpiration();
            return redirect()->route('staff.review.next', $project->id)
                ->with('warning', 'Review expired and returned to queue.');
        }

        $annotation = $review->annotation()->with([
            'annotationValues.dimension.dimensionValues',
            'task.audioFile',
        ])->first();

        $dimensions = AnnotationDimension::where('project_id', $project->id)
            ->with(['dimensionValues' => fn($q) => $q->orderBy('display_order')])
            ->orderBy('display_order')
            ->get();

        // Map current values by dimension
        $values = $annotation->annotationValues->map(function ($v) {
            return [
                'dimension_id' => $v->dimension_id,
                'selected_value' => $v->selected_value,
                'numeric_value' => $v->numeric_value,
                'notes' => $v->notes,
            ];
        });

        return Inertia::render('Staff/ReviewTask', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'review_time_minutes' => $project->review_time_minutes,
            ],
            'review' => [
                'id' => $review->id,
                'expires_at' => optional($review->expires_at)?->toIso8601String(),
                'feedback_rating' => $review->feedback_rating,
                'feedback_comment' => $review->feedback_comment,
            ],
            'annotation' => [
                'id' => $annotation->id,
                'task_id' => $annotation->task_id,
                'values' => $values,
                'audio' => [
                    'id' => $annotation->task->audioFile?->id,
                    'filename' => $annotation->task->audioFile?->original_filename,
                    'url' => $annotation->task->audioFile?->url,
                    'duration' => $annotation->task->audioFile?->duration,
                ],
            ],
            'dimensions' => $dimensions->map(fn($d)=>[
                'id' => $d->id,
                'name' => $d->name,
                'description' => $d->description,
                'dimension_type' => $d->dimension_type,
                'scale_min' => $d->scale_min,
                'scale_max' => $d->scale_max,
                'is_required' => (bool)$d->is_required,
                'values' => $d->dimensionValues->map(fn($v)=>['value'=>$v->value,'label'=>$v->label ?: $v->value]),
            ]),
        ]);
    }

    public function saveReviewDraft(Request $request, Project $project, Review $review)
    {
        $user = $request->user();
        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['reviewer', 'project_admin']), 403, 'Not allowed.');
        abort_unless($review->annotation->task->project_id === $project->id, 404);
        abort_unless($review->reviewer_id === $user->id, 403, 'Not your review.');

        if ($review->isExpired()) {
            $review->handleExpiration();
            return back()->with('warning', 'Review expired. Draft not saved.');
        }

        $payload = $request->validate([
            'feedback_rating' => 'nullable|integer|min:1|max:5',
            'feedback_comment' => 'nullable|string|max:2000',
            'spent_seconds' => 'nullable|integer|min:0',
        ]);

        $review->update([
            'feedback_rating' => $payload['feedback_rating'] ?? $review->feedback_rating,
            'feedback_comment' => $payload['feedback_comment'] ?? $review->feedback_comment,
            'review_time_spent' => $payload['spent_seconds'] ?? $review->review_time_spent,
        ]);

        return back()->with('success', 'Feedback saved.');
    }

// in App\Http\Controllers\Staff\TaskController.php

public function approveReview(Request $request, Project $project, Review $review)
{
    $user = $request->user();
    $membership = $this->userMembershipOrAbort($project, $user->id);
    abort_unless(in_array($membership->role, ['reviewer', 'project_admin']), 403, 'Not allowed.');
    abort_unless($review->annotation->task->project_id === $project->id, 404);
    if ($review->reviewer_id !== $user->id) {
        return redirect()->route('staff.review.next', $project->id)
            ->with('error', 'This review is not assigned to you.');
    }

    if ($review->isExpired()) {
        $review->handleExpiration();
        return redirect()->route('staff.review.next', $project->id)
            ->with('warning', 'Review expired.');
    }

    // Validate feedback and optional changes (only categorical/numeric fields will be validated)
    $payload = $request->validate([
        'feedback_rating'  => 'nullable|integer|min:1|max:5',
        'feedback_comment' => 'nullable|string|max:2000',
        'spent_seconds'    => 'nullable|integer|min:0',

        'changes'                  => 'nullable|array',
        'changes.*.dimension_id'   => 'required|integer|exists:annotation_dimensions,id',
        'changes.*.selected_value' => 'nullable|string|max:255', // categorical
        'changes.*.numeric_value'  => 'nullable|integer',        // numeric_scale
        'changes.*.change_reason'  => 'nullable|string|max:2000',
    ]);

    $spent = (int)($payload['spent_seconds'] ?? 0);

    DB::transaction(function () use ($review, $payload) {
        $annotation = $review->annotation()->with(['annotationValues'])->first();

        // Index current values by dimension
        $currentValues = $annotation->annotationValues->keyBy('dimension_id');

        // Pull all project dimensions to know their types & possible values
        $dimensions = AnnotationDimension::whereHas('project', function ($q) use ($annotation) {
                $q->where('id', $annotation->task->project_id);
            })
            ->get()
            ->keyBy('id');

        // Apply corrections (if any)
        foreach (($payload['changes'] ?? []) as $chg) {
            $dimId   = (int) $chg['dimension_id'];
            $dim     = $dimensions->get($dimId);
            if (!$dim) continue;

            // Original row (may be null if annotator skipped that dim)
            $row = $currentValues->get($dimId);

            $origVal = $row?->selected_value;
            $origNum = $row?->numeric_value;

            $corrVal = array_key_exists('selected_value', $chg) ? $chg['selected_value'] : $origVal;
            $corrNum = array_key_exists('numeric_value',  $chg) ? $chg['numeric_value']  : $origNum;

            $changed = false;

            if ($dim->dimension_type === 'categorical') {
                $changed = $origVal !== $corrVal;
                if ($changed) {
                    // upsert annotation value row
                    AnnotationValue::updateOrCreate(
                        ['annotation_id' => $annotation->id, 'dimension_id' => $dimId],
                        ['selected_value' => $corrVal, 'numeric_value' => null]
                    );

                    // log review change
                    $review->reviewChanges()->create([
                        'dimension_id'     => $dimId,
                        'original_value'   => $origVal,
                        'corrected_value'  => $corrVal,
                        'original_numeric' => null,
                        'corrected_numeric'=> null,
                        'change_reason'    => $chg['change_reason'] ?? null,
                    ]);
                }
            } elseif ($dim->dimension_type === 'numeric_scale') {
                $changed = (int)$origNum !== (int)$corrNum;
                if ($changed) {
                    AnnotationValue::updateOrCreate(
                        ['annotation_id' => $annotation->id, 'dimension_id' => $dimId],
                        ['selected_value' => null, 'numeric_value' => $corrNum]
                    );

                    $review->reviewChanges()->create([
                        'dimension_id'     => $dimId,
                        'original_value'   => null,
                        'corrected_value'  => null,
                        'original_numeric' => $origNum,
                        'corrected_numeric'=> $corrNum,
                        'change_reason'    => $chg['change_reason'] ?? null,
                    ]);
                }
            }
        }

        // Finalize review
        $review->update([
            'action'            => 'approved',
            'feedback_rating'   => $payload['feedback_rating'] ?? $review->feedback_rating,
            'feedback_comment'  => $payload['feedback_comment'] ?? $review->feedback_comment,
            'review_time_spent' => $payload['spent_seconds'] ?? $review->review_time_spent,
            'completed_at'      => now(),
        ]);

        // Approve the annotation + task
        $annotation->update(['status' => 'approved']);
        $annotation->task()->update(['status' => 'approved']);
    });

    return redirect()->route('staff.flow.success', [
        'project' => $project->id,
        'kind'    => 'review_approved',
        'seconds' => $spent,
    ])->with('success', 'Review approved.');
}


public function skipReview(Request $request, Project $project, Review $review)
{
    $user = $request->user();
    $this->userMembershipOrAbort($project, $user->id);
    abort_unless($review->annotation->task->project_id === $project->id, 404);
    if ($review->reviewer_id !== $user->id) {
        return redirect()->route('staff.review.next', $project->id)
            ->with('error', 'This review is not assigned to you.');
    }

    $payload = $request->validate([
        'reason'      => 'required|in:technical_issue,unclear_audio,unclear_annotation,personal_reason,other',
        'description' => 'nullable|string|max:1000',
    ]);

    $review->skipByUser($user, $payload['reason'], $payload['description'] ?? null);

    return redirect()->route('staff.flow.success', [
        'project' => $project->id,
        'kind'    => 'review_skipped',
        'seconds' => 0,
    ])->with('info', 'Review skipped.');
}


    /* =========================================================
     * Success page
     * ========================================================= */
    public function success(Request $request, Project $project): Response
    {
        $user    = $request->user();
        $kind    = $request->query('kind', 'attempt_submitted'); // attempt_submitted | attempt_skipped | review_approved | review_skipped
        $seconds = (int) $request->query('seconds', 0);
    
        // Map kind -> action expected by FlowResult.vue
        $action = match ($kind) {
            'attempt_submitted' => 'submitted',
            'attempt_skipped'   => 'skipped',
            'review_approved'   => 'approved',
            'review_skipped'    => 'skipped',
            default             => 'submitted',
        };
    
        $isAttempt = str_starts_with($kind, 'attempt_');
    
        // === Today window
        $startDay = now()->startOfDay();
    
        // === Active batches?
        $hasActiveBatches = $project->batches()
            ->whereIn('status', ['published', 'in_progress'])
            ->exists();
    
        // === Compute "today count" + "is there a next item?"
        if ($isAttempt) {
            // Count: submitted today by this user in this project
            $taskCount = Annotation::query()
                ->where('annotator_id', $user->id)
                ->whereHas('task', fn($q) => $q->where('project_id', $project->id))
                ->where('submitted_at', '>=', $startDay)
                ->count();
    
            // Next availability (resume or pending not skipped)
            $resumeAttemptExists = Task::query()
                ->where('project_id', $project->id)
                ->whereHas('batch', fn($q) => $q->whereIn('status', ['published','in_progress']))
                ->whereIn('status', ['assigned','in_progress'])
                ->where('assigned_to', $user->id)
                ->where(function($q){ $q->whereNull('expires_at')->orWhere('expires_at','>', now()); })
                ->exists();
    
            $skippedTaskIds = SkipActivity::query()
                ->where('user_id', $user->id)
                ->where('project_id', $project->id)
                ->where('activity_type', 'task')
                ->pluck('task_id');
    
            $pendingAvailableExists = Task::query()
                ->where('project_id', $project->id)
                ->whereHas('batch', fn($q) => $q->whereIn('status', ['published','in_progress']))
                ->where('status', 'pending')
                ->whereNotIn('id', $skippedTaskIds)
                ->exists();
    
            $nextTaskAvailable = $hasActiveBatches && ($resumeAttemptExists || $pendingAvailableExists);
        } else {
            // Count: approved reviews today by this user in this project
            $taskCount = Review::query()
                ->where('reviewer_id', $user->id)
                ->whereHas('annotation.task', fn($q) => $q->where('project_id', $project->id))
                ->where('action', 'approved')
                ->where('completed_at', '>=', $startDay)
                ->count();
    
            // Next availability (resume review or submitted annotations exist)
            $resumeReviewExists = Review::query()
                ->whereHas('annotation.task', fn($q) => $q->where('project_id', $project->id))
                ->where('reviewer_id', $user->id)
                ->whereNull('completed_at')
                ->where(function($q){ $q->whereNull('expires_at')->orWhere('expires_at','>', now()); })
                ->exists();
    
            $submittedExists = Annotation::query()
                ->where('status', 'submitted')
                ->whereHas('task', function ($q) use ($project) {
                    $q->where('project_id', $project->id)
                      ->whereHas('batch', fn($b) => $b->whereIn('status', ['published', 'in_progress']));
                })
                ->exists();
    
            $nextTaskAvailable = $hasActiveBatches && ($resumeReviewExists || $submittedExists);
        }
    
        return Inertia::render('Staff/FlowResult', [
            'project'           => ['id' => $project->id, 'name' => $project->name],
            // what FlowResult.vue expects:
            'action'            => $action,
            'timeSpent'         => $seconds,
            'taskCount'         => $taskCount,
            'nextTaskAvailable' => $nextTaskAvailable,
            // keep old fields in case you still reference them somewhere:
            'kind'              => $kind,
            'seconds'           => $seconds,
        ]);
    }
    
}
