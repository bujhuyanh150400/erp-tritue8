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
        return DB::table('class_enrollments')
            ->where('class_id', $classId)
            ->whereNull('left_at')
            ->update([
                'left_at' => now()
            ]);
    }
}
