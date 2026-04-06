<?php

namespace App\Repositories;

use App\Constants\ClassStatus;
use App\Constants\DayOfWeek;
use App\Core\Repository\BaseRepository;
use App\Models\ClassScheduleTemplate;
use Illuminate\Database\Eloquent\Builder;
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
     * Tìm xung đột PHÒNG hoặc GIÁO VIÊN trên lịch cố định (Templates)
     * @param int $roomId  ID phòng học
     * @param int $teacherId  ID giáo viên
     * @param array $daysOfWeek  Thứ học (mảng số nguyên theo DayOfWeek::class)
     * @param string $startTime  Giờ bắt đầu
     * @param string $endTime  Giờ kết thúc
     * @param string $startDate  Ngày bắt đầu kiểm tra
     * @param string $endDate  Ngày kết thúc kiểm tra
     * @param int|null $excludeTemplateId  ID lịch cố định không kiểm tra
     * @return ClassScheduleTemplate|Model|null
     */
    public function findConflicts(int $roomId, int $teacherId, array $daysOfWeek, string $startTime, string $endTime, string $startDate, string $endDate, int|null $excludeTemplateId = null)
    {
        return $this->query()
            // Dùng Closure để gom nhóm điều kiện OR (Phòng hỏng HOẶC Giáo viên bận)
            ->where(function ($query) use ($roomId, $teacherId) {
                $query->where('room_id', $roomId)
                    ->orWhere('teacher_id', $teacherId);
            })
            ->when($excludeTemplateId, function ($query) use ($excludeTemplateId) {
                $query->where('id', '!=', $excludeTemplateId);
            })
            ->whereIn('day_of_week', $daysOfWeek)
            // 1. Giao thoa thời gian trong ngày
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            // 2. Giao thoa khoảng ngày
            ->where('start_date', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $startDate);
            })
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
