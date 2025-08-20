<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function findByRole(string $role): Collection;
    public function getActiveUsers(): Collection;
    public function getProjectOwners(): Collection;
    public function getUsersWithProjects(): Collection;
    public function searchUsers(string $query): Collection;
}
