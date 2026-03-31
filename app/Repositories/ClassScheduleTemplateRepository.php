<?php

namespace App\Repositories;

use App\Constants\ClassStatus;
use App\Constants\DayOfWeek;
use App\Core\Repository\BaseRepository;
use App\Models\ClassScheduleTemplate;
use Illuminate\Database\Eloquent\Model;

class ClassScheduleTemplateRepository extends BaseRepository
{
    public function getModel()
    {
        return ClassScheduleTemplate::class;
    }

    /**
     * Đếm số lớp đang hoạt động theo phòng học
     * @param int $roomId
     * @return mixed
     */
    public function countClassesActive(int $roomId)
    {
        return $this->model->currentlyActive()
            ->where('room_id', $roomId)
            ->whereHas('class', function ($query) {
                $query->where('status', ClassStatus::Active->value);
            })
            ->distinct('class_id')
            ->count('class_id');
    }

    /**
     * Kiểm tra xem phòng có lịch đang hoạt động không
     * @param int $roomId
     * @return bool
     */
    public function hasActiveSchedulesForRoom(int $roomId): bool
    {
        return $this->query()
            ->where('room_id', $roomId)
            ->currentlyActive()
            ->exists();
    }

    /**
     * Kiểm tra trùng PHÒNG trên lịch cố định (Templates)
     * @param int $roomId  ID phòng học
     * @param array $daysOfWeek  Thứ học (mảng số nguyên theo DayOfWeek::class)
     * @param string $startTime  Giờ bắt đầu
     * @param string $endTime  Giờ kết thúc
     * @param string $startDate  Ngày bắt đầu kiểm tra
     * @param string $endDate  Ngày kết thúc kiểm tra
     */
    public function findRoomConflicts(int $roomId, array $daysOfWeek, string $startTime, string $endTime, string $startDate, string $endDate)
    {

        return $this->query()
            ->where('room_id', $roomId)
            ->whereIn('day_of_week', $daysOfWeek) // TÌM TẤT CẢ CÁC THỨ CÙNG LÚC
            // 1. Giao thoa thời gian trong ngày (Time Overlap)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            // 2. Giao thoa khoảng ngày (Date Range Overlap)
            ->where('start_date', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $startDate);
            })
            ->with('class') // Load relationship class để lấy tên báo lỗi
            ->first();
    }

    /**
     * Kiểm tra trùng GIÁO VIÊN trên lịch cố định (Templates)
     * @param int $teacherId  ID giáo viên
     * @param array $daysOfWeek  Thứ học (mảng số nguyên theo DayOfWeek::class)
     * @param string $startTime  Giờ bắt đầu kiểm tra
     * @param string $endTime  Giờ kết thúc kiểm tra
     * @param string $startDate  Ngày bắt đầu kiểm tra
     * @param string $endDate  Ngày kết thúc kiểm tra
     */
    public function findTeacherConflicts(int $teacherId, array $daysOfWeek, string $startTime, string $endTime, string $startDate, string $endDate)
    {
        return $this->query()
            ->where('teacher_id', $teacherId)
            ->whereIn('day_of_week', $daysOfWeek) // TÌM TẤT CẢ CÁC THỨ CÙNG LÚC
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $startDate);
            })
            ->with('class')
            ->first();
    }

    /**
     * Tìm các lớp đang hoạt động và chưa hết hạn (phục vụ sinh lịch tuần theo tuần)
     * @param string $targetWeekStart
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveTemplatesForRolling(string $targetWeekStart)
    {
        return $this->query()
            ->whereHas('class', function ($q) {
                // Lưu ý: Đổi 'active' thành Enum ClassStatus::Active->value nếu dự án bạn dùng Enum
                $q->where('status', 'active');
            })
            ->where(function ($q) use ($targetWeekStart) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $targetWeekStart);
            })
            ->get();
    }
}
