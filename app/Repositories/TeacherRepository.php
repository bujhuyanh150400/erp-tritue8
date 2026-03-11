<?php

namespace App\Repositories;

use App\Models\Teacher;
use App\Core\Repository\BaseRepository;

class TeacherRepository extends BaseRepository
{
    public function getModel(): string
    {
        return Teacher::class;
    }

    public function getListTeacher(array $filters = [], int $perPage = 10)
    {
        $query = $this->model->query()->with('user');

        if (!empty($filters['full_name'])) {
            $query->where('full_name', 'ILIKE', '%' . $filters['full_name'] . '%');
        }

        if (!empty($filters['phone'])) {
            $query->where('phone', 'ILIKE', '%' . $filters['phone'] . '%');
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['joined_at_from'])) {
            $query->where('joined_at', '>=', $filters['joined_at_from']);
        }

        if (!empty($filters['joined_at_to'])) {
            $query->where('joined_at', '<=', $filters['joined_at_to']);
        }

        return $query
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
