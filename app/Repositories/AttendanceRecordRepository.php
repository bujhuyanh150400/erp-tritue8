<?php

namespace App\Repositories;

use App\Core\Repository\BaseRepository;
use App\Models\AttendanceRecord;

class AttendanceRecordRepository extends BaseRepository
{
    public function getModel()
    {
        return AttendanceRecord::class;
    }
}
