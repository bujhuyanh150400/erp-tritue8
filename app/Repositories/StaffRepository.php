<?php

namespace App\Repositories;

use App\Models\Staff;
use App\Core\Repository\BaseRepository;

class StaffRepository extends BaseRepository
{
    public function getModel(): string
    {
        return Staff::class;
    }

    public function getListStaff(array $filters = [], int $perPage = 10)
    {
        $query = $this->model->query()->with('user');

        if (!empty($filters['full_name'])) {
            $query->where('full_name', 'LIKE', '%' . $filters['full_name'] . '%');
        }

        if (!empty($filters['phone'])) {
            $query->where('phone', 'LIKE', '%' . $filters['phone'] . '%');
        }

        if (!empty($filters['role_type'])) {
            $query->where('role_type', $filters['role_type']);
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
