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
}
