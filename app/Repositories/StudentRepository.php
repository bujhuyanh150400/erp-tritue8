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

        if (! empty($filters['keyword']) && ! empty(trim($filters['keyword']))) {
            $keyword = trim($filters['keyword']);
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('parent_phone', 'like', "%{$keyword}%")
                    ->orWhereHas('user', function ($userQuery) use ($keyword) {
                        // Tiếp tục bọc logic bên trong userQuery để an toàn tuyệt đối
                        $userQuery->where(function ($uq) use ($keyword) {
                            if (is_numeric($keyword)) {
                                $uq->where('id', (int) $keyword);
                            }
                            // Sử dụng orWhere bên trong nhóm này
                            $uq->orWhere('username', 'like', "%{$keyword}%");
                        });
                    });
            });
        }

        if (! empty($filters['grade_level'])) {
            $query->where('grade_level', $filters['grade_level']);
        }

        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (! empty($filters['parent_phone'])) {
            $query->where('parent_phone', 'LIKE', '%'.$filters['parent_phone'].'%');
        }

        return $query;
    }

    public function sort(Builder $query, string $orderBy, string $orderDirection): Builder
    {
        return $query->orderBy($orderBy, $orderDirection);
    }

    /**
     * Tìm học sinh theo ID người dùng
     */
    public function findStudentByUserId(int $userId): ?Student
    {
        return $this->model->query()
            ->with('user')
            ->where('user_id', $userId)
            ->first();
    }
}
