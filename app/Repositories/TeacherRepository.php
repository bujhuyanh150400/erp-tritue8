<?php

namespace App\Repositories;

use App\Constants\AttendanceStatus;
use App\Constants\ClassStatus;
use App\Core\Interfaces\Paginate;
use App\Core\Repository\BaseRepository;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class TeacherRepository extends BaseRepository implements Paginate
{
    public function getModel(): string
    {
        return Teacher::class;
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
                $query->select('id', 'username', 'is_active', 'role');
            }]);

        $query = $this->filters($query, $filters);

        $query = $this->sort($query, $orderBy, $orderDirection);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function filters($query, array $filters = []): Builder
    {
        if (!empty($filters['keyword']) && trim($filters['keyword']) !== '') {

            $keyword = trim($filters['keyword']);

            $query->where(function ($q) use ($keyword) {

                $q->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhereHas('user', function ($userQuery) use ($keyword) {

                        if (is_numeric($keyword)) {
                            $userQuery->where('id', (int)$keyword);
                        }

                        $userQuery->orWhere('username', 'like', "%{$keyword}%");
                    });

            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['phone'])) {
            $query->where('phone', 'like', '%' . $filters['phone'] . '%');
        }

        if (!empty($filters['joined_at_from'])) {
            $query->where('joined_at', '>=', $filters['joined_at_from']);
        }

        if (!empty($filters['joined_at_to'])) {
            $query->where('joined_at', '<=', $filters['joined_at_to']);
        }

        return $query;
    }

    public function sort(Builder $query, string $orderBy, string $orderDirection): Builder
    {
        return $query->orderBy($orderBy, $orderDirection);
    }

    /**
     * Tìm giáo viên theo id
     */
    public function findTeacherByUserId(int $userId): ?Teacher
    {
        return $this->model->query()
            ->with('user')
            ->where('user_id', $userId)
            ->first();
    }

    public function getListingQuery(Builder $query): Builder
    {
        return $query
            ->leftJoin('users', 'users.id', '=', 'teachers.user_id')

            ->leftJoin('classes', function ($join) {
                $join->on('classes.teacher_id', '=', 'teachers.id')
                    ->where('classes.status', 1);
            })

            ->leftJoin('subjects', 'subjects.id', '=', 'classes.subject_id')

            ->select([
                'teachers.*',
                'users.is_active',

                \DB::raw("STRING_AGG(DISTINCT subjects.name, ', ') as subjects"),
                \DB::raw("COUNT(DISTINCT classes.id) as total_classes"),
            ])

            ->groupBy('teachers.id', 'users.is_active');
    }

    public function setFilters(Builder $query, array $data): Builder
    {
        if (!empty($data['keyword'])) {
            $keyword = trim($data['keyword']);

            $query->where(function ($q) use ($keyword) {
                $q->where('teachers.full_name', 'ILIKE', "%{$keyword}%")
                    ->orWhere('teachers.phone', 'ILIKE', "%{$keyword}%")
                    ->orWhereRaw('CAST(users.id AS TEXT) ILIKE ?', ["%{$keyword}%"]);
            });
        }

        if (!empty($data['status'])) {
            $query->where('teachers.status', $data['status']);
        }

        if (!empty($data['is_active']) || $data['is_active'] === '0') {
            $query->where('users.is_active', (int)$data['is_active']);
        }

        if (!empty($data['subject_id'])) {
            $query->whereExists(function ($sub) use ($data) {
                $sub->selectRaw(1)
                    ->from('classes')
                    ->whereColumn('classes.teacher_id', 'teachers.id')
                    ->where('classes.subject_id', $data['subject_id'])
                    ->where('classes.status', 1);
            });
        }

        return $query;
    }

    /**
     * Lấy dữ liệu chuyên cần theo từng lớp của giáo viên trong tháng
     */
    public function getAttendanceStatsByClass(int $teacherId, string $month): array
    {
        $monthCarbon = \Illuminate\Support\Carbon::parse($month);
        $monthStart = $monthCarbon->copy()->startOfMonth();
        $monthEnd = $monthCarbon->copy()->endOfMonth();

        $classes = DB::table('classes')
            ->where('teacher_id', $teacherId)
            ->where('status', ClassStatus::Active->value)
            ->select('id', 'name')
            ->get();

        $result = [];
        foreach ($classes as $class) {
            $stats = DB::table('attendance_records as ar')
                ->join('attendance_sessions as sess', 'ar.session_id', '=', 'sess.id')
                ->where('sess.class_id', $class->id)
                ->whereBetween('sess.session_date', [$monthStart, $monthEnd])
                ->selectRaw('
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE ar.status IN (?, ?)) as present_count
                ', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
                ->first();

            $rate = $stats->total > 0
                ? round(($stats->present_count / $stats->total) * 100, 1)
                : 0;

            $result[] = [
                'name' => $class->name,
                'rate' => (float) $rate,
            ];
        }

        return $result;
    }
}
