<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UserCreatedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Get user statistics for dashboard
     */
    public function getUserStatistics(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();
        $adminUsers = User::where('role', 'system_admin')->count();
        $projectOwners = User::where('role', 'project_owner')->count();
        $regularUsers = User::where('role', 'user')->count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $unverifiedUsers = User::whereNull('email_verified_at')->count();
        $recentUsers = User::where('created_at', '>=', now()->subDays(30))->count();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $inactiveUsers,
            'admin_users' => $adminUsers,
            'project_owners' => $projectOwners,
            'regular_users' => $regularUsers,
            'verified_users' => $verifiedUsers,
            'unverified_users' => $unverifiedUsers,
            'recent_users' => $recentUsers,
        ];
    }
    /**
     * Create a new user
     */
    public function createUser(
        string $firstName,
        string $lastName,
        string $email,
        string $role = 'user',
        bool $isActive = true,
        bool $emailVerified = false
    ): User {
        // Generate a temporary password
        $tempPassword = Str::random(16);

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make($tempPassword),
            'role' => $role,
            'is_active' => $isActive,
            'email_verified_at' => $emailVerified ? now() : null,
        ]);

        // Send notification to user
        $user->notify(new UserCreatedNotification($user));

        return $user;
    }

    /**
     * Update an existing user
     */
    public function updateUser(
        User $user,
        string $firstName,
        string $lastName,
        string $email,
        string $role = 'user',
        bool $isActive = true,
        bool $emailVerified = false
    ): User {
        $wasVerified = $user->email_verified_at !== null;
        $verifiedStatusChanged = $wasVerified !== $emailVerified;

        $user->update([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'role' => $role,
            'is_active' => $isActive,
            'email_verified_at' => $emailVerified ? ($wasVerified ? $user->email_verified_at : now()) : null,
        ]);

        return $user;
    }

    /**
     * Set user active status
     */
    public function setUserActiveStatus(User $user, bool $isActive): User
    {
        $user->update([
            'is_active' => $isActive,
        ]);

        return $user;
    }

    /**
     * Set user verified status
     */
    public function setUserVerifiedStatus(User $user, $timestampOrNull)
    {
        $user->email_verified_at = $timestampOrNull;
        $user->save();
    }
    
}