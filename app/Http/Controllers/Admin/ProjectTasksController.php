<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SegmentationTasksExport;
use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\AnnotationDimension;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel; // require: composer require maatwebsite/excel
use App\Exports\ProjectTasksExport;

class ProjectTasksController extends Controller
{
    /**
     * Show project tasks table (filters + pagination)
     */

    public function index(Request $request, Project $project)
    {
        $this->authorizeProjectView($project);

        // filters
        $status = $request->string('status', 'all')->toString(); // all|accepted|rejected|under_review|pending|assigned|in_progress|approved
        $batchIds = collect($request->input('batches', []))->filter()->map('intval')->values()->all();
        $q = trim((string) $request->input('q', ''));

        $query = Task::query()
            ->with([
                'batch:id,name,status',
                'audioFile:id,project_id,original_filename,duration,file_path',
                'annotations' => function ($q) {
                    $q->with(['annotationValues:annotation_id,dimension_id,selected_value,numeric_value']);
                },
                'approvedAnnotation' => function ($q) {
                    $q->with(['annotationValues:annotation_id,dimension_id,selected_value,numeric_value']);
                },
            ])
            ->where('project_id', $project->id);

        if (!empty($batchIds)) {
            $query->whereIn('batch_id', $batchIds);
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('id', (int) $q)
                    ->orWhereHas('audioFile', fn($aq) => $aq->where('original_filename', 'like', "%{$q}%"));
            });
        }

        // status mapping (unchanged)
        if ($status !== 'all') {
            $map = [
                'accepted' => ['approved'], // accepted => approved in your data model
                'rejected' => ['rejected'],
                'under_review' => ['under_review'],
                'pending' => ['pending'],
                'assigned' => ['assigned'],
                'in_progress' => ['in_progress'],
                'approved' => ['approved'],
            ];
            $query->whereIn('status', $map[$status] ?? [$status]);
        }

        $tasks = $query->orderByDesc('id')->paginate(20)->withQueryString();

        // batches for filter
        $batches = $project->batches()->select('id', 'name', 'status')->orderBy('id', 'desc')->get();

        // === Per-type meta for the table/export ===
        // Annotation projects: keep current dimensions payload (so nothing breaks)
        $dimensions = null;
        if ($project->project_type === 'annotation') {
            $dimensions = AnnotationDimension::where('project_id', $project->id)
                ->orderBy('display_order')
                ->get(['id', 'name', 'dimension_type', 'scale_min', 'scale_max']);
        }

        // Segmentation projects: return the project's labels so the export modal can show "Included / Excluded"
        // (We only return project labels; custom labels are created by workers and flagged during export)
        $segmentationLabels = null;
        if ($project->project_type === 'segmentation') {
            $segmentationLabels = $project->segmentationLabels()
                ->orderBy('project_segmentation_labels.display_order') // if you use display order on the pivot
                ->get(['segmentation_labels.id', 'segmentation_labels.name', 'segmentation_labels.color', 'segmentation_labels.description']);
        }

        // transform rows (lightweight; page stays fast)
        $rows = $tasks->through(function (Task $t) {
            return [
                'id' => $t->id,
                'status' => $t->status,
                'batch' => $t->batch?->name,
                'audio' => [
                    'filename' => $t->audioFile?->original_filename,
                    'url' => $t->audioFile?->url,
                    'duration' => $t->audioFile?->duration,
                ],
                'submitted_at' => optional($t->completed_at)?->toDateTimeString(),
                'approved_at' => optional($t->approved_at ?? null)?->toDateTimeString(),
            ];
        });

        return Inertia::render('Admin/Projects/Tasks/Index', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'project_type' => $project->project_type, // 👈 needed by the view & export modal
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'batches' => $batchIds,
            ],
            'batches' => $batches,
            // Only include for annotation projects (keeps current implementation)
            'dimensions' => $dimensions
                ? $dimensions->map(fn($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'dimension_type' => $d->dimension_type,
                ])
                : null,
            // Only include for segmentation projects (for export modal include/exclude UI)
            'segmentation_labels' => $segmentationLabels
                ? $segmentationLabels->map(fn($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                    'description' => $l->description,
                ])
                : null,
            'tasks' => [
                'data' => $rows->items(),
                'links' => $tasks->linkCollection(),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                    'from' => $tasks->firstItem(),
                    'to' => $tasks->lastItem(),
                ],
            ],
        ]);
    }



    public function export(Request $request, Project $project)
    {
        $this->authorizeProjectView($project);

        // Common params
        $validated = $request->validate([
            'type' => 'required|in:json,csv,excel',
            'status' => 'nullable|string',
            'batches' => 'nullable|array',
            'batches.*' => 'integer',
            // Segmentation-only
            'include_label_ids' => 'nullable|array',
            'include_label_ids.*' => 'integer',
            'exclude_label_ids' => 'nullable|array',
            'exclude_label_ids.*' => 'integer',
        ]);

        $type = $validated['type'];
        $status = $validated['status'] ?? 'all';
        $batchIds = collect($validated['batches'] ?? [])->filter()->map('intval')->values()->all();


        if ($project->project_type === 'segmentation') {
            // Label filters (project labels only). Remove any overlap (exclude wins removed from include UI, but sanitize server-side too).
            $include = collect($validated['include_label_ids'] ?? [])->filter()->map('intval')->values();
            $exclude = collect($validated['exclude_label_ids'] ?? [])->filter()->map('intval')->values();
            if ($include->isNotEmpty() && $exclude->isNotEmpty()) {
                $exclude = $exclude->diff($include); // ensure no overlap
            }
            $includeSet = $include->flip(); // id => idx
            $excludeSet = $exclude->flip();

            // Base query + eager loads
            $query = Task::query()
                ->with([
                    'batch:id,name,status',
                    'audioFile:id,project_id,original_filename,duration,file_path',
                    'segments' => fn($q) => $q->orderBy('start_time'),
                    'segments.projectLabel:id,name,color',
                    'segments.customLabel:id,name,color',
                ])
                ->where('project_id', $project->id);

            if (!empty($batchIds)) {
                $query->whereIn('batch_id', $batchIds);
            }

            if ($status !== 'all') {
                $map = [
                    'accepted' => ['approved'],
                    'rejected' => ['rejected'],
                    'under_review' => ['under_review'],
                    'pending' => ['pending'],
                    'assigned' => ['assigned'],
                    'in_progress' => ['in_progress'],
                    'approved' => ['approved'],
                ];
                $query->whereIn('status', $map[$status] ?? [$status]);
            }

            $tasks = $query->orderBy('id')->get();

            // Per-segment filter:
            // - If includeSet NOT empty => allow only non-custom segments whose project_label_id ∈ includeSet, then drop those also in excludeSet.
            // - If includeSet empty      => allow all segments EXCEPT those with project_label_id ∈ excludeSet. (Custom segments are allowed.)
            $segmentPasses = function ($seg) use ($includeSet, $excludeSet): bool {
                $isCustom = !is_null($seg->custom_label_id);

                // Exclusion check (applies only to project labels)
                if (!$isCustom) {
                    $pid = (int) $seg->project_label_id;
                    if ($excludeSet->has($pid)) {
                        return false;
                    }
                }

                // Inclusion logic
                if ($includeSet->isNotEmpty()) {
                    // Only allow project labels that are specifically included
                    if ($isCustom) {
                        return false; // customs aren't on include list
                    }
                    $pid = (int) $seg->project_label_id;
                    return $includeSet->has($pid);
                }

                // No include filter: allow customs + any project label not excluded
                return true;
            };

            // Build one row per TASK, with nested segments (filtered)
            $rows = [];
            foreach ($tasks as $task) {
                $segments = [];
                foreach ($task->segments as $seg) {
                    if (!$segmentPasses($seg)) {
                        continue;
                    }
                    $isCustom = !is_null($seg->custom_label_id);
                    $labelId = $isCustom ? $seg->custom_label_id : $seg->project_label_id;
                    $labelName = $isCustom ? ($seg->customLabel?->name) : ($seg->projectLabel?->name);

                    $segments[] = [
                        'segment_start' => (float) $seg->start_time,
                        'segment_end' => (float) $seg->end_time,
                        'label_id' => $labelId,
                        'label_name' => $labelName,
                        'is_custom' => (bool) $isCustom,
                        'notes' => $seg->notes,
                    ];
                }

                // Skip tasks that have zero matching segments
                if (empty($segments)) {
                    continue;
                }

                $rows[] = [
                    'task_id' => $task->id,
                    'batch' => $task->batch?->name,
                    'status' => $task->status,
                    'audio_filename' => $task->audioFile?->original_filename,
                    'audio_url' => $task->audioFile?->url, // accessor if available
                    'duration_sec' => $task->audioFile?->duration,
                    'submitted_at' => optional($task->completed_at)->toDateTimeString(),
                    'approved_at' => optional($task->approved_at ?? null)->toDateTimeString(),
                    // Nested segments (array) – for JSON we keep array; for CSV/XLSX we will stringify below
                    'segments' => $segments,
                ];
            }

            $filenameBase = 'project_' . $project->id . '_segments_' . now()->format('Ymd_His');

            if ($type === 'json') {
                return Response::streamDownload(function () use ($rows) {
                    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }, "{$filenameBase}.json", ['Content-Type' => 'application/json']);
            }

            // For CSV/XLSX, stringify the "segments" array into JSON for a single cell
            $flat = array_map(function ($row) {
                $row['segments'] = json_encode($row['segments'], JSON_UNESCAPED_SLASHES);
                return $row;
            }, $rows);

            if ($type === 'csv') {
                $headers = array_keys($flat[0] ?? ['task_id' => null, 'segments' => '[]']);
                return Response::streamDownload(function () use ($flat, $headers) {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, $headers);
                    foreach ($flat as $r) {
                        fputcsv($out, Arr::only($r, $headers));
                    }
                    fclose($out);
                }, "{$filenameBase}.csv", ['Content-Type' => 'text/csv']);
            }

            // Excel (XLSX via maatwebsite/excel)
            return Excel::download(
                new SegmentationTasksExport($flat), // uses headings from first row keys
                "{$filenameBase}.xlsx"
            );
        }

        /**
         * ==========================
         * ANNOTATION PROJECTS (unchanged)
         * ==========================
         */
        $query = Task::query()
            ->with([
                'batch:id,name,status',
                'audioFile:id,project_id,original_filename,duration,file_path',
                'annotations' => function ($q) {
                    $q->with(['annotationValues:annotation_id,dimension_id,selected_value,numeric_value']);
                },
                'approvedAnnotation' => function ($q) {
                    $q->with(['annotationValues:annotation_id,dimension_id,selected_value,numeric_value']);
                },
            ])
            ->where('project_id', $project->id);

        if (!empty($batchIds)) {
            $query->whereIn('batch_id', $batchIds);
        }

        if ($status !== 'all') {
            $map = [
                'accepted' => ['approved'],
                'rejected' => ['rejected'],
                'under_review' => ['under_review'],
                'pending' => ['pending'],
                'assigned' => ['assigned'],
                'in_progress' => ['in_progress'],
                'approved' => ['approved'],
            ];
            $query->whereIn('status', $map[$status] ?? [$status]);
        }

        $tasks = $query->orderBy('id')->get();

        $dimensions = AnnotationDimension::where('project_id', $project->id)
            ->orderBy('display_order')
            ->get(['id', 'name', 'dimension_type']);

        $rows = [];
        foreach ($tasks as $task) {
            [$dimValues, $changedFlags] = $this->finalDimensionValues($task);
            $row = [
                'task_id' => $task->id,
                'batch' => $task->batch?->name,
                'status' => $task->status,
                'audio_filename' => $task->audioFile?->original_filename,
                'audio_url' => $task->audioFile?->url,
                'duration_sec' => $task->audioFile?->duration,
                'submitted_at' => optional($task->completed_at)->toDateTimeString(),
                'approved_at' => optional($task->approved_at ?? null)->toDateTimeString(),
            ];
            foreach ($dimensions as $d) {
                $key = $d->name;
                $row[$key] = $dimValues[$d->id] ?? null;
                $row[$key . '_changed'] = $changedFlags[$d->id] ?? false;
            }
            $rows[] = $row;
        }

        $filenameBase = 'project_' . $project->id . '_tasks_' . now()->format('Ymd_His');

        if ($type === 'json') {
            return Response::streamDownload(function () use ($rows) {
                echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }, "{$filenameBase}.json", ['Content-Type' => 'application/json']);
        }

        if ($type === 'csv') {
            $headers = array_keys($rows[0] ?? ['task_id' => null]);
            return Response::streamDownload(function () use ($rows, $headers) {
                $out = fopen('php://output', 'w');
                fputcsv($out, $headers);
                foreach ($rows as $r) {
                    fputcsv($out, Arr::only($r, $headers));
                }
                fclose($out);
            }, "{$filenameBase}.csv", ['Content-Type' => 'text/csv']);
        }

        // Excel (XLSX via maatwebsite/excel)
        $export = new ProjectTasksExport($rows);
        return Excel::download($export, "{$filenameBase}.xlsx");
    }



    /**
     * Compute "final" dimension values for a task:
     * - If review corrected a value, use corrected
     * - Else use annotation value (prefer approved annotation if exists)
     * Return: [valuesByDimId, changedFlagsByDimId]
     */
    private function finalDimensionValues(Task $task): array
    {
        $valuesByDimId = [];
        $changedFlags = [];

        // Prefer approved annotation if it exists
        $annotation = $task->approvedAnnotation ?: $task->annotations->first();

        if ($annotation) {
            foreach ($annotation->annotationValues as $val) {
                $valuesByDimId[$val->dimension_id] = $val->selected_value ?? $val->numeric_value;
                $changedFlags[$val->dimension_id] = false;
            }
        }

        // If there is a review with changes, overlay the corrected values
        $review = $task->annotationReviews?->first(); // latest/only for the task
        if ($review && $review->reviewChanges && $review->action === 'approved') {
            foreach ($review->reviewChanges as $chg) {
                $valuesByDimId[$chg->dimension_id] =
                    $chg->corrected_value ?? $chg->corrected_numeric ?? $valuesByDimId[$chg->dimension_id] ?? null;
                $changedFlags[$chg->dimension_id] = true;
            }
        }

        return [$valuesByDimId, $changedFlags];
    }

    private function authorizeProjectView(Project $project): void
    {
        $user = auth()->user();
        // Reuse your canViewProject logic from ProjectController if you prefer.
        if ($user->isSystemAdmin())
            return;
        if ($user->id === $project->owner_id)
            return;
        $ok = $project->members()->where('user_id', $user->id)->where('is_active', true)->exists();
        abort_unless($ok, 403, 'You do not have permission to view this project.');
    }
}
