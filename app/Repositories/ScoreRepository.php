<?php

namespace App\Repositories;

use App\Core\Repository\BaseRepository;
use App\Models\Score;

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
}
