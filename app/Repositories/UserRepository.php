<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function findByRole(string $role): Collection
    {
        return $this->model->where('role', $role)->get();
    }

    public function getActiveUsers(): Collection
    {
        return $this->model->where('is_active', true)->get();
    }

    public function getProjectOwners(): Collection
    {
        return $this->model->where('role', 'project_owner')
                          ->where('is_active', true)
                          ->get();
    }

    public function getUsersWithProjects(): Collection
    {
        return $this->model->with(['ownedProjects', 'projectMemberships.project'])
                          ->where('is_active', true)
                          ->get();
    }

    public function searchUsers(string $query): Collection
    {
        return $this->model->where(function ($q) use ($query) {
            $q->where('first_name', 'like', "%{$query}%")
              ->orWhere('last_name', 'like', "%{$query}%")
              ->orWhere('email', 'like', "%{$query}%");
        })->where('is_active', true)->get();
    }
}
