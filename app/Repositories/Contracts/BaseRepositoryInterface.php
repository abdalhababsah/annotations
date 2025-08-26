<?php
// BaseRepositoryInterface.php - UNCHANGED (Already correct)

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BaseRepositoryInterface
{
    public function find(int $id): ?Model;
    public function findOrFail(int $id): Model;
    public function findBy(array $criteria): Collection;
    public function findOneBy(array $criteria): ?Model;
    public function create(array $data): Model;
    public function update(int $id, array $data): Model;
    public function delete(int $id): bool;
    public function all(): Collection;
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    public function count(): int;
}