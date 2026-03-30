<?php

namespace App\Repositories;

use App\Constants\AttendanceSessionStatus;
use App\Constants\AttendanceStatus;
use App\Constants\ClassStatus;
use App\Constants\ReportStatus;
use App\Constants\ScheduleStatus;
use App\Core\Interfaces\Paginate;
use App\Core\Repository\BaseRepository;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class TeacherRepository extends BaseRepository implements Paginate
{
    public function getModel(): string
    {
        return Teacher::class;
    }

    public function paginate(
        array $filters = [],
        int $perPage = 10,
        int $page = 1,
        string $orderBy = 'id',
        string $orderDirection = 'desc'
    ): LengthAwarePaginator {

        $query = $this->getQuery()
            ->with(['user' => function ($query) {
                $query->select('id', 'username', 'is_active', 'role');
            }]);

        $query = $this->filters($query, $filters);

        $query = $this->sort($query, $orderBy, $orderDirection);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function filters($query, array $filters = []): Builder
    {
        if (!empty($filters['keyword']) && trim($filters['keyword']) !== '') {

            $keyword = trim($filters['keyword']);

            $query->where(function ($q) use ($keyword) {

                $q->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhereHas('user', function ($userQuery) use ($keyword) {

                        if (is_numeric($keyword)) {
                            $userQuery->where('id', (int)$keyword);
                        }

                        $userQuery->orWhere('username', 'like', "%{$keyword}%");
                    });

            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['phone'])) {
            $query->where('phone', 'like', '%' . $filters['phone'] . '%');
        }

        if (!empty($filters['joined_at_from'])) {
            $query->where('joined_at', '>=', $filters['joined_at_from']);
        }

        if (!empty($filters['joined_at_to'])) {
            $query->where('joined_at', '<=', $filters['joined_at_to']);
        }

        return $query;
    }

    public function sort(Builder $query, string $orderBy, string $orderDirection): Builder
    {
        return $query->orderBy($orderBy, $orderDirection);
    }

    /**
     * Tìm giáo viên theo id
     */
    public function findTeacherByUserId(int $userId): ?Teacher
    {
        return $this->model->query()
            ->with('user')
            ->where('user_id', $userId)
            ->first();
    }

    public function getListingQuery(Builder $query): Builder
    {
        return $query
            ->leftJoin('users', 'users.id', '=', 'teachers.user_id')

            ->leftJoin('classes', function ($join) {
                $join->on('classes.teacher_id', '=', 'teachers.id')
                    ->where('classes.status', 1);
            })

            ->leftJoin('subjects', 'subjects.id', '=', 'classes.subject_id')

            ->select([
                'teachers.*',
                'users.is_active',

                \DB::raw("STRING_AGG(DISTINCT subjects.name, ', ') as subjects"),
                \DB::raw("COUNT(DISTINCT classes.id) as total_classes"),
            ])

            ->groupBy('teachers.id', 'users.is_active');
    }

    public function setFilters(Builder $query, array $data): Builder
    {
        if (!empty($data['keyword'])) {
            $keyword = trim($data['keyword']);

            $query->where(function ($q) use ($keyword) {
                $q->where('teachers.full_name', 'ILIKE', "%{$keyword}%")
                    ->orWhere('teachers.phone', 'ILIKE', "%{$keyword}%")
                    ->orWhereRaw('CAST(users.id AS TEXT) ILIKE ?', ["%{$keyword}%"]);
            });
        }

        if (!empty($data['status'])) {
            $query->where('teachers.status', $data['status']);
        }

        if (!empty($data['is_active']) || $data['is_active'] === '0') {
            $query->where('users.is_active', (int)$data['is_active']);
        }

        if (!empty($data['subject_id'])) {
            $query->whereExists(function ($sub) use ($data) {
                $sub->selectRaw(1)
                    ->from('classes')
                    ->whereColumn('classes.teacher_id', 'teachers.id')
                    ->where('classes.subject_id', $data['subject_id'])
                    ->where('classes.status', 1);
            });
        }

        return $query;
    }

    /**
     * Lấy các chỉ số KPI của giáo viên theo tháng
     */
    public function getKpiStats(int $teacherId, string $month): array
    {
        $monthCarbon = Carbon::parse($month);
        $monthStart = $monthCarbon->copy()->startOfMonth();
        $monthEnd = $monthCarbon->copy()->endOfMonth();

        // Deadline quy định: Ngày 5 của tháng kế tiếp
        $deadline = $monthCarbon->copy()->addMonth()->startOfMonth()->addDays(4)->endOfDay();

        // 1. Tổng số lớp đang dạy (Active)
        $totalActiveClasses = DB::table('classes')
            ->where('teacher_id', $teacherId)
            ->where('status', ClassStatus::Active->value)
            ->count();

        // 2. Tổng buổi dạy tháng (Completed)
        $totalSessions = DB::table('attendance_sessions')
            ->where('teacher_id', $teacherId)
            ->where('status', AttendanceSessionStatus::Completed->value)
            ->whereBetween('session_date', [$monthStart, $monthEnd])
            ->count();

        // 3. Tỷ lệ chuyên cần TB các lớp
        $attendanceStats = DB::table('attendance_records as ar')
            ->join('attendance_sessions as sess', 'ar.session_id', '=', 'sess.id')
            ->where('sess.teacher_id', $teacherId)
            ->whereBetween('sess.session_date', [$monthStart, $monthEnd])
            ->selectRaw('
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE ar.status IN (?, ?)) as present_count
            ', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->first();

        $attendanceRate = $attendanceStats->total > 0
            ? round(($attendanceStats->present_count / $attendanceStats->total) * 100, 1)
            : 0;

        // 4. Tỷ lệ nộp báo cáo đúng hạn & Tỷ lệ báo cáo được duyệt ngay
        $reportStats = DB::table('monthly_reports')
            ->where('teacher_id', $teacherId)
            ->where('month', $month)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status != ?) as submitted_count,
                COUNT(*) FILTER (WHERE status != ? AND submitted_at <= ?) as on_time_count,
                COUNT(*) FILTER (WHERE status = ?) as approved_count,
                COUNT(*) FILTER (WHERE status IN (?, ?)) as reviewed_count,
                COUNT(*) FILTER (WHERE status = ?) as draft_count
            ', [
                ReportStatus::Pending->value, // status != Pending => Đã nộp
                ReportStatus::Pending->value, $deadline, // Đúng hạn
                ReportStatus::Approved->value, // Duyệt ngay (Approved)
                ReportStatus::Approved->value, ReportStatus::Rejected->value, // Đã review
                ReportStatus::Pending->value // Draft
            ])
            ->first();

        $submissionRate = $reportStats->total > 0
            ? round(($reportStats->on_time_count / $reportStats->total) * 100, 1)
            : 0;

        $approvalRate = $reportStats->reviewed_count > 0
            ? round(($reportStats->approved_count / $reportStats->reviewed_count) * 100, 1)
            : 0;

        // 5. Điểm TB toàn bộ lớp
        $avgScore = DB::table('scores as sc')
            ->join('attendance_records as ar', 'sc.attendance_record_id', '=', 'ar.id')
            ->join('attendance_sessions as sess', 'ar.session_id', '=', 'sess.id')
            ->where('sess.teacher_id', $teacherId)
            ->whereBetween('sess.session_date', [$monthStart, $monthEnd])
            ->avg('sc.score');

        return [
            'total_active_classes' => (int) $totalActiveClasses,
            'total_sessions' => (int) $totalSessions,
            'attendance_rate' => (float) $attendanceRate,
            'submission_rate' => (float) $submissionRate, // Tỷ lệ nộp đúng hạn
            'approval_rate' => (float) $approvalRate, // Tỷ lệ duyệt ngay
            'avg_score' => round($avgScore ?? 0, 2),
            'draft_count' => (int) $reportStats->draft_count,
            'is_past_deadline' => now()->greaterThan($deadline),
            'has_reports' => $reportStats->total > 0,
            'total_reports' => (int) $reportStats->total,
        ];
    }

    /**
     * Lấy dữ liệu chuyên cần theo từng lớp của giáo viên trong tháng
     */
    public function getAttendanceStatsByClass(int $teacherId, string $month): array
    {
        $monthCarbon = \Illuminate\Support\Carbon::parse($month);
        $monthStart = $monthCarbon->copy()->startOfMonth();
        $monthEnd = $monthCarbon->copy()->endOfMonth();

        $classes = DB::table('classes')
            ->where('teacher_id', $teacherId)
            ->where('status', ClassStatus::Active->value)
            ->select('id', 'name')
            ->get();

        $result = [];
        foreach ($classes as $class) {
            $stats = DB::table('attendance_records as ar')
                ->join('attendance_sessions as sess', 'ar.session_id', '=', 'sess.id')
                ->where('sess.class_id', $class->id)
                ->whereBetween('sess.session_date', [$monthStart, $monthEnd])
                ->selectRaw('
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE ar.status IN (?, ?)) as present_count
                ', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
                ->first();

            $rate = $stats->total > 0
                ? round(($stats->present_count / $stats->total) * 100, 1)
                : 0;

            $result[] = [
                'name' => $class->name,
                'rate' => (float) $rate,
            ];
        }

        return $result;
    }
}
