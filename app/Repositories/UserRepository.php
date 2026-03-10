<?php

namespace App\Repositories;

use App\Core\Repository\BaseRepository;
use App\Models\User;

class UserRepository extends BaseRepository
{
    public function getModel(): string
    {
        return User::class;
    }

    public function findByUsername(string $username): ?User
    {
        return $this->model->where('username', $username)->first();
    }
}
