<?php

namespace App\Repositories;

use App\Constants\ClassStatus;
use App\Constants\ScheduleStatus;
use App\Core\Interfaces\FilterFilament;
use App\Core\Repository\BaseRepository;
use App\Models\SchoolClass;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ClassRepository extends BaseRepository implements FilterFilament
{
    public function getModel()
    {
        return SchoolClass::class;
    }


    public function getListingQuery(Builder $query): Builder
    {
        return $query
            ->with([
                'subject:id,name',
                'teacher:id,full_name',
            ])
            // Đếm số lượng học sinh đang học
            ->withCount(['enrollments as active_students_count' => function ($q) {
                $q->whereNull('left_at');
            }]);
    }

    public function setFilters(Builder $query, array $filters = []): Builder
    {
        // 1. Tìm kiếm nhanh (Keyword)
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'ilike', '%' . $keyword . '%')
                    ->orWhere('code', 'ilike', '%' . $keyword . '%')
                    // Tìm cả tên giáo viên thông qua quan hệ
                    ->orWhereHas('teacher', function ($tQ) use ($keyword) {
                        $tQ->where('full_name', 'ilike', '%' . $keyword . '%');
                    });
            });
        }

        // 2. Các bộ lọc chính xác
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }
        if (!empty($filters['grade_level'])) {
            $query->where('grade_level', $filters['grade_level']);
        }
        if (!empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        return $query;
    }

    /**
     * Đếm số lớp đang hoạt động theo môn học
     * @param int $subjectId
     * @return int
     */
    public function countClassActiveBySubjectId(int $subjectId)
    {
        return $this->query()
            ->where('subject_id', $subjectId)
            ->where('status', ClassStatus::Active)
            ->count();
    }



    /**
     * Lấy danh sách các lớp học đang dùng phòng và có sĩ số vượt quá sức chứa cho trước.
     * @param int $roomId ID của phòng học
     * @param int $capacity Sức chứa mới muốn kiểm tra
     * @return Collection
     */
    public function getClassesExceedingCapacity(int $roomId, int $capacity): Collection
    {
        return $this->query()
            // 1. Lọc các lớp đang có lịch học tại phòng này và lịch còn hạn
            ->whereHas('scheduleTemplates', function ($query) use ($roomId) {
                $query->where('room_id', $roomId)
                    ->currentlyActive(); // Dùng scope đã tạo ở Model ClassScheduleTemplate
            })
            // 2. Đếm số học sinh đang học (loại những em đã nghỉ: left_at IS NULL)
            ->withCount(['enrollments as si_so' => function ($query) {
                $query->whereNull('left_at');
            }])
            // 3. Chỉ lấy những lớp có sĩ số (si_so) LỚN HƠN sức chứa mới
            ->having('si_so', '>', $capacity)
            ->get();
    }

    public function updateStatus(int $classId, ClassStatus $status, ?Carbon $endAt = null): bool
    {
        $data = [
            'status' => $status->value
        ];
        if ($endAt) {
            $data['end_at'] = $endAt;
        }
        return $this->query()
            ->where('id', $classId)
            ->update($data);
    }

    public function countActiveByTeacher(int $teacherId): int
    {
        return $this->query()
            ->where('teacher_id', $teacherId)
            ->where('status', ClassStatus::Active->value)
            ->count();
    }

}
