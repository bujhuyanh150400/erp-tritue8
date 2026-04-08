<?php

namespace App\Repositories;

use App\Core\Repository\BaseRepository;
use App\Models\TeacherSalaryConfig;

class TeacherSalaryConfigRepository extends BaseRepository
{
    public function getModel(): string
    {
        return TeacherSalaryConfig::class;
    }


    /**
     * Lấy lương hiệu lực của giáo viên trong khoảng thời gian
     * @param int $teacherId
     * @param int $classId
     * @param string $startDate
     * @param string $endDate
     * @param float|null $fallbackSalary
     * @return float
     */
    public function getEffectiveSalaryForPeriod(int $teacherId, int $classId, string $startDate, string $endDate, ?float $fallbackSalary = 0):  float
    {
        $config = $this->query()
            ->where('teacher_id', $teacherId)
            ->where('class_id', $classId)
            ->where('effective_from', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $startDate);
            })
            ->latest('effective_from')
            ->first();

        return $config ? (float)$config->salary_per_session : (float)$fallbackSalary;
    }

    /**
     * Lấy danh sách cấu hình lương của giáo viên trong khoảng thời gian
     * @param int $teacherId
     * @param int $classId
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getConfigsForPeriod(int $teacherId, int $classId, string $startDate, string $endDate)
    {
        return $this->query()
            ->where('teacher_id', $teacherId)
            ->where('class_id', $classId)
            // Điều kiện để cấu hình có hiệu lực giao thoa với khoảng thời gian sinh lịch
            ->where('effective_from', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $startDate);
            })
            // Sắp xếp giảm dần theo ngày bắt đầu để ưu tiên cấu hình mới nhất
            ->orderBy('effective_from', 'desc')
            ->get(); // Lấy ra một Collection thay vì first()
    }
}
