<?php

namespace App\Repositories;

use App\Constants\ClassStatus;
use App\Constants\DayOfWeek;
use App\Constants\ScheduleStatus;
use App\Core\Repository\BaseRepository;
use App\Models\ScheduleInstance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleInstanceRepository extends BaseRepository
{
    public function getModel(): string
    {
        return ScheduleInstance::class;
    }

    public function createScheduleInstance(array $data)
    {
        return $this->model->create($data);
    }

    public function updateRoom(int $id, int $roomId): int
    {
        return $this->query()
            ->where('id', $id)
            ->update([
                'room_id' => $roomId
            ]);
    }

    public function cancelSchedule(int $id, array $data): int
    {
        return $this->query()
            ->where('id', $id)
            ->update([
                'status' => ScheduleStatus::Cancelled->value,
                'note' => $data['reason'] ?? null,
            ]);
    }

    public function updateAttendanceFee(int $scheduleInstanceId): void
    {
        DB::table('attendance_records')
            ->whereIn('session_id', function ($q) use ($scheduleInstanceId) {
                $q->select('id')
                    ->from('attendance_sessions')
                    ->where('schedule_instance_id', $scheduleInstanceId);
            })
            ->update([
                'is_fee_counted' => false
            ]);
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
     * Kiểm tra trùng GIÁO VIÊN trên các buổi học thực tế (Instances)
     * @param int $teacherId  ID GV
     * @param array $daysOfWeek  Thứ học (mảng số nguyên theo DayOfWeek::class)
     * @param string $startTime  Giờ bắt đầu
     * @param string $endTime  Giờ kết thúc
     * @param string $startDate  Ngày bắt đầu kiểm tra
     * @param string|null $endDate  Ngày kết thúc kiểm tra
     */
    public function findTeacherConflicts(int $teacherId, array $daysOfWeek, string $startTime, string $endTime, string $startDate, ?string $endDate)
    {
        $placeholders = implode(',', array_fill(0, count($daysOfWeek), '?'));
        return $this->model->newQuery()
            ->where('teacher_id', $teacherId)
            ->whereRaw("EXTRACT(ISODOW FROM date) IN ($placeholders)", $daysOfWeek)
            ->where('date', '>=', $startDate)
            ->when($endDate, fn ($query) => $query->where('date', '<=', $endDate))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->whereIn('status', [ScheduleStatus::Upcoming->value, ScheduleStatus::Completed->value])
            ->with('class')
            ->first();
    }

    /**
     * Kiểm tra trùng PHÒNG trên các buổi học thực tế (Instances)
     * @param int $roomId  ID phòng học
     * @param array $daysOfWeek  Thứ học (mảng số nguyên theo DayOfWeek::class)
     * @param string $startTime  Giờ bắt đầu
     * @param string $endTime  Giờ kết thúc
     * @param string $startDate  Ngày bắt đầu kiểm tra
     * @param string|null $endDate  Ngày kết thúc kiểm tra
     */
    public function findRoomConflicts(int $roomId, array $daysOfWeek, string $startTime, string $endTime, string $startDate, ?string $endDate)
    {
        $placeholders = implode(',', array_fill(0, count($daysOfWeek), '?'));
        return $this->query()
            ->where('room_id', $roomId)
            // Lọc ra các buổi có thứ trùng khớp (Postgres: 1=Mon, 7=Sun)
            ->whereRaw("EXTRACT(ISODOW FROM date) IN ($placeholders)", $daysOfWeek)
            // Nằm trong khoảng ngày xét duyệt
            ->where('date', '>=', $startDate)
            ->when($endDate, fn ($query) => $query->where('date', '<=', $endDate))
            // Giao thoa thời gian
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            // Chỉ quan tâm các buổi chưa bị hủy
            ->whereIn('status', [ScheduleStatus::Upcoming->value, ScheduleStatus::Completed->value])
            ->with('class')
            ->first();
    }

    /**
     * Lấy lịch học cho calendar
     * @param Carbon $start
     * @param Carbon $end
     * @param array $filters
     * @return Collection
     */
    public function getScheduleInstancesForCalendar(Carbon $start, Carbon $end, array $filters = []): Collection    {
        $query = $this->model->newQuery()
            ->with(['class', 'teacher', 'room', 'class.subject'])
            ->withCount('classEnrollments as si_so')
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<=', $end->toDateString());

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
        return $query->get();
    }
}
