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
use App\Models\TaskCustomLabel;
use App\Models\TaskSegment;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(private TaskService $tasks)
    {
    }

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

    protected function userSkippedTask(int $userId, Project $project, Task $task): bool
    {
        return SkipActivity::query()
            ->where('user_id', $userId)
            ->where('project_id', $project->id)
            ->where('activity_type', 'task')
            ->where('task_id', $task->id)
            ->exists();
    }

    protected function isNeverSelectable(Task $task): bool
    {
        return in_array($task->status, [
            'submitted',
            'under_review',
            'approved',
            'skipped',
            'expired'
        ], true);
    }
    private function normalizeChangeType(string $action): string
    {
        return match ($action) {
            'create'   => 'added',
            'update'   => 'modified',
            'delete'   => 'deleted',
            'added', 'modified', 'deleted' => $action,
            default => 'modified', // or throw a validation exception
        };
    }
    
    /* =========================================================
     * Attempt (Annotator / Labeler)
     * ========================================================= */
    public function nextAttempt(Request $request, Project $project)
    {
        $user = $request->user();

        // If user already has an active task (in any member project), bounce to earliest
        if ($active = $this->tasks->firstActiveTaskForUser($user->id)) {
            return redirect()
                ->route('staff.attempt.show', [$active->project_id, $active->id])
                ->with('info', 'Resuming your active task.');
        }

        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['annotator', 'project_admin']), 403, 'Not allowed.');

        // Try resuming within this project
        $resume = Task::query()
            ->where('project_id', $project->id)
            ->whereHas('batch', fn($q) => $q->whereIn('status', ['published', 'in_progress']))
            ->whereIn('status', ['assigned', 'in_progress'])
            ->where('assigned_to', $user->id)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('assigned_at')
            ->first();

        if ($resume) {
            return redirect()->route('staff.attempt.show', [$project->id, $resume->id]);
        }

        if (!$this->ensureActiveBatches($project)) {
            return back()->with('warning', 'No published or in-progress batches.');
        }

        $skipped = SkipActivity::getSkippedTasksForUser($user->id, $project->id);

        $next = Task::query()
            ->where('project_id', $project->id)
            ->whereHas('batch', fn($q) => $q->whereIn('status', ['published', 'in_progress']))
            ->where('status', 'pending')
            ->whereNotIn('id', $skipped)
            ->orderBy('id')
            ->first();

        if (!$next) {
            return back()->with('info', 'No available tasks right now.');
        }

        return redirect()->route('staff.attempt.show', [$project->id, $next->id]);
    }

    public function showAttempt(Request $request, Project $project, Task $task)
    {
        $user = $request->user();

        // Never-render certain states
        if ($this->isNeverSelectable($task)) {
            return redirect()->route('staff.dashboard')->with('error', 'This task is not available.');
        }

        // Membership + correct project
        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['annotator', 'project_admin']), 403, 'Not allowed.');
        abort_unless($task->project_id === $project->id, 404);

        // Only from active batches
        abort_unless($task->batch && in_array($task->batch->status, ['published', 'in_progress']), 403, 'Task batch is not active.');

        // If user has another active task (any project), always resume the FIRST one
        if ($active = $this->tasks->firstActiveTaskForUser($user->id, $task->id)) {
            if ($active->id !== $task->id) {
                return redirect()
                    ->route('staff.attempt.show', [$active->project_id, $active->id])
                    ->with('info', 'You already have an active task. Resuming your earliest active task.');
            }
        }

        // If this user previously skipped this task, block (even via direct URL)
        if ($this->userSkippedTask($user->id, $project, $task)) {
            return redirect()->route('staff.dashboard')->with('error', 'You previously skipped this task.');
        }

        // Expired?
        if ($task->isExpired()) {
            $task->handleExpiration();
            return Inertia::render('Staff/TaskExpired', [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                ],
                'task' => ['id' => $task->id],
            ]);
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

                if ($locked->batch && $locked->batch->status === 'published') {
                    $locked->batch->update(['status' => 'in_progress']);
                }
            });
            $task->refresh();
        } elseif ($task->assigned_to && $task->assigned_to !== $user->id) {
            abort(403, 'Task assigned to another user.');
        }

        // === BRANCH by project type ===
        if ($project->project_type === 'segmentation') {
            // Load or create latest annotation header (draft or submitted)
            $annotation = $task->annotations()
                ->where('annotator_id', $user->id)
                ->orderByDesc('id')
                ->first();

            $segments = collect();
            if ($annotation) {
                $segments = TaskSegment::query()
                    ->with(['projectLabel:id,name,color', 'customLabel:id,name,color'])
                    ->where('annotation_id', $annotation->id)
                    ->orderBy('start_time')
                    ->get()
                    ->map(function ($s) {
                        return [
                            'id' => $s->id,
                            'start_time' => (float) $s->start_time,
                            'end_time' => (float) $s->end_time,
                            'project_label' => $s->project_label_id ? [
                                'id' => $s->projectLabel->id,
                                'name' => $s->projectLabel->name,
                                'color' => $s->projectLabel->color,
                            ] : null,
                            'custom_label' => $s->custom_label_id ? [
                                'id' => $s->customLabel->id,
                                'name' => $s->customLabel->name,
                                'color' => $s->customLabel->color,
                            ] : null,
                            'notes' => $s->notes,
                        ];
                    });
            }

            // Labels available for this project (ordered)
            $projectLabels = $project->segmentationLabels()
                ->orderBy('project_segmentation_labels.display_order')
                ->get(['segmentation_labels.id', 'segmentation_labels.name', 'segmentation_labels.color', 'segmentation_labels.description'])
                ->map(fn($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                    'description' => $l->description,
                ]);

            // Existing custom labels for this task
            $existingCustomLabels = TaskCustomLabel::where('task_id', $task->id)
                ->orderBy('created_at', 'asc')
                ->get(['id', 'name', 'color', 'description'])
                ->map(fn($cl) => [
                    'id' => $cl->id,
                    'name' => $cl->name,
                    'color' => $cl->color,
                    'description' => $cl->description,
                    'isCustom' => true,
                ]);

            return Inertia::render('Staff/LabelTask', [
                'mode' => 'attempt',
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'task_time_minutes' => $project->task_time_minutes,
                    'allow_custom_labels' => (bool) $project->allow_custom_labels,
                ],
                'task' => [
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
                'labels' => $projectLabels,
                'customLabels' => $existingCustomLabels,
                'draft' => [
                    'annotation_id' => $annotation?->id,
                    'segments' => $segments,
                ],
            ]);
        }

        // ===== Annotation attempt =====
        $dimensions = AnnotationDimension::where('project_id', $project->id)
            ->with(['dimensionValues' => fn($q) => $q->orderBy('display_order')])
            ->orderBy('display_order')
            ->get();

        $annotation = $task->annotations()
            ->where('annotator_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $annotationValues = $annotation
            ? $annotation->annotationValues()->get(['dimension_id', 'selected_value', 'numeric_value', 'notes'])
            : collect();

        return Inertia::render('Staff/AttemptTask', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'task_time_minutes' => $project->task_time_minutes,
            ],
            'task' => [
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
            'dimensions' => $dimensions->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'description' => $d->description,
                'dimension_type' => $d->dimension_type,
                'scale_min' => $d->scale_min,
                'scale_max' => $d->scale_max,
                'is_required' => (bool) $d->is_required,
                'values' => $d->dimensionValues->map(fn($v) => [
                    'value' => $v->value,
                    'label' => $v->label ?: $v->value
                ]),
            ]),
            'draft' => [
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
            return Inertia::render('Staff/TaskExpired', [
                'project' => ['id' => $project->id, 'name' => $project->name],
                'task' => ['id' => $task->id],
            ]);
        }

        if ($project->project_type === 'segmentation') {
            $payload = $request->validate([
                'segments' => 'required|array',
                'segments.*.start_time' => 'required|numeric|min:0',
                'segments.*.end_time' => 'required|numeric|gt:segments.*.start_time',
                'segments.*.project_label_id' => 'nullable|integer|exists:segmentation_labels,id',
                'segments.*.custom_label' => 'nullable|array',
                'segments.*.custom_label.name' => 'required_with:segments.*.custom_label|string|max:100',
                'segments.*.custom_label.color' => 'required_with:segments.*.custom_label|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'segments.*.notes' => 'nullable|string|max:2000',
                'spent_seconds' => 'nullable|integer|min:0',
            ]);

            $allowCustom = (bool) $project->allow_custom_labels;

            DB::transaction(function () use ($task, $user, $payload, $allowCustom) {
                $annotation = Annotation::firstOrCreate(
                    ['task_id' => $task->id, 'annotator_id' => $user->id],
                    ['status' => 'draft', 'started_at' => now()]
                );

                if ($annotation->status !== 'submitted') {
                    $annotation->update([
                        'status' => 'draft',
                        'total_time_spent' => $payload['spent_seconds'] ?? $annotation->total_time_spent,
                    ]);
                }

                $this->syncSegments($annotation, $payload['segments'], $allowCustom);
            });

            return back()->with('success', 'Draft saved.');
        }

        // Annotation draft
        $payload = $request->validate([
            'values' => 'required|array',
            'spent_seconds' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($task, $user, $payload) {
            $annotation = Annotation::firstOrCreate(
                ['task_id' => $task->id, 'annotator_id' => $user->id],
                ['status' => 'draft', 'started_at' => now()]
            );

            if ($annotation->status !== 'submitted') {
                $annotation->update([
                    'status' => 'draft',
                    'total_time_spent' => $payload['spent_seconds'] ?? $annotation->total_time_spent,
                ]);
            }

            $byDim = collect($payload['values'])->keyBy('dimension_id');
            $dimensionIds = $byDim->keys()->all();

            AnnotationValue::where('annotation_id', $annotation->id)
                ->whereNotIn('dimension_id', $dimensionIds)
                ->delete();

            foreach ($byDim as $dimensionId => $v) {
                AnnotationValue::updateOrCreate(
                    ['annotation_id' => $annotation->id, 'dimension_id' => $dimensionId],
                    [
                        'selected_value' => $v['selected_value'] ?? null,
                        'numeric_value' => $v['numeric_value'] ?? null,
                        'notes' => $v['notes'] ?? null,
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
            return Inertia::render('Staff/TaskExpired', [
                'project' => ['id' => $project->id, 'name' => $project->name],
                'task' => ['id' => $task->id],
            ]);
        }

        if ($project->project_type === 'segmentation') {
            $payload = $request->validate([
                'segments' => 'required|array|min:1',
                'segments.*.start_time' => 'required|numeric|min:0',
                'segments.*.end_time' => 'required|numeric|gt:segments.*.start_time',
                'segments.*.project_label_id' => 'nullable|integer|exists:segmentation_labels,id',
                'segments.*.custom_label' => 'nullable|array',
                'segments.*.custom_label.name' => 'required_with:segments.*.custom_label|string|max:100',
                'segments.*.custom_label.color' => 'required_with:segments.*.custom_label|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'segments.*.notes' => 'nullable|string|max:2000',
                'spent_seconds' => 'nullable|integer|min:0',
            ]);

            $spent = (int) ($payload['spent_seconds'] ?? 0);
            $allowCustom = (bool) $project->allow_custom_labels;

            DB::transaction(function () use ($task, $user, $payload, $spent, $allowCustom) {
                $annotation = Annotation::firstOrCreate(
                    ['task_id' => $task->id, 'annotator_id' => $user->id],
                    ['status' => 'draft', 'started_at' => now()]
                );

                // Sync segments
                $this->syncSegments($annotation, $payload['segments'], $allowCustom);

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

            return redirect()->route('staff.flow.success', [
                'project' => $project->id,
                'kind' => 'attempt_submitted',
                'seconds' => $spent,
            ])->with('success', 'Submission sent for review.');
        }

        // Annotation submit
        $payload = $request->validate([
            'values' => 'required|array',
            'spent_seconds' => 'nullable|integer|min:0',
        ]);
        $spent = (int) ($payload['spent_seconds'] ?? 0);

        DB::transaction(function () use ($task, $user, $payload, $spent) {
            $annotation = Annotation::firstOrCreate(
                ['task_id' => $task->id, 'annotator_id' => $user->id],
                ['status' => 'draft', 'started_at' => now()]
            );

            $byDim = collect($payload['values'])->keyBy('dimension_id');
            $dimensionIds = $byDim->keys()->all();

            AnnotationValue::where('annotation_id', $annotation->id)
                ->whereNotIn('dimension_id', $dimensionIds)
                ->delete();

            foreach ($byDim as $dimensionId => $v) {
                AnnotationValue::updateOrCreate(
                    ['annotation_id' => $annotation->id, 'dimension_id' => $dimensionId],
                    [
                        'selected_value' => $v['selected_value'] ?? null,
                        'numeric_value' => $v['numeric_value'] ?? null,
                        'notes' => $v['notes'] ?? null,
                    ]
                );
            }

            $annotation->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'total_time_spent' => $spent ?: $annotation->total_time_spent,
            ]);

            $task->update([
                'status' => 'under_review',
                'started_at' => $task->started_at ?: now(),
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('staff.flow.success', [
            'project' => $project->id,
            'kind' => 'attempt_submitted',
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
            'kind' => 'attempt_skipped',
            'seconds' => 0,
        ])->with('info', 'Task skipped.');
    }

    /* =========================================================
     * Review (Reviewer) - Approve only
     * ========================================================= */
    public function nextReview(Request $request, Project $project)
    {
        $user = $request->user();

        // No parallel work of any type
        if ($activeTask = $this->tasks->firstActiveTaskForUser($user->id)) {
            return redirect()
                ->route('staff.attempt.show', [$activeTask->project_id, $activeTask->id])
                ->with('error', 'You already have an active task. Finish it before reviewing.');
        }

        if ($activeReview = $this->tasks->firstActiveReviewForUser($user->id)) {
            $pid = optional(optional($activeReview->annotation)->task)->project_id ?? $project->id;
            return redirect()
                ->route('staff.review.show', [$pid, $activeReview->id])
                ->with('info', 'Resuming your active review.');
        }

        $membership = $this->userMembershipOrAbort($project, $user->id);
        abort_unless(in_array($membership->role, ['reviewer', 'project_admin']), 403, 'Not allowed.');

        if (!$this->ensureActiveBatches($project)) {
            return back()->with('warning', 'No published or in-progress batches.');
        }

        $annotation = $project->getNextReviewForUser($user->id);
        if (!$annotation) {
            return back()->with('info', 'No items ready for review.');
        }

        $review = $project->assignReviewToUser($annotation->id, $user->id);
        if (!$review) {
            return back()->with('error', 'Could not assign review.');
        }

        return redirect()->route('staff.review.show', [$project->id, $review->id]);
    }

    public function showReview(Request $request, Project $project, Review $review)
    {
        $user = $request->user();

        // Must belong to this project
        abort_unless(
            $review->annotation && $review->annotation->task && $review->annotation->task->project_id === $project->id,
            404
        );

        // Must be assigned to this reviewer
        if ($review->reviewer_id !== $user->id) {
            return redirect()->route('staff.dashboard')->with('error', 'This review is not assigned to you.');
        }

        // If user is no longer an active member, don’t let this count as “active”
        if (!$this->tasks->isActiveMember($project->id, $user->id)) {
            return redirect()->route('staff.dashboard')->with('warning', 'You are no longer a member of this project.');
        }

        if ($review->completed_at) {
            return redirect()->route('staff.dashboard')->with('info', 'This review is already completed.');
        }
        if ($review->isExpired()) {
            $review->handleExpiration();
            return redirect()->route('staff.dashboard')->with('warning', 'This review has expired.');
        }

        // No parallel work: if there is another active review, always resume the earliest one
        if ($active = $this->tasks->firstActiveReviewForUser($user->id, $review->id)) {
            if ($active->id !== $review->id) {
                $pid = optional(optional($active->annotation)->task)->project_id ?? $project->id;
                return redirect()
                    ->route('staff.review.show', [$pid, $active->id])
                    ->with('info', 'You already have an active review. Resuming your earliest active review.');
            }
        }

        // Also block if they somehow have an active task
        if ($activeTask = $this->tasks->firstActiveTaskForUser($user->id)) {
            return redirect()
                ->route('staff.attempt.show', [$activeTask->project_id, $activeTask->id])
                ->with('error', 'Finish your active task before reviewing.');
        }

        if ($project->project_type === 'segmentation') {
            $annotation = $review->annotation()->with(['task.audioFile'])->first();

            $segments = TaskSegment::query()
                ->with(['projectLabel:id,name,color', 'customLabel:id,name,color'])
                ->where('annotation_id', $annotation->id)
                ->orderBy('start_time')
                ->get()
                ->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'start_time' => (float) $s->start_time,
                        'end_time' => (float) $s->end_time,
                        'notes' => $s->notes,
                        'project_label' => $s->project_label_id ? [
                            'id' => $s->projectLabel->id,
                            'name' => $s->projectLabel->name,
                            'color' => $s->projectLabel->color,
                        ] : null,
                        'custom_label' => $s->custom_label_id ? [
                            'id' => $s->customLabel->id,
                            'name' => $s->customLabel->name,
                            'color' => $s->customLabel->color,
                        ] : null,
                    ];
                });

            $labels = $project->segmentationLabels()
                ->orderBy('project_segmentation_labels.display_order')
                ->get(['segmentation_labels.id', 'segmentation_labels.name', 'segmentation_labels.color', 'segmentation_labels.description'])
                ->map(fn($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                    'description' => $l->description,
                ]);

            $customLabels = TaskCustomLabel::where('task_id', $annotation->task_id)
                ->orderBy('created_at', 'asc')
                ->get(['id', 'name', 'color', 'description', 'uuid'])
                ->map(fn($cl) => [
                    'id' => $cl->id,
                    'name' => $cl->name,
                    'color' => $cl->color,
                    'uuid' => $cl->uuid,
                    'description' => $cl->description,
                    'isCustom' => true,
                ]);

            $allLabels = $labels->concat($customLabels);

            return Inertia::render('Staff/ReviewSegmentationTask', [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'review_time_minutes' => $project->review_time_minutes,
                    'allow_custom_labels' => $project->allow_custom_labels,
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
                    'audio' => [
                        'id' => $annotation->task->audioFile?->id,
                        'filename' => $annotation->task->audioFile?->original_filename,
                        'url' => $annotation->task->audioFile?->url,
                        'duration' => $annotation->task->audioFile?->duration,
                    ],
                    'segments' => $segments,
                ],
                'labels' => $allLabels,
            ]);
        }

        $annotation = $review->annotation()->with([
            'annotationValues.dimension.dimensionValues',
            'task.audioFile',
        ])->first();

        $dimensions = AnnotationDimension::where('project_id', $project->id)
            ->with(['dimensionValues' => fn($q) => $q->orderBy('display_order')])
            ->orderBy('display_order')
            ->get();

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
                'allow_custom_labels' => $project->allow_custom_labels,
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
            'dimensions' => $dimensions->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'description' => $d->description,
                'dimension_type' => $d->dimension_type,
                'scale_min' => $d->scale_min,
                'scale_max' => $d->scale_max,
                'is_required' => (bool) $d->is_required,
                'values' => $d->dimensionValues->map(fn($v) => ['value' => $v->value, 'label' => $v->label ?: $v->value]),
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

    public function approveReview(Request $request, Project $project, Review $review)
    {
        $user = $request->user();

        if ($project->project_type === 'segmentation') {
            // 1) Broaden validation to accept both action vocabularies
            $payload = $request->validate([
                'feedback_rating' => 'nullable|integer|min:1|max:5',
                'feedback_comment' => 'nullable|string|max:2000',
                'spent_seconds' => 'nullable|integer|min:0',
                'segment_changes' => 'nullable|array',
                // Accept both UI terms (create/update/delete) and DB terms (added/modified/deleted)
                'segment_changes.*.action' => 'required|in:create,update,delete,added,modified,deleted',
                'segment_changes.*.segment_id' => 'nullable|integer|exists:task_segments,id',
                'segment_changes.*.start_time' => 'required_unless:segment_changes.*.action,delete,deleted|numeric|min:0',
                'segment_changes.*.end_time' => 'required_unless:segment_changes.*.action,delete,deleted|numeric|gt:segment_changes.*.start_time',
                'segment_changes.*.project_label_id' => 'nullable|integer|exists:segmentation_labels,id',
                'segment_changes.*.custom_label' => 'nullable|array',
                'segment_changes.*.custom_label.name' => 'required_with:segment_changes.*.custom_label|string|max:100',
                'segment_changes.*.custom_label.color' => 'required_with:segment_changes.*.custom_label|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'segment_changes.*.notes' => 'nullable|string|max:2000',
            ]);


            $spent = (int) ($payload['spent_seconds'] ?? 0);
            $allowCustom = (bool) $project->allow_custom_labels;

            DB::transaction(function () use ($review, $payload, $allowCustom) {
                $annotation = $review->annotation()->with(['task'])->first();

                foreach (($payload['segment_changes'] ?? []) as $chg) {
                    $customLabelId = null;
                    if (isset($chg['custom_label']) && $allowCustom) {
                        $existingCustom = TaskCustomLabel::firstOrCreate(
                            ['task_id' => $annotation->task_id, 'name' => $chg['custom_label']['name']],
                            ['color'   => $chg['custom_label']['color'] ?? '#6B7280', 'created_by' => auth()->id()]
                        );
                        $customLabelId = $existingCustom->id;
                    }
                
                    $review->segmentChanges()->create([
                        'review_id'            => $review->id,
                        'segment_id'           => $chg['segment_id'] ?? null,
                        'change_type'          => $this->normalizeChangeType($chg['action']), // <-- map here
                        'new_start_time'       => isset($chg['start_time']) ? (float) $chg['start_time'] : null,
                        'new_end_time'         => isset($chg['end_time']) ? (float) $chg['end_time'] : null,
                        'new_project_label_id' => $chg['project_label_id'] ?? null,
                        'new_custom_label_id'  => $customLabelId,
                        'change_reason'        => $chg['notes'] ?? null,
                    ]);
                }
                

                $review->update([
                    'action' => 'approved',
                    'feedback_rating' => $payload['feedback_rating'] ?? $review->feedback_rating,
                    'feedback_comment' => $payload['feedback_comment'] ?? $review->feedback_comment,
                    'review_time_spent' => $payload['spent_seconds'] ?? $review->review_time_spent,
                    'completed_at' => now(),
                ]);

                $annotation->update(['status' => 'approved']);
                $annotation->task()->update(['status' => 'approved']);
            });

            return redirect()->route('staff.flow.success', [
                'project' => $project->id,
                'kind' => 'review_approved',
                'seconds' => $spent,
            ])->with('success', 'Segmentation review approved.');
        }

        // Annotation-only approve path
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

        $payload = $request->validate([
            'feedback_rating' => 'nullable|integer|min:1|max:5',
            'feedback_comment' => 'nullable|string|max:2000',
            'spent_seconds' => 'nullable|integer|min:0',

            'changes' => 'nullable|array',
            'changes.*.dimension_id' => 'required|integer|exists:annotation_dimensions,id',
            'changes.*.selected_value' => 'nullable|string|max:255',
            'changes.*.numeric_value' => 'nullable|integer',
            'changes.*.change_reason' => 'nullable|string|max:2000',
        ]);

        $spent = (int) ($payload['spent_seconds'] ?? 0);

        DB::transaction(function () use ($review, $payload) {
            $annotation = $review->annotation()->with(['annotationValues'])->first();

            $currentValues = $annotation->annotationValues->keyBy('dimension_id');

            $dimensions = AnnotationDimension::whereHas('project', function ($q) use ($annotation) {
                $q->where('id', $annotation->task->project_id);
            })->get()->keyBy('id');

            foreach (($payload['changes'] ?? []) as $chg) {
                $dimId = (int) $chg['dimension_id'];
                $dim = $dimensions->get($dimId);
                if (!$dim)
                    continue;

                $row = $currentValues->get($dimId);
                $origVal = $row?->selected_value;
                $origNum = $row?->numeric_value;

                $corrVal = array_key_exists('selected_value', $chg) ? $chg['selected_value'] : $origVal;
                $corrNum = array_key_exists('numeric_value', $chg) ? $chg['numeric_value'] : $origNum;

                if ($dim->dimension_type === 'categorical') {
                    if ($origVal !== $corrVal) {
                        AnnotationValue::updateOrCreate(
                            ['annotation_id' => $annotation->id, 'dimension_id' => $dimId],
                            ['selected_value' => $corrVal, 'numeric_value' => null]
                        );
                        $review->reviewChanges()->create([
                            'dimension_id' => $dimId,
                            'original_value' => $origVal,
                            'corrected_value' => $corrVal,
                            'original_numeric' => null,
                            'corrected_numeric' => null,
                            'change_reason' => $chg['change_reason'] ?? null,
                        ]);
                    }
                } elseif ($dim->dimension_type === 'numeric_scale') {
                    if ((int) $origNum !== (int) $corrNum) {
                        AnnotationValue::updateOrCreate(
                            ['annotation_id' => $annotation->id, 'dimension_id' => $dimId],
                            ['selected_value' => null, 'numeric_value' => $corrNum]
                        );
                        $review->reviewChanges()->create([
                            'dimension_id' => $dimId,
                            'original_value' => null,
                            'corrected_value' => null,
                            'original_numeric' => $origNum,
                            'corrected_numeric' => $corrNum,
                            'change_reason' => $chg['change_reason'] ?? null,
                        ]);
                    }
                }
            }

            $review->update([
                'action' => 'approved',
                'feedback_rating' => $payload['feedback_rating'] ?? $review->feedback_rating,
                'feedback_comment' => $payload['feedback_comment'] ?? $review->feedback_comment,
                'review_time_spent' => $payload['spent_seconds'] ?? $review->review_time_spent,
                'completed_at' => now(),
            ]);

            $annotation->update(['status' => 'approved']);
            $annotation->task()->update(['status' => 'approved']);
        });

        return redirect()->route('staff.flow.success', [
            'project' => $project->id,
            'kind' => 'review_approved',
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
            'reason' => 'required|in:technical_issue,unclear_audio,unclear_annotation,personal_reason,other',
            'description' => 'nullable|string|max:1000',
        ]);

        $review->skipByUser($user, $payload['reason'], $payload['description'] ?? null);

        return redirect()->route('staff.flow.success', [
            'project' => $project->id,
            'kind' => 'review_skipped',
            'seconds' => 0,
        ])->with('info', 'Review skipped.');
    }

    /* =========================================================
     * Success page
     * ========================================================= */
    public function success(Request $request, Project $project): Response
    {
        $user = $request->user();
        $kind = $request->query('kind', 'attempt_submitted'); // attempt_submitted | attempt_skipped | review_approved | review_skipped
        $seconds = (int) $request->query('seconds', 0);

        $action = match ($kind) {
            'attempt_submitted' => 'submitted',
            'attempt_skipped' => 'skipped',
            'review_approved' => 'approved',
            'review_skipped' => 'skipped',
            default => 'submitted',
        };

        $isAttempt = str_starts_with($kind, 'attempt_');
        $startDay = now()->startOfDay();

        $hasActiveBatches = $project->batches()
            ->whereIn('status', ['published', 'in_progress'])
            ->exists();

        if ($isAttempt) {
            $taskCount = Annotation::query()
                ->where('annotator_id', $user->id)
                ->whereHas('task', fn($q) => $q->where('project_id', $project->id))
                ->where('submitted_at', '>=', $startDay)
                ->count();

            $resumeAttemptExists = Task::query()
                ->where('project_id', $project->id)
                ->whereHas('batch', fn($q) => $q->whereIn('status', ['published', 'in_progress']))
                ->whereIn('status', ['assigned', 'in_progress'])
                ->where('assigned_to', $user->id)
                ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();

            $skippedTaskIds = SkipActivity::query()
                ->where('user_id', $user->id)
                ->where('project_id', $project->id)
                ->where('activity_type', 'task')
                ->pluck('task_id');

            $pendingAvailableExists = Task::query()
                ->where('project_id', $project->id)
                ->whereHas('batch', fn($q) => $q->whereIn('status', ['published', 'in_progress']))
                ->where('status', 'pending')
                ->whereNotIn('id', $skippedTaskIds)
                ->exists();

            $nextTaskAvailable = $hasActiveBatches && ($resumeAttemptExists || $pendingAvailableExists);
        } else {
            $taskCount = Review::query()
                ->where('reviewer_id', $user->id)
                ->whereHas('annotation.task', fn($q) => $q->where('project_id', $project->id))
                ->where('action', 'approved')
                ->where('completed_at', '>=', $startDay)
                ->count();

            $resumeReviewExists = Review::query()
                ->whereHas('annotation.task', fn($q) => $q->where('project_id', $project->id))
                ->where('reviewer_id', $user->id)
                ->whereNull('completed_at')
                ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
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
            'project' => ['id' => $project->id, 'name' => $project->name],
            'action' => $action,
            'timeSpent' => $seconds,
            'taskCount' => $taskCount,
            'nextTaskAvailable' => $nextTaskAvailable,
            'kind' => $kind,
            'seconds' => $seconds,
        ]);
    }

    /* =========================================================
     * Internal: Segments sync (create/update/delete)
     * ========================================================= */
    protected function syncSegments(Annotation $annotation, array $segments, bool $allowCustom): void
    {
        TaskSegment::where('annotation_id', $annotation->id)->delete();

        foreach ($segments as $seg) {
            $projectLabelId = $seg['project_label_id'] ?? null;
            $custom = $seg['custom_label'] ?? null;

            $customId = null;
            if ($custom) {
                abort_unless($allowCustom, 422, 'Custom labels are not allowed in this project.');
                $existing = TaskCustomLabel::firstOrCreate(
                    ['task_id' => $annotation->task_id, 'name' => $custom['name']],
                    ['color' => $custom['color'] ?? '#6B7280', 'created_by' => $annotation->annotator_id]
                );
                $customId = $existing->id;
                $projectLabelId = null; // mutually exclusive
            } else {
                $customId = null;
            }

            TaskSegment::create([
                'task_id' => $annotation->task_id,
                'annotation_id' => $annotation->id,
                'project_label_id' => $projectLabelId,
                'custom_label_id' => $customId,
                'start_time' => (float) $seg['start_time'],
                'end_time' => (float) $seg['end_time'],
                'notes' => $seg['notes'] ?? null,
            ]);
        }
    }
}
