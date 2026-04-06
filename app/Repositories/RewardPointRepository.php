<?php

namespace App\Repositories;

use App\Core\Repository\BaseRepository;
use App\Models\RewardPoint;

class RewardPointRepository extends BaseRepository
{
    public function getModel()
    {
        return RewardPoint::class;
    }

    public function getStudentBalance(int $studentId): int
    {
        return (int) $this->query()
            ->where('student_id', $studentId)
            ->sum('amount');
    }
}
