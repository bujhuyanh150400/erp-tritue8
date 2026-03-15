<?php

namespace App\Repositories;

use App\Core\Interfaces\Paginate;
use App\Core\Repository\BaseRepository;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class StaffRepository extends BaseRepository implements Paginate
{
    public function getModel(): string
    {
        return Staff::class;
    }

    public function paginate(
        array $filters = [],
        int $perPage = 10,
        int $page = 1,
        string $orderBy = 'id',
        string $orderDirection = 'desc'
    ): LengthAwarePaginator {

        $query = $this->getQuery()
            ->with(['user' => function ($query) {
                $query->select('id','username','is_active','role');
            }]);

        $query = $this->filters($query, $filters);

        $query = $this->sort($query, $orderBy, $orderDirection);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function filters(Builder $query, array $filters = []): Builder
    {
        if (!empty($filters['keyword'])) {

            $keyword = trim($filters['keyword']);

            $query->where(function ($q) use ($keyword) {

                $q->where('full_name','like',"%{$keyword}%")
                    ->orWhere('phone','like',"%{$keyword}%")
                    ->orWhereHas('user', function ($userQuery) use ($keyword) {

                        if (is_numeric($keyword)) {
                            $userQuery->where('id',(int)$keyword);
                        }

                        $userQuery->orWhere('username','like',"%{$keyword}%");
                    });

            });
        }

        if (!empty($filters['role_type'])) {
            $query->where('role_type',$filters['role_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status',$filters['status']);
        }

        if (!empty($filters['joined_at_from'])) {
            $query->where('joined_at','>=',$filters['joined_at_from']);
        }

        if (!empty($filters['joined_at_to'])) {
            $query->where('joined_at','<=',$filters['joined_at_to']);
        }

        return $query;
    }

    public function sort(Builder $query,string $orderBy,string $orderDirection): Builder
    {
        return $query->orderBy($orderBy,$orderDirection);
    }

    public function findStaffByUserId(int $userId): ?Staff
    {
        return $this->model->query()
            ->with('user')
            ->where('user_id',$userId)
            ->first();
    }
}
