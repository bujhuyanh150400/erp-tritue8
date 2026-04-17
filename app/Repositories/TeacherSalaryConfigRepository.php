<?php

namespace App\Repositories;

use App\Constants\SalaryType;
use App\Core\Repository\BaseRepository;
use App\Models\TeacherSalaryConfig;

class TeacherSalaryConfigRepository extends BaseRepository
{
    public function getModel(): string
    {
        return TeacherSalaryConfig::class;
    }


    /**
     * Lấy lương hiệu lực của giáo viên theo ca
     * @param int $teacherId
     * @return float
     */
    public function getSalarySession(int $teacherId):  float
    {
        $config = $this->query()
            ->where('teacher_id', $teacherId)
            ->where('salary_type', SalaryType::Session)
            ->first();

        return $config ? (float)$config->salary_per_session : 0;
    }
}
