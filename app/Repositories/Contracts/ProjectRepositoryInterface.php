<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface extends BaseRepositoryInterface
{
    public function findByOwner(User $owner): Collection;
    public function findByType(string $type): Collection;
    public function findByStatus(string $status): Collection;
    public function getActiveProjects(): Collection;
    public function getUserProjects(User $user): Collection;
    public function getProjectsWithProgress(): Collection;
    public function createWithOwner(array $data, User $owner): Project;
    public function assignToOwner(Project $project, User $owner, User $assigner): Project;
    public function getProjectStatistics(Project $project): array;
}
