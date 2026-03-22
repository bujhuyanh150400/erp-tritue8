<?php

namespace App\Repositories;

use App\Constants\ClassStatus;
use App\Core\Repository\BaseRepository;
use App\Models\ClassScheduleTemplate;

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
}
