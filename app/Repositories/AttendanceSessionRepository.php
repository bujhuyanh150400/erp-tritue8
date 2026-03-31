<?php

namespace App\Repositories;

use App\Core\Repository\BaseRepository;
use App\Models\AttendanceSession;

class AttendanceSessionRepository extends BaseRepository
{
    public function getModel()
    {
        return AttendanceSession::class;
    }
}
