<?php

namespace App\Repositories;

use App\Core\Interfaces\Paginate;
use App\Core\Repository\BaseRepository;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class StudentRepository extends BaseRepository implements Paginate
{
    public function getModel(): string
    {
        return Student::class;
    }

    public function paginate(array $filters = [], int $perPage = 10, int $page = 1, string $orderBy = 'id', string $orderDirection = 'desc'): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->getQuery()
            ->with(['user' => function ($query) {
                $query->select('id', 'is_active', 'role');
            }]);

        $query = $this->filters($query, $filters);

        $query = $this->sort($query, $orderBy, $orderDirection);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function filters($query, array $filters = []): Builder
    {
        $query = $this->model->query();

        if (! empty($filter['full_name'])) {
            $query->where('full_name', 'LIKE', '%'.$filter['full_name'].'%');
        }

        if (! empty($filter['grade_level'])) {
            $query->where('grade_level', $filter['grade_level']);
        }

        if (! empty($filter['gender'])) {
            $query->where('gender', $filter['gender']);
        }

        if (! empty($filter['parent_phone'])) {
            $query->where('parent_phone', 'LIKE', '%'.$filter['parent_phone'].'%');
        }

        return $query;
    }

    public function sort(Builder $query, string $orderBy, string $orderDirection): Builder
    {
        return $query->orderBy($orderBy, $orderDirection);
    }
}
