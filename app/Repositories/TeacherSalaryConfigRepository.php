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

    
    public function getEffectiveSalary(int $teacherId, int $classId, string $date, ?float $fallbackSalary): float
    {
        $config = $this->query()
            ->where('teacher_id', $teacherId)
            ->where('class_id', $classId)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->latest('effective_from')
            ->first();

        return $config ? (float)$config->salary_per_session : (float)$fallbackSalary;
    }
}
