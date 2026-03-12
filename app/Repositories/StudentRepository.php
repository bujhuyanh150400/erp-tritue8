<?php

namespace App\Repositories;

use App\Models\Student;
use App\Core\Repository\BaseRepository;

class StudentRepository extends BaseRepository
{
    public function getModel(): string
    {
        return Student::class;
    }

    public function getList(array $filter = [], int $perPage = 10)
    {
        $query = $this->filterStudentList($filter);

        return $query
            ->with('user')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    private function filterStudentList(array $filter = [])
    {
        $query = $this->model->query();

        if (!empty($filter['full_name'])) {
            $query->where('full_name', 'LIKE', '%' . $filter['full_name'] . '%');
        }

        if (!empty($filter['grade_level'])) {
            $query->where('grade_level', $filter['grade_level']);
        }

        if (!empty($filter['gender'])) {
            $query->where('gender', $filter['gender']);
        }

        if (!empty($filter['parent_phone'])) {
            $query->where('parent_phone', 'LIKE', '%' . $filter['parent_phone'] . '%');
        }

        return $query;
    }
}
