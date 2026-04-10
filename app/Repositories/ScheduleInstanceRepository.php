<?php

namespace App\Repositories;

use App\Constants\AttendanceStatus;
use App\Constants\AttendanceSessionStatus;
use App\Constants\ClassStatus;
use App\Constants\DayOfWeek;
use App\Constants\ScheduleStatus;
use App\Core\Interfaces\FilterFilament;
use App\Core\Repository\BaseRepository;
use App\Models\ClassEnrollment;
use App\Models\ScheduleInstance;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleInstanceRepository extends BaseRepository implements FilterFilament
{
    public function getModel(): string
    {
        return ScheduleInstance::class;
    }

    /**
     * Kiểm tra xem HS có xung đột thời gian với Lớp mới hay không
     * @param int $studentId
     * @param int $newClassId
     * @param string $enrolledAt
     * @return bool
     */
    public function checkStudentHasConflict(int $studentId, int $newClassId, string $enrolledAt): bool
    {
        return $this->model->newQuery()
            ->where('date', '>=', $enrolledAt)
            ->where('status', '!=', ScheduleStatus::Cancelled)
            // Lớp HS đang học
            ->whereHas('class.enrollments', function ($query) use ($studentId) {
                $query->where('student_id', $studentId)
                    ->whereNull('left_at');
            })
            // 2. Trùng giờ với Lớp mới
            ->whereExists(function ($query) use ($newClassId) {
                $query->select(DB::raw(1))
                    ->from('class_schedule_templates')
                    ->where('class_id', $newClassId)
                    ->whereRaw('class_schedule_templates.day_of_week = EXTRACT(ISODOW FROM schedule_instances.date)')
                    ->whereColumn('class_schedule_templates.start_time', '<', 'schedule_instances.end_time')
                    ->whereColumn('class_schedule_templates.end_time', '>', 'schedule_instances.start_time');
            })
            ->exists();
    }

    /**
     * Cập nhật GV cho các lịch sắp đến
     * @param int $classId
     * @param int $teacherId
     * @return int
     */
    public function updateTeacherForFutureSchedules(int $classId, int $teacherId): int
    {
        return $this->query()
            ->where('class_id', $classId)
            ->where('date', '>=', now()->toDateString())
            ->where('status', ScheduleStatus::Upcoming->value)
            ->update([
                'teacher_id' => $teacherId
            ]);
    }

    /**
     * Hủy các lịch sắp đến của lớp
     * @param int $classId
     * @return int
     */
    public function cancelFutureSchedulesByClassId(int $classId): int
    {
        return $this->query()
            ->where('class_id', $classId)
            ->where('date', '>=', now()->toDateString())
            ->where('status', ScheduleStatus::Upcoming->value)
            ->update([
                'status' => ScheduleStatus::Cancelled->value
            ]);
    }

    /**
     * Tìm xung đột PHÒNG hoặc GIÁO VIÊN trên các buổi học thực tế (Instances)
     * @param int $roomId  ID phòng học
     * @param array $daysOfWeek  Thứ học (mảng số nguyên theo DayOfWeek::class)
     * @param string $startTime  Giờ bắt đầu
     * @param string $endTime  Giờ kết thúc
     * @param string $startDate  Ngày bắt đầu kiểm tra
     * @param string|null $endDate  Ngày kết thúc kiểm tra
     * @param int|null $excludeInstanceId ID buổi học thực tế không kiểm tra
     */
    public function findConflicts(
        int $roomId,
        int $teacherId,
        array $daysOfWeek,
        string $startTime,
        string $endTime,
        string $startDate,
        ?string $endDate = null,
        int|null $excludeInstanceId = null
    ) {
        $placeholders = implode(',', array_fill(0, count($daysOfWeek), '?'));

        return $this->query()
            // 1. Nhóm điều kiện OR: Trùng phòng HOẶC trùng giáo viên
            ->where(function ($query) use ($roomId, $teacherId) {
                $query->where('room_id', $roomId)
                    ->orWhere('teacher_id', $teacherId);
            })

            // 2. Loại trừ ID hiện tại (khi chỉnh sửa)
            ->when($excludeInstanceId, fn ($query) => $query->where('id', '!=', $excludeInstanceId))

            // 3. Lọc theo Thứ (Postgres ISODOW: 1=Mon, 7=Sun)
            ->whereRaw("EXTRACT(ISODOW FROM date) IN ($placeholders)", $daysOfWeek)

            // 4. Nằm trong khoảng ngày xét duyệt
            ->where('date', '>=', $startDate)
            ->when($endDate, fn ($query) => $query->where('date', '<=', $endDate))

            // 5. Giao thoa thời gian (Time Overlap logic)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)

            // 6. Chỉ xét các buổi có hiệu lực
            ->whereIn('status', [
                ScheduleStatus::Upcoming->value,
                ScheduleStatus::Completed->value
            ])
            ->first();
    }

    /**
     * Lấy query danh sách các buổi học thực tế (Instances)
     * @param Builder $query
     * @return Builder
     */
    public function getListingQuery(Builder $query): Builder
    {
        return $query
            ->select('schedule_instances.*') // Đảm bảo lấy các cột của bảng chính
            ->with([
                'class.subject', // Gộp 'class' và 'class.subject'
                'teacher',
                'room',
                'attendanceSession' => function ($q) {
                    $q->withCount([
                        'attendanceRecords as present_count' => function ($recordQuery) {
                            $recordQuery->whereIn('status', [
                                AttendanceStatus::Present->value,
                                AttendanceStatus::Late->value,
                            ]);
                        }
                    ]);
                }
            ])
            ->addSelect([
                'active_students_count' => ClassEnrollment::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('class_enrollments.class_id', 'schedule_instances.class_id')
                    // Tối ưu Postgres: Tránh ép kiểu ::date trên cột enrolled_at để tận dụng Index
                    ->whereRaw('class_enrollments.enrolled_at < (schedule_instances.date + INTERVAL \'1 day\')')
                    ->where(function ($subQuery) {
                        $subQuery->whereNull('class_enrollments.left_at')
                            // left_at đã là kiểu DATE (theo database.md), có thể dùng whereColumn thay vì whereRaw
                            ->orWhereColumn('class_enrollments.left_at', '>=', 'schedule_instances.date');
                    })
            ]);
    }

    /**
     * Lọc query theo các trường lọc
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function setFilters(Builder $query, array $filters = []): Builder
    {
        if (!empty($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }
        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }
        if (!empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }
        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }
        if (!empty($filters['schedule_type'])) {
            $query->where('schedule_type', $filters['schedule_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['subject_id'])) {
            $query->whereHas('class', function ($classQuery) use ($filters) {
                $classQuery->where('subject_id', $filters['subject_id']);
            });
        }
        if (!empty($filters['grade_level'])) {
            $query->whereHas('class', function ($classQuery) use ($filters) {
                $classQuery->where('grade_level', $filters['grade_level']);
            });
        }
        if ($filters['active_classes_only'] ?? false) {
            $query->whereHas('class', function ($classQuery) use ($filters) {
                $classQuery->where('status', ClassStatus::Active);
            });
        }
        return $query;
    }

    public function countBillableSessionsByClassInMonth(int $classId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) $this->query()
            ->from('schedule_instances as si')
            ->join('attendance_sessions as as_sess', 'as_sess.schedule_instance_id', '=', 'si.id')
            ->where('si.class_id', $classId)
            ->whereBetween('si.date', [$from->toDateString(), $to->toDateString()])
            ->where('as_sess.status', AttendanceSessionStatus::Locked->value)
            ->where('si.status', '!=', ScheduleStatus::Cancelled->value)
            ->selectRaw('COUNT(DISTINCT si.id) as total_sessions')
            ->value('total_sessions');
    }
}
