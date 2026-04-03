<?php

namespace App\Repositories;

use App\Core\Repository\BaseRepository;
use App\Models\Score;
use Carbon\CarbonInterface;

class ScoreRepository extends BaseRepository
{
    public function getModel(): string
    {
        return Score::class;
    }


    /**
     * Xóa tất cả điểm số liên quan đến điểm danh
     * @param int $attendanceRecordId
     * @return mixed
     */
    public function deleteScoresByAttendanceRecord(int $attendanceRecordId)
    {
        return $this->model->newQuery()->where('attendance_record_id', $attendanceRecordId)->delete();
    }

    public function getTeacherAverageScoreInRange(int $teacherId, CarbonInterface $from, CarbonInterface $to): float
    {
        return round((float) ($this->query()
            ->from('scores as sc')
            ->join('attendance_records as ar', 'sc.attendance_record_id', '=', 'ar.id')
            ->join('attendance_sessions as sess', 'ar.session_id', '=', 'sess.id')
            ->where('sess.teacher_id', $teacherId)
            ->whereBetween('sess.session_date', [$from, $to])
            ->avg('sc.score') ?? 0), 2);
    }
}
