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

            ->leftJoin('users','users.id','=','staff.user_id')

            ->leftJoin('staff_salary_configs as ssc', function ($join) {
                $join->on('ssc.staff_id','=','staff.id')
                    ->whereNull('ssc.effective_to');
            })

            ->select([
                'staff.*',
                'users.is_active',
                'ssc.salary_type',
                'ssc.salary_amount',
            ]);

        $query = $this->filters($query,$filters);

        $query = $this->sort($query,$orderBy,$orderDirection);

        return $query->paginate($perPage,['*'],'page',$page);
    }

    public function filters(Builder $query, array $filters = []): Builder
    {
        if (!empty($filters['keyword'])) {

            $keyword = trim($filters['keyword']);

            $query->where(function ($q) use ($keyword) {

                $q->where('staff.full_name','ILIKE',"%{$keyword}%")
                    ->orWhere('staff.phone','ILIKE',"%{$keyword}%")
                    ->orWhereRaw('CAST(users.id AS TEXT) ILIKE ?',["%{$keyword}%"]);
            });
        }

        if (!empty($filters['role_type'])) {
            $query->where('staff.role_type',$filters['role_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('staff.status',$filters['status']);
        }

        // 🔥 FIX is_active
        if (isset($filters['is_active']) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('users.is_active',(int)$filters['is_active']);
        }

        if (!empty($filters['joined_at_from'])) {
            $query->where('staff.joined_at','>=',$filters['joined_at_from']);
        }

        if (!empty($filters['joined_at_to'])) {
            $query->where('staff.joined_at','<=',$filters['joined_at_to']);
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

    public function getListingQuery(Builder $query): Builder
    {
        return $query
            ->leftJoin('users', 'users.id', '=', 'staff.user_id')

            ->leftJoin('staff_salary_configs as ssc', function ($join) {
                $join->on('ssc.staff_id', '=', 'staff.id')
                    ->whereNull('ssc.effective_to'); // config hiện hành
            })

            ->select([
                'staff.*',
                'users.is_active',
                'ssc.salary_type',
                'ssc.salary_amount',
            ]);
    }

    public function setFilters(Builder $query, array $data): Builder
    {
        // keyword
        if (!empty($data['keyword'])) {
            $keyword = trim($data['keyword']);

            $query->where(function ($q) use ($keyword) {
                $q->where('staff.full_name', 'ILIKE', "%{$keyword}%")
                    ->orWhere('staff.phone', 'ILIKE', "%{$keyword}%")
                    ->orWhereRaw('CAST(users.id AS TEXT) ILIKE ?', ["%{$keyword}%"]);
            });
        }

        // role
        if (!empty($data['role_type'])) {
            $query->where('staff.role_type', $data['role_type']);
        }

        // status
        if (!empty($data['status'])) {
            $query->where('staff.status', $data['status']);
        }

        // account status
        if (isset($data['is_active']) && $data['is_active'] !== null && $data['is_active'] !== '') {
            $query->where('users.is_active', (int)$data['is_active']);
        }

        return $query;
    }
}
