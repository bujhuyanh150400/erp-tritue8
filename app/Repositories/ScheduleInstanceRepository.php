<?php

namespace App\Repositories;

use App\Constants\DayOfWeek;
use App\Constants\ScheduleStatus;
use App\Core\Repository\BaseRepository;
use App\Models\ScheduleInstance;
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

    public function getTeacherScheduleConflicts(int $teacherId, int $classId): Collection
    {
        return $this->model->newQuery()
            ->from('schedule_instances as si')
            ->join('classes as c', 'c.id', '=', 'si.class_id')
            ->where('si.teacher_id', $teacherId)
            ->where('si.class_id', '!=', $classId)
            ->where('si.date', '>=', now()->toDateString())
            ->where('si.status', '!=', ScheduleStatus::Cancelled->value)
            ->whereExists(function ($query) use ($classId) {
                $query->select(DB::raw(1))
                    ->from('schedule_instances as si2')
                    ->where('si2.class_id', $classId)
                    ->where('si2.status', '!=', ScheduleStatus::Cancelled->value)
                    ->whereColumn('si2.date', 'si.date')
                    ->whereColumn('si2.start_time', '<', 'si.end_time')
                    ->whereColumn('si2.end_time', '>', 'si.start_time');
            })
            ->select([
                'si.date',
                'si.start_time',
                'si.end_time',
                'c.name as class_name'
            ])
            ->get();
    }

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
     * Kiểm tra xung đột phòng khi tạo/cập nhật Template
     * @param int $roomId ID của phòng
     * @param DayOfWeek $dayOfWeek Thứ trong tuần (0: Chủ nhật, 1: Thứ 2, ..., 6: Thứ 7)
     * @param string $startTime Giờ bắt đầu (HH:mm)
     * @param string $endTime Giờ kết thúc (HH:mm)
     * @param string $startDate Ngày bắt đầu (YYYY-MM-DD)
     * @param int|null $excludeTemplateId ID của Template cần loại trừ (nếu có)
     * @return Collection
     */
    public function checkRoomConflictForTemplate(int $roomId, DayOfWeek $dayOfWeek, string $startTime, string $endTime, string $startDate, ?int $excludeTemplateId = null): Collection
    {
        $query = $this->model->newQuery()
            ->from('schedule_instances as si')
            ->join('classes as c', 'c.id', '=', 'si.class_id')
            ->where('si.room_id', $roomId)
            ->whereRaw('EXTRACT(ISODOW FROM si.date) = ?', [$dayOfWeek->value])
            ->where('si.date', '>=', $startDate)
            ->where('si.status', '!=', ScheduleStatus::Cancelled->value)
            ->where('si.start_time', '<', $endTime)
            ->where('si.end_time', '>', $startTime);

        if ($excludeTemplateId) {
            $query->where(function($q) use ($excludeTemplateId) {
                $q->whereNull('si.template_id')
                  ->orWhere('si.template_id', '!=', $excludeTemplateId);
            });
        }

        return $query->select([
                'si.date',
                'si.start_time',
                'si.end_time',
                'c.name as class_name'
            ])
            ->get();
    }

    /**
     * Kiểm tra xung đột giáo viên khi tạo/cập nhật Template
     * @param int $teacherId ID của giáo viên
     * @param DayOfWeek $dayOfWeek Thứ trong tuần (0: Chủ nhật, 1: Thứ 2, ..., 6: Thứ 7)
     * @param string $startTime Giờ bắt đầu (HH:mm)
     * @param string $endTime Giờ kết thúc (HH:mm)
     * @param string $startDate Ngày bắt đầu (YYYY-MM-DD)
     * @param int|null $excludeTemplateId ID của Template cần loại trừ (nếu có)
     * @return Collection
     */
    public function checkTeacherConflictForTemplate(int $teacherId, DayOfWeek $dayOfWeek, string $startTime, string $endTime, string $startDate, ?int $excludeTemplateId = null): Collection
    {
        $query = $this->model->newQuery()
            ->from('schedule_instances as si')
            ->join('classes as c', 'c.id', '=', 'si.class_id')
            ->where('si.teacher_id', $teacherId)
            ->whereRaw('EXTRACT(ISODOW FROM si.date) = ?', [$dayOfWeek->value])
            ->where('si.date', '>=', $startDate)
            ->where('si.status', '!=', ScheduleStatus::Cancelled->value)
            ->where('si.start_time', '<', $endTime)
            ->where('si.end_time', '>', $startTime);

        if ($excludeTemplateId) {
            $query->where(function($q) use ($excludeTemplateId) {
                $q->whereNull('si.template_id')
                  ->orWhere('si.template_id', '!=', $excludeTemplateId);
            });
        }

        return $query->select([
                'si.date',
                'si.start_time',
                'si.end_time',
                'c.name as class_name'
            ])
            ->get();
    }


    public function findRoomConflict($roomId, $date, $startTime, $endTime, $ignoreId = null)
    {
        return DB::table('schedule_instances as si2')
            ->join('classes', 'si2.class_id', '=', 'classes.id')
            ->where('si2.room_id', $roomId)
            ->where('si2.date', $date)
            ->where('si2.status', '!=', ScheduleStatus::Cancelled->value)
            ->when($ignoreId, fn ($q) => $q->where('si2.id', '!=', $ignoreId))
            ->where('si2.start_time', '<', $endTime)
            ->where('si2.end_time', '>', $startTime)
            ->select('classes.name', 'si2.start_time', 'si2.end_time')
            ->first();
    }

    public function findTeacherConflict($teacherId, $date, $startTime, $endTime, $ignoreId = null)
    {
        return DB::table('schedule_instances as si2')
            ->join('classes', 'si2.class_id', '=', 'classes.id')
            ->where('si2.teacher_id', $teacherId)
            ->where('si2.date', $date)
            ->where('si2.status', '!=', ScheduleStatus::Cancelled->value)
            ->when($ignoreId, fn ($q) => $q->where('si2.id', '!=', $ignoreId))
            ->where('si2.start_time', '<', $endTime)
            ->where('si2.end_time', '>', $startTime)
            ->select('classes.name', 'si2.start_time', 'si2.end_time')
            ->first();
    }

    public function getSalarySnapshot(int $teacherId, int $classId, string $date): ?float
    {
        return \App\Models\TeacherSalaryConfig::query()
            ->where('teacher_id', $teacherId)
            ->where('class_id', $classId)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->orderBy('effective_from', 'desc')
            ->value('salary_per_session');
    }
}
