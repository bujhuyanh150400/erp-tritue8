<?php

namespace App\Repositories;

use App\Constants\AttendanceSessionStatus;
use App\Core\Repository\BaseRepository;
use App\Models\AttendanceRecord;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AttendanceRecordRepository extends BaseRepository
{
    public function getModel()
    {
        return AttendanceRecord::class;
    }

    public function getFeeCountedAttendancesByStudentClassInMonth(
        int $studentId,
        int $classId,
        CarbonInterface $from,
        CarbonInterface $to
    ): Collection {
        return $this->query()
            ->from('attendance_records as ar')
            ->join('attendance_sessions as as_sess', 'ar.session_id', '=', 'as_sess.id')
            ->join('schedule_instances as si', 'as_sess.schedule_instance_id', '=', 'si.id')
            ->select([
                'ar.id',
                'as_sess.session_date',
                'si.id as schedule_instance_id',
            ])
            ->where('ar.student_id', $studentId)
            ->where('si.class_id', $classId)
            ->where('ar.is_fee_counted', true)
            ->whereBetween('as_sess.session_date', [$from->toDateString(), $to->toDateString()])
            ->where('as_sess.status', AttendanceSessionStatus::Locked->value)
            ->where('si.status', '!=', \App\Constants\ScheduleStatus::Cancelled->value)
            ->orderBy('as_sess.session_date')
            ->get();
    }
}
