<?php

namespace App\Repositories;

use App\Constants\AttendanceSessionStatus;
use App\Constants\ScheduleStatus;
use App\Core\Repository\BaseRepository;
use App\Models\AttendanceRecord;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            ->leftJoin('schedule_instance_participants as sip', function ($join) use ($studentId) {
                $join->on('sip.schedule_instance_id', '=', 'si.id')
                    ->where('sip.student_id', '=', $studentId);
            })
            ->select([
                'ar.id',
                'ar.status',
                'as_sess.session_date',
                'si.id as schedule_instance_id',
                'si.class_id as instance_class_id',
                'si.teacher_id as instance_teacher_id',
                'si.schedule_type',
                'si.fee_type',
                'si.custom_fee_per_session',
                'sip.fee_amount as participant_fee_amount',
            ])
            ->where('ar.student_id', $studentId)
            ->where('ar.is_fee_counted', true)
            ->whereBetween('as_sess.session_date', [$from->toDateString(), $to->toDateString()])
            ->where('as_sess.status', AttendanceSessionStatus::Locked->value)
            ->where('si.status', '!=', ScheduleStatus::Cancelled->value)
            ->where(function ($query) use ($classId) {
                $query->where('si.class_id', $classId)
                    ->orWhere(function ($specialQuery) use ($classId) {
                        $specialQuery->whereNull('si.class_id')
                            ->whereNotNull('sip.id')
                            ->whereExists(function ($classQuery) use ($classId) {
                                $classQuery->select(DB::raw(1))
                                    ->from('classes')
                                    ->where('classes.id', $classId)
                                    ->whereColumn('classes.teacher_id', 'si.teacher_id');
                            })
                            ->whereExists(function ($enrollmentQuery) use ($classId) {
                                $enrollmentQuery->select(DB::raw(1))
                                    ->from('class_enrollments')
                                    ->whereColumn('class_enrollments.student_id', 'ar.student_id')
                                    ->where('class_enrollments.class_id', $classId)
                                    ->whereRaw('class_enrollments.enrolled_at < (as_sess.session_date + INTERVAL \'1 day\')')
                                    ->where(function ($activeQuery) {
                                        $activeQuery->whereNull('class_enrollments.left_at')
                                            ->orWhereColumn('class_enrollments.left_at', '>=', 'as_sess.session_date');
                                    });
                            });
                    });
            })
            ->orderBy('as_sess.session_date')
            ->get();
    }
}
