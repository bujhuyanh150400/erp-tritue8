<?php

namespace App\Repositories;

use App\Core\Repository\BaseRepository;
use App\Models\UserLog;

class UserLogRepository extends BaseRepository
{

    public function getModel(): string
    {
        return UserLog::class;
    }

    /**
     * Tạo mới một entry log cho user.
     * @param int $userId
     * @param string $action
     * @param string|null $description
     * @return UserLog
     */
    public function log(int $userId, string $action, ?string $description = null): UserLog
    {
        return $this->model->create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
