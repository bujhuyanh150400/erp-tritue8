<?php

namespace App\Repositories;

use App\Constants\AttendanceStatus;
use App\Constants\AttendanceSessionStatus;
use App\Core\Repository\BaseRepository;
use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use App\Models\ClassEnrollment;
use App\Models\Score;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

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

    public function getStudentMonthlyOverview(int $studentId, string $month): array
    {
        [$from, $to] = $this->resolveMonthRange($month);

        $baseQuery = $this->query()
            ->where('student_id', $studentId)
            ->whereHas('session', function ($query) use ($from, $to) {
                $query->whereBetween('session_date', [$from, $to]);
            });

        $totalSessions = (clone $baseQuery)->count();

        $totalPresent = (clone $baseQuery)
            ->whereIn('status', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
            ])
            ->count();

        $totalAbsent = (clone $baseQuery)
            ->whereIn('status', [
                AttendanceStatus::AbsentExcused->value,
                AttendanceStatus::Absent->value,
            ])
            ->count();

        $averageScore = (float) Score::query()
            ->whereHas('attendanceRecord', function ($query) use ($studentId, $from, $to) {
                $query->where('student_id', $studentId)
                    ->whereHas('session', function ($sessionQuery) use ($from, $to) {
                        $sessionQuery->whereBetween('session_date', [$from, $to]);
                    });
            })
            ->avg('score');

        return [
            'total_sessions' => $totalSessions,
            'total_present' => $totalPresent,
            'total_absent' => $totalAbsent,
            'participation_rate' => $totalSessions > 0 ? round(($totalPresent / $totalSessions) * 100, 2) : 0,
            'average_score' => round($averageScore, 2),
        ];
    }

    public function getStudentMonthlyHistoryQuery(int $studentId, string $month): Builder
    {
        [$from, $to] = $this->resolveMonthRange($month);

        return $this->query()
            ->where('student_id', $studentId)
            ->whereHas('session', function ($query) use ($from, $to) {
                $query->whereBetween('session_date', [$from, $to]);
            })
            ->with([
                'session.class:id,name',
                'session.scheduleInstance:id,class_id,start_time,end_time,schedule_type',
                'session.rewardPoints' => function ($query) use ($studentId) {
                    $query->where('student_id', $studentId)
                        ->select('id', 'student_id', 'session_id', 'amount');
                },
                'scores' => function ($query) {
                    $query->select('id', 'attendance_record_id', 'exam_slot', 'exam_name', 'score', 'max_score', 'note')
                        ->orderBy('exam_slot', 'asc');
                },
            ])
            ->orderByDesc(
                AttendanceSession::query()
                    ->select('session_date')
                    ->whereColumn('attendance_sessions.id', 'attendance_records.session_id')
            );
    }

    public function getStudentMonthlyClassNames(int $studentId, string $month): Collection
    {
        [$from, $to] = $this->resolveMonthRange($month);

        return ClassEnrollment::query()
            ->where('student_id', $studentId)
            ->whereDate('enrolled_at', '<=', $to)
            ->where(function ($query) use ($from) {
                $query->whereNull('left_at')
                    ->orWhereDate('left_at', '>=', $from);
            })
            ->with('class')
            ->get()
            ->pluck('class.name')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function resolveMonthRange(string $month): array
    {
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $month);
        } catch (Throwable) {
            $monthDate = now();
        }

        return [
            $monthDate->copy()->startOfMonth()->toDateString(),
            $monthDate->copy()->endOfMonth()->toDateString(),
        ];
    }
}
