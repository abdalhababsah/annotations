<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskCustomLabel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomLabelController extends Controller
{
    /**
     * Create a new custom label for a specific task
     */
    public function create(Request $request, Project $project, Task $task)
    {
        
        $user = $request->user();
        
        // Verify project membership and permissions
        $membership = $project->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$membership) {
            return back()->withErrors(['error' => 'You are not a member of this project.']);
        }

        // Check if custom labels are allowed in this project
        if (!$project->allow_custom_labels) {
            return back()->withErrors(['error' => 'Custom labels are not allowed in this project.']);
        }

        // Verify task belongs to project
        if ($task->project_id !== $project->id) {
            return back()->withErrors(['error' => 'Task does not belong to this project.']);
        }

        // Validate request data
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                // Ensure uniqueness within this task
                Rule::unique('task_custom_labels')->where(function ($query) use ($task) {
                    return $query->where('task_id', $task->id);
                })
            ],
            'color' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/'
            ],
            'description' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Create the custom label
            $customLabel = TaskCustomLabel::create([
                'task_id' => $task->id,
                'name' => $validated['name'],
                'color' => $validated['color'],
                'description' => $validated['description'] ?? null,
                'created_by' => $user->id,
            ]);

            DB::commit();

            // Return with the label data in flash for frontend to use
            return back()->with([
                'success' => 'Custom label created successfully.',
                'label' => [
                    'id' => $customLabel->id,
                    'name' => $customLabel->name,
                    'uuid' => $customLabel->uuid,
                    'color' => $customLabel->color,
                    'description' => $customLabel->description,
                    'task_id' => $customLabel->task_id,
                    'created_by' => $customLabel->created_by,
                    'created_at' => $customLabel->created_at->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors([
                'error' => 'Failed to create custom label. Please try again.'
            ]);
        }
    }

    /**
     * Alternative JSON response method (if you prefer API-style responses)
     */
    public function createJson(Request $request, Project $project, Task $task): JsonResponse
    {
        $user = $request->user();
        
        // Verify project membership and permissions
        $membership = $project->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this project.'
            ], 403);
        }

        // Check if custom labels are allowed in this project
        if (!$project->allow_custom_labels) {
            return response()->json([
                'success' => false,
                'message' => 'Custom labels are not allowed in this project.'
            ], 422);
        }

        // Verify task belongs to project
        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project.'
            ], 404);
        }

        // Validate request data
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('task_custom_labels')->where(function ($query) use ($task) {
                    return $query->where('task_id', $task->id);
                })
            ],
            'color' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/'
            ],
            'description' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Create the custom label
            $customLabel = TaskCustomLabel::create([
                'task_id' => $task->id,
                'name' => $validated['name'],
                'color' => $validated['color'],
                'description' => $validated['description'] ?? null,
                'created_by' => $user->id,
            ]);

            DB::commit();

            // Return the created label data
            return response()->json([
                'success' => true,
                'message' => 'Custom label created successfully.',
                'label' => [
                    'id' => $customLabel->id,
                    'uuid' => $customLabel->uuid,
                    'name' => $customLabel->name,
                    
                    'color' => $customLabel->color,
                    'description' => $customLabel->description,
                    'task_id' => $customLabel->task_id,
                    'created_by' => $customLabel->created_by,
                    'created_at' => $customLabel->created_at->toIso8601String(),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create custom label. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    /**
     * Get all custom labels for a specific task
     */
    public function index(Request $request, Project $project, Task $task): JsonResponse
    {
        $user = $request->user();
        
        // Verify project membership
        $membership = $project->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this project.'
            ], 403);
        }

        // Verify task belongs to project
        if ($task->project_id !== $project->id) {
            return response()->json([
                'success' => false,
                'message' => 'Task does not belong to this project.'
            ], 404);
        }

        // Get all custom labels for this task
        $customLabels = TaskCustomLabel::where('task_id', $task->id)
            ->with('creator:id,first_name,last_name,email')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($label) {
                return [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                    'description' => $label->description,
                    'task_id' => $label->task_id,
                    'created_by' => [
                        'id' => $label->creator->id,
                        'name' => $label->creator->full_name,
                        'email' => $label->creator->email,
                    ],
                    'created_at' => $label->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'labels' => $customLabels,
            'allow_custom_labels' => $project->allow_custom_labels
        ]);
    }

    /**
     * Update a custom label (only by creator or project admin)
     */
    public function update(Request $request, Project $project, Task $task, TaskCustomLabel $customLabel): JsonResponse
    {
        $user = $request->user();
        
        // Verify project membership
        $membership = $project->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this project.'
            ], 403);
        }

        // Verify task and label relationship
        if ($task->project_id !== $project->id || $customLabel->task_id !== $task->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid task or label.'
            ], 404);
        }

        // Check permissions: only creator or project admin can update
        if ($customLabel->created_by !== $user->id && $membership->role !== 'project_admin') {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit custom labels you created.'
            ], 403);
        }

        // Validate request data
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('task_custom_labels')->where(function ($query) use ($task) {
                    return $query->where('task_id', $task->id);
                })->ignore($customLabel->id)
            ],
            'color' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/'
            ],
            'description' => 'nullable|string|max:500'
        ]);

        try {
            $customLabel->update([
                'name' => $validated['name'],
                'color' => $validated['color'],
                'description' => $validated['description'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Custom label updated successfully.',
                'label' => [
                    'id' => $customLabel->id,
                    'uuid' => $customLabel->uuid,
                    'name' => $customLabel->name,
                    'color' => $customLabel->color,
                    'description' => $customLabel->description,
                    'task_id' => $customLabel->task_id,
                    'created_by' => $customLabel->created_by,
                    'updated_at' => $customLabel->updated_at->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update custom label. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a custom label (only if not in use)
     */
    public function destroy(Request $request, Project $project, Task $task, TaskCustomLabel $customLabel): JsonResponse
    {
        $user = $request->user();
        
        // Verify project membership
        $membership = $project->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this project.'
            ], 403);
        }

        // Verify task and label relationship
        if ($task->project_id !== $project->id || $customLabel->task_id !== $task->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid task or label.'
            ], 404);
        }

        // Check permissions: only creator or project admin can delete
        if ($customLabel->created_by !== $user->id && $membership->role !== 'project_admin') {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete custom labels you created.'
            ], 403);
        }

        // Check if label is in use by any segments
        $segmentCount = \App\Models\TaskSegment::where('custom_label_id', $customLabel->id)->count();
        
        if ($segmentCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete custom label. It is currently used by {$segmentCount} segment(s)."
            ], 422);
        }

        try {
            $customLabel->delete();

            return response()->json([
                'success' => true,
                'message' => 'Custom label deleted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete custom label. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}