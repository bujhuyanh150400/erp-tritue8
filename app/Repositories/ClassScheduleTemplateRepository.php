<?php

namespace App\Repositories;

use App\Constants\ClassStatus;
use App\Constants\DayOfWeek;
use App\Core\Interfaces\FilterFilament;
use App\Core\Repository\BaseRepository;
use App\Models\ClassScheduleTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClassScheduleTemplateRepository extends BaseRepository implements FilterFilament
{
    public function getModel()
    {
        return ClassScheduleTemplate::class;
    }

    public function getListingQuery(Builder $query): Builder
    {
        return $query->with([
            'class',
            'teacher:id,full_name',
            'room:id,name',
        ])
            // 1. Đếm tổng số buổi học đã được sinh ra từ Template này
            // Eloquent sẽ tự động tạo ra một thuộc tính ảo tên là: schedule_instances_count
            ->withCount('scheduleInstances')

            // 2. Lấy ra Ngày học xa nhất (max date)
            // Thuộc tính ảo: schedule_instances_max_date
            ->withMax('scheduleInstances', 'date')

            // 3. Lấy ra thời điểm Tool/Job chạy sinh lịch lần cuối cùng (max created_at)
            // Thuộc tính ảo: schedule_instances_max_created_at
            ->withMax('scheduleInstances', 'created_at')

            ->latest('id');
    }

    public function setFilters(Builder $query, array $filters = []): Builder
    {
        return $query;
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
                $q->where('status', ClassStatus::Active);
            })
            ->where(function ($q) use ($targetWeekStart) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $targetWeekStart);
            })
            ->get();
    }


}
