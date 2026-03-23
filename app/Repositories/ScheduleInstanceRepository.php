<?php

namespace App\Repositories;

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
                    ->whereRaw('class_schedule_templates.day_of_week = EXTRACT(DOW FROM schedule_instances.date)')
                    ->whereColumn('class_schedule_templates.start_time', '<', 'schedule_instances.end_time')
                    ->whereColumn('class_schedule_templates.end_time', '>', 'schedule_instances.start_time');
            })
            ->exists();
    }

    public function getTeacherScheduleConflicts(int $teacherId, int $classId): Collection
    {
        return DB::table('schedule_instances as si')
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
}
