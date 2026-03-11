<?php

namespace App\Services;

use App\Core\Services\BaseService;
use App\Repositories\UserRepository;

class UserService extends BaseService
{

    public function __construct(
        protected UserRepository $userRepository,
    )
    {
    }



}
