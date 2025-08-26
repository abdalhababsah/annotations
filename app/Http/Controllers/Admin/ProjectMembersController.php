<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectMembersController extends Controller
{
    public function __construct(
        private ProjectService $projectService,
        private ProjectRepositoryInterface $projectRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

// Controllers/Admin/ProjectMembersController.php

public function index(Request $request, int $project)
{
    $project = $this->projectRepository->findOrFail($project);
    $user = auth()->user();
    $this->authorizeView($project, $user);

    // Filters
    $q       = trim((string) $request->get('q', ''));
    $role    = $request->get('role'); // 'annotator'|'reviewer'|'project_admin'|null
    $status  = $request->has('is_active') ? (bool) $request->boolean('is_active') : null;
    $perPage = (int) ($request->get('perPage', 10));
    $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 10;

    $membersQuery = $project->members()
        ->whereHas('user')                           // ensure user exists
        ->with(['user:id,first_name,last_name,email'])
        // 🔽 apply filters
        ->when($role, fn ($q2) => $q2->where('role', $role))
        ->when(!is_null($status), fn ($q2) => $q2->where('is_active', $status))
        ->when($q !== '', function ($q2) use ($q) {
            $q2->whereHas('user', function ($uq) use ($q) {
                $like = '%' . $q . '%';
                $uq->where('first_name', 'like', $like)
                   ->orWhere('last_name', 'like', $like)
                   ->orWhereRaw("CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) LIKE ?", [$like])
                   ->orWhere('email', 'like', $like);
            });
        })
        ->orderByDesc('created_at');

    $members = $membersQuery->paginate($perPage)->withQueryString();

    return Inertia::render('Admin/Projects/Members/Index', [
        'project' => [
            'id' => $project->id,
            'name' => $project->name,
            'owner' => [
                'id' => $project->owner?->id,
                'name' => ($project->owner?->full_name)
                    ?? trim(($project->owner?->first_name ?? '') . ' ' . ($project->owner?->last_name ?? '')),
                'email' => $project->owner?->email,
            ],
            'status' => $project->status,
        ],
        'members' => [
            'data' => $members->through(function (ProjectMember $m) {
                $u = $m->user;
                $name = $u?->full_name ?? trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                return [
                    'id' => $m->id,
                    'user' => [
                        'id' => $u?->id,
                        'name' => $name ?: '[deleted user]',
                        'email' => $u?->email,
                    ],
                    'role' => $m->role,
                    'is_active' => (bool) $m->is_active,
                    'workload_limit' => $m->workload_limit,
                    'assigned_at' => $m->created_at->format('Y-m-d H:i'),
                ];
            }),
            'links' => $members->linkCollection(),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ],
        'filters' => [
            'q' => $q,
            'role' => $role,
            'is_active' => $status,
            'perPage' => $perPage,
        ],
        'can' => [
            'manageTeam' => $this->canManageTeam($project, $user),
        ],
    ]);
}



    public function availableUsers(int $project): JsonResponse
    {
        $project = $this->projectRepository->findOrFail($project);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        $available = $this->projectService->getAvailableUsersForProject($project);

        return response()->json(['users' => $available->values()]);
    }

    public function store(Request $request, int $project): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($project);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => ['required', Rule::in(['annotator', 'reviewer', 'project_admin'])],
            'workload_limit' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $this->projectService->addMemberToProject($project, $validated, $user);
            return back()->with('success', 'Team member assigned successfully!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, int $project, int $member)
    {
        $project = $this->projectRepository->findOrFail($project);
        $user = auth()->user();
        $this->authorizeManage($project, $user);
    
        $validated = $request->validate([
            'role' => ['required', Rule::in(['annotator', 'reviewer', 'project_admin'])],
            'workload_limit' => 'nullable|integer|min:1|max:50',
            'is_active' => 'boolean',
        ]);
    
        $m = $project->members()->whereKey($member)->firstOrFail();
    
        $m->role = $validated['role'];
        $m->is_active = (bool)($validated['is_active'] ?? $m->is_active);
        $m->workload_limit = array_key_exists('workload_limit', $validated) ? $validated['workload_limit'] : $m->workload_limit;
        $m->save();
    
        return back()->with('success', 'Member updated successfully!');
    }
    
    

    public function destroy(int $project, int $member): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($project);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        try {
            $this->projectService->removeMemberFromProject($project, $member);
            return back()->with('success', 'Team member removed.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // --- Permission helpers (kept identical to your ProjectController) ---
    private function canManageTeam(Project $project, User $user): bool
    {
        return $user->isSystemAdmin() ||
            $user->id === $project->owner_id ||
            $project->members()->where('user_id', $user->id)
                ->where('role', 'project_admin')
                ->where('is_active', true)
                ->exists();
    }

    private function canViewProject(Project $project, User $user): bool
    {
        if ($user->isSystemAdmin())
            return true;
        if ($user->id === $project->owner_id)
            return true;
        return $project->members()->where('user_id', $user->id)
            ->where('is_active', true)->exists();
    }

    private function authorizeManage(Project $project, User $user): void
    {
        if (!$this->canManageTeam($project, $user)) {
            abort(403, 'You do not have permission to manage this project team.');
        }
    }

    private function authorizeView(Project $project, User $user): void
    {
        if (!$this->canViewProject($project, $user)) {
            abort(403, 'You do not have permission to view this project.');
        }
    }
}
