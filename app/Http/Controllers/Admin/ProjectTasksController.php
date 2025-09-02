<?php

namespace App\Http\Controllers\Admin;

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
        $status = $request->string('status', 'all')->toString();          // all|accepted|rejected|under_review|pending|assigned|in_progress|approved
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

        // status mapping
        if ($status !== 'all') {
            $map = [
                'accepted' => ['approved'],   // accepted => approved in your data model
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

        // dimension meta for table header (and export)
        $dimensions = AnnotationDimension::where('project_id', $project->id)
            ->orderBy('display_order')->get(['id', 'name', 'dimension_type', 'scale_min', 'scale_max']);

        // transform rows (no heavy dimension values here; page stays fast)
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
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'batches' => $batchIds,
            ],
            'batches' => $batches,
            'dimensions' => $dimensions->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'dimension_type' => $d->dimension_type
            ]),
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

    /**
     * Export tasks with final dimension values (reviewer changes prioritized)
     * type: json|csv|excel
     * status: all|accepted|rejected|...
     * batches[]=id (optional)
     */
    public function export(Request $request, Project $project)
    {
        $this->authorizeProjectView($project);

        $validated = $request->validate([
            'type' => 'required|in:json,csv,excel',
            'status' => 'nullable|string',
            'batches' => 'nullable|array',
            'batches.*' => 'integer',
        ]);

        $type = $validated['type'];
        $status = $validated['status'] ?? 'all';
        $batchIds = collect($validated['batches'] ?? [])->filter()->map('intval')->values()->all();

        $query = Task::query()
            // In ProjectTasksController@index and export:
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
            ->orderBy('display_order')->get(['id', 'name', 'dimension_type']);

        // Prepare rows
        $rows = [];
        foreach ($tasks as $task) {
            [$dimValues, $changedFlags] = $this->finalDimensionValues($task);

            $row = [
                'task_id' => $task->id,
                'batch' => $task->batch?->name,
                'status' => $task->status,
                'audio_filename' => $task->audioFile?->original_filename,
                'audio_url' => $task->audioFile?->url,  // voice link in export
                'duration_sec' => $task->audioFile?->duration,
                'submitted_at' => optional($task->completed_at)?->toDateTimeString(),
                'approved_at' => optional($task->approved_at ?? null)?->toDateTimeString(),
            ];

            // Put each dimension into its own column (plus *_changed flag)
            foreach ($dimensions as $d) {
                $key = $d->name; // safe if names are column-safe; otherwise slug it
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
