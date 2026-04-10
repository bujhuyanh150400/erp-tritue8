<?php

namespace App\Repositories;

use App\Constants\AttendanceSessionStatus;
use App\Constants\AttendanceStatus;
use App\Core\Repository\BaseRepository;
use App\Models\AttendanceSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AttendanceSessionRepository extends BaseRepository
{
    public function getModel()
    {
        return AttendanceSession::class;
    }

    public function countCompletedByTeacherInRange(int $teacherId, CarbonInterface $from, CarbonInterface $to): int
    {
        return $this->query()
            ->where('teacher_id', $teacherId)
            ->where('status', AttendanceSessionStatus::Completed->value)
            ->whereBetween('session_date', [$from, $to])
            ->count();
    }

    public function getTeacherAttendanceStatsInRange(int $teacherId, CarbonInterface $from, CarbonInterface $to): array
    {
        $stats = $this->query()
            ->from('attendance_sessions as sess')
            ->join('attendance_records as ar', 'ar.session_id', '=', 'sess.id')
            ->where('sess.teacher_id', $teacherId)
            ->whereBetween('sess.session_date', [$from, $to])
            ->selectRaw('
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE ar.status IN (?, ?)) as present_count
            ', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'present_count' => (int) ($stats->present_count ?? 0),
        ];
    }

    public function getLockedClassIdsInMonth(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->query()
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', AttendanceSessionStatus::Locked->value)
            ->distinct()
            ->orderBy('class_id')
            ->pluck('class_id');
    }
}
