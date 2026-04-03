<?php

namespace App\Repositories;

use App\Core\Repository\BaseRepository;
use App\Models\RewardRedemption;
use Illuminate\Database\Eloquent\Builder;

class RewardRedemptionRepository extends BaseRepository
{
    public function getModel(): string
    {
        return RewardRedemption::class;
    }

    public function getStudentHistoryQuery(int $studentId): Builder
    {
        return $this->query()
            ->with([
                'rewardItem:id,name,reward_type',
                'processedBy:id,username',
                'processedBy.teacher:id,user_id,full_name',
                'processedBy.staff:id,user_id,full_name',
                'processedBy.student:id,user_id,full_name',
            ])
            ->where('student_id', $studentId)
            ->orderByDesc('redeemed_at');
    }
}
