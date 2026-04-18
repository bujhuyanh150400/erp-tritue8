<?php

namespace App\Repositories;

use App\Core\Interfaces\FilterFilament;
use App\Core\Repository\BaseRepository;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class StudentRepository extends BaseRepository implements FilterFilament
{
    public function getModel(): string
    {
        return Student::class;
    }

    /**
     * Tạo query để lấy danh sách học sinh (Trang danh sách học sinh)
     * @param Builder $query
     * @return Builder
     */
    public function getListingQuery(Builder $query): Builder
    {
        return $this->query()
            ->with([
                'user',
                'activeClassEnrollments.class' // Eager load luôn danh sách lớp đang học
            ])
            // Hàm này tự động sinh ra cột 'total_stars' tính tổng cột 'amount'
            ->withSum('rewardPoints as total_stars', 'amount');
    }

    /**
     * Tạo query để lọc danh sách học sinh
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function setFilters(Builder $query, array $filters = []): Builder
    {
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

        if (! empty($filters['class_id'])) {
            $query->whereHas('activeClassEnrollments', function ($query) use ($filters) {
                $query->where('class_id', $filters['class_id']);
            });
        }


        return $query;
    }

    /**
     * Áp dụng bộ lọc cho danh sách báo cáo học sinh (StudentReportResource).
     * Chỉ kiểm tra trong tháng đã chọn học sinh đã có monthly_report hay chưa.
     */
    public function setStudentReportFilters(Builder $query, array $filters = []): Builder
    {
        $month = ! empty($filters['month']) ? (string) $filters['month'] : now()->format('Y-m');

        $query->withExists([
            'monthlyReports as has_monthly_report' => function (Builder $subQuery) use ($month) {
                $subQuery->where('month', $month);
            },
        ]);

        if (!empty($filters['class_id'])) {
            $query->whereHas('activeClassEnrollments', function (Builder $subQuery) use ($filters) {
                $subQuery->where('class_id', $filters['class_id']);
            });
        }

        if (!empty($filters['keyword']) && !empty(trim((string) $filters['keyword']))) {
            $keyword = trim((string) $filters['keyword']);
            $query->where(function (Builder $subQuery) use ($keyword) {
                $subQuery->where('students.full_name', 'like', "%{$keyword}%")
                    ->orWhere('students.parent_phone', 'like', "%{$keyword}%");
            });
        }

        if (!empty($filters['grade_level'])) {
            $query->where('students.grade_level', $filters['grade_level']);
        }

        return $query;
    }

    /**
     * Tạo query để lấy danh sách học sinh có thể thêm vào lớp học
     * @param int $classId
     * @return Builder
     */
    public function getAvailableStudentsForClassQuery(SchoolClass $class): Builder
    {
        return $this->query()
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('users.is_active', true)
            ->where('students.grade_level', $class->grade_level)
            ->whereNotIn('students.id', function ($query) use ($class) {
                $query->select('student_id')
                    ->from('class_enrollments')
                    ->where('class_id', $class->id)
                    ->whereNull('left_at');
            })
            ->orderBy("created_at", "DESC")
            ->select('students.*');
    }

    public function findStudentById(int $studentId): ?Student
    {
        return $this->query()
            ->with('user')
            ->find($studentId);
    }

}
