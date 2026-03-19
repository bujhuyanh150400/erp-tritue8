<?php

namespace App\Repositories;

use App\Core\Interfaces\FilterFilament;
use App\Core\Repository\BaseRepository;
use App\Models\ClassEnrollment;
use App\Models\RewardPoint;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StudentRepository extends BaseRepository implements FilterFilament
{
    public function getModel(): string
    {
        return Student::class;
    }

    /**
     * Tạo query để lấy danh sách học sinh
     * @param Builder $query
     * @return Builder
     */
    public function getListingQuery(Builder $query): Builder
    {
        return $this->query()
            ->with(['user'])
            ->addSelect([
                'students.*',
                // Môn đang học
                'subject_names' => DB::table('class_enrollments')
                    ->join('classes', 'class_enrollments.class_id', '=', 'classes.id')
                    ->join('subjects', 'classes.subject_id', '=', 'subjects.id')
                    ->whereColumn('class_enrollments.student_id', 'students.id')
                    ->whereNull('class_enrollments.left_at')
                    ->select(DB::raw("string_agg(subjects.name, ',')")),

                // Tổng số sao (Reward Points)
                'total_stars' => DB::table('reward_points')
                    ->whereColumn('student_id', 'students.id')
                    ->selectRaw('COALESCE(SUM(amount), 0)'),

            ]);
    }

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


        return $query;
    }

}
