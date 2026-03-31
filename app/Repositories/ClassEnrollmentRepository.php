<?php

namespace App\Repositories;

use App\Constants\AttendanceStatus;
use App\Core\Repository\BaseRepository;
use App\Models\ClassEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClassEnrollmentRepository extends BaseRepository
{
    public function getModel()
    {
        return ClassEnrollment::class;
    }

    /**
     * Lấy danh sách học sinh trong lớp với thống kê buổi có mặt và buổi nghỉ
     * @param int $classId
     * @return Builder
     */
    public function getEnrollmentsWithAttendanceStatsQuery(int $classId): Builder
    {
        // 1. Sub-query đếm buổi CÓ MẶT
        $presentCountQuery = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_records.session_id', '=', 'attendance_sessions.id')
            ->whereColumn('attendance_records.student_id', 'class_enrollments.student_id')
            ->where('attendance_sessions.class_id', $classId)
            ->whereIn('attendance_records.status', [AttendanceStatus::Present, AttendanceStatus::Late])
            ->selectRaw('count(*)');

        // 2. Sub-query đếm buổi NGHỈ
        $absentCountQuery = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_records.session_id', '=', 'attendance_sessions.id')
            ->whereColumn('attendance_records.student_id', 'class_enrollments.student_id')
            ->where('attendance_sessions.class_id', $classId)
            ->whereIn('attendance_records.status', [AttendanceStatus::AbsentExcused, AttendanceStatus::Absent])
            ->selectRaw('count(*)');

        return $this->model->newQuery()
            ->with('student') // Eager load thông tin HS để tránh N+1
            ->where('class_id', $classId)
            ->whereNull('left_at') // Chỉ lấy người đang học
            ->addSelect([
                'class_enrollments.*',
                'total_present' => $presentCountQuery, // Số buổi có mặt
                'total_absent'   => $absentCountQuery, // Số buổi nghỉ
            ]);
    }
    /**
     * Lấy sĩ số lớp hiện tại
     * @param int $classId
     * @return int
     */
    public function getClassEnrollment(
        int $classId,
    ): int
    {
        return $this->model->newQuery()
            ->where('class_id', $classId)
            ->whereNull('left_at')
            ->count();
    }
    /**
     * Kiểm tra sinh viên đã đăng ký lớp học này chưa
     * @param int $classId
     * @param int $studentId
     * @return bool
     */
    public function checkStudentIsEnrolledInClass(
        int $classId,
        int $studentId
    ): bool
    {
        return $this->model->newQuery()
            ->where('class_id', $classId)
            ->where('student_id', $studentId)
            ->whereNull('left_at')
            ->exists();
    }

    public function endActiveEnrollments(int $classId): int
    {
        return $this->model->newQuery()
            ->where('class_id', $classId)
            ->whereNull('left_at')
            ->update([
                'left_at' => now(),
            ]);
    }

    /**
     * Lấy bản ghi enrollment đang active của học sinh trong lớp
     */
    public function getActiveEnrollment(int $classId, int $studentId): ?ClassEnrollment
    {
        return $this->model->newQuery()
            ->where('class_id', $classId)
            ->where('student_id', $studentId)
            ->whereNull('left_at')
            ->first();
    }

    /**
     * Lấy bản ghi enrollment đầu tiên để lấy ngày enrolled_at gốc
     */
    public function getOriginalEnrollment(int $classId, int $studentId): ?ClassEnrollment
    {
        return $this->model->newQuery()
            ->where('class_id', $classId)
            ->where('student_id', $studentId)
            ->orderBy('enrolled_at', 'asc')
            ->first();
    }


    /**
     * Lấy danh sách học sinh đang học tại thời điểm diễn ra buổi học
     * @param int $sessionId ID sessiong điểm danh
     * @param int $classId ID lớp học
     * @param string $startDate Ngày bắt đầu điểm danh
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStudentListForAttendance(int $sessionId,int $classId, string $startDate)
    {
        return $this->query()
            ->with([
                'student' => function ($query) use ($sessionId) {
                    // Tự động tính tổng sao
                    $query->withSum('rewardPoints as total_reward_points', 'amount')
                        // Load bảng điểm danh CỦA ĐÚNG BUỔI NÀY
                        ->with(['attendanceRecords' => function ($q) use ($sessionId) {
                            $q->where('session_id', $sessionId)
                                ->with(['scores' => function ($scoreQuery) {
                                    $scoreQuery->orderBy('exam_slot', 'asc');
                                }]);
                        }]);
                },
            ])
            ->where('class_id', $classId)
            // Điều kiện chốt chặn: Đang học tại thời điểm đó
            ->whereDate('enrolled_at', '<=', $startDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('left_at')
                    ->orWhereDate('left_at', '>=', $startDate);
            })
            ->get();
    }
}
