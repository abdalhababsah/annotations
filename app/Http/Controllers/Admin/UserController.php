<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {
    }

    /**
     * Display a listing of users with filters and pagination
     */
    public function index(Request $request): Response
    {
        // Authorize access (system admins only)
        abort_unless(auth()->user()->isSystemAdmin(), 403, 'You do not have permission to manage users.');

        // Build query with filters
        $query = User::query();

        // Apply filters
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) LIKE ?", ["%{$search}%"]);
            });
        }

        if ($request->filled('role')) {
            if ($request->role !== 'all') {
                $query->where('role', $request->role);
            }
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('verified')) {
            if ($request->verified === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->verified === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        // Apply sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Paginate results
        $perPage = (int) $request->get('per_page', 10);
        $users = $query->paginate($perPage)->withQueryString();

        // Get statistics
        $statistics = $this->userService->getUserStatistics();

        return Inertia::render('Admin/Users/Index', [
            'users' => [
                'data' => $users->items(),
                'links' => $users->linkCollection(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'from' => $users->firstItem(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'to' => $users->lastItem(),
                    'total' => $users->total(),
                ],
            ],
            'statistics' => $statistics,
            'filters' => [
                'q' => $request->q,
                'role' => $request->role,
                'status' => $request->status,
                'verified' => $request->verified,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()->isSystemAdmin(), 403, 'You do not have permission to create users.');

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => ['required', Rule::in(['system_admin', 'project_owner', 'user'])],
            'is_active' => 'boolean',
            'email_verified' => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            // Create user and send notification
            $user = $this->userService->createUser(
                $validated['first_name'],
                $validated['last_name'],
                $validated['email'],
                $validated['role'],
                $validated['is_active'] ?? true,
                $validated['email_verified'] ?? false
            );

            if (!$user || !$user->id) {
                throw new \Exception('User creation failed (no ID returned).');
            }

            DB::commit();

            return redirect()->route('admin.users.index')
                ->with('success', 'User created successfully! A password reset link has been sent to their email.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('User creation failed', ['error' => $e->getMessage(), 'email' => $validated['email']]);
            return back()->with('error', 'Failed to create user: ' . $e->getMessage());
        }
    }


    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        // Authorize access
        abort_unless(auth()->user()->isSystemAdmin(), 403, 'You do not have permission to update users.');

        // Validate request
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => ['required', Rule::in(['system_admin', 'project_owner', 'user'])],
            'is_active' => 'boolean',
            'email_verified' => 'boolean',
        ]);

        try {
            // Update user
            $this->userService->updateUser(
                $user,
                $validated['first_name'],
                $validated['last_name'],
                $validated['email'],
                $validated['role'],
                $validated['is_active'] ?? true,
                $validated['email_verified'] ?? false
            );

            return back()->with('success', 'User updated successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Toggle user active status
     */
    public function toggleActive(User $user)
    {
        // Authorize access
        abort_unless(auth()->user()->isSystemAdmin(), 403, 'You do not have permission to update users.');

        // Prevent deactivating yourself
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        try {
            $newStatus = !$user->is_active;
            $this->userService->setUserActiveStatus($user, $newStatus);

            $message = $newStatus
                ? 'User activated successfully!'
                : 'User deactivated successfully!';

            return back()->with('success', $message);

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update user status: ' . $e->getMessage());
        }
    }

    /**
     * Toggle user verified status
     */
    public function toggleVerified(User $user)
    {
        // Authorize access
        abort_unless(auth()->user()->isSystemAdmin(), 403, 'You do not have permission to update users.');

        try {
            $newStatus = $user->email_verified_at ? null : now();
            $this->userService->setUserVerifiedStatus($user, $newStatus);

            $message = $newStatus
                ? 'User verified successfully!'
                : 'User verification removed!';

            return back()->with('success', $message);

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update user verification: ' . $e->getMessage());
        }
    }

    /**
     * Send password reset link to user
     */
    public function sendPasswordReset(User $user)
    {
        // Authorize access
        abort_unless(auth()->user()->isSystemAdmin(), 403, 'You do not have permission to update users.');

        try {
            $status = Password::sendResetLink(['email' => $user->email]);

            if ($status === Password::RESET_LINK_SENT) {
                return back()->with('success', 'Password reset link sent to user!');
            } else {
                return back()->with('error', 'Failed to send password reset email.');
            }

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send password reset: ' . $e->getMessage());
        }
    }
}