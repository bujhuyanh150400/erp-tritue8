<?php

namespace App\Repositories;

use App\Constants\ScheduleStatus;
use App\Core\Repository\BaseRepository;
use App\Models\ScheduleInstance;
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
}
