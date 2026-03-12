<?php

namespace App\Services;

use App\Core\Services\BaseService;
use App\Repositories\UserRepository;
use App\Core\Logs\Logging;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;

class UserService extends BaseService
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function handleRegister(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {

                $exists = $this->userRepository->findByUsername($data['username']);

                if ($exists) {
                    throw new ServiceException('Tên đăng nhập đã tồn tại.');
                }
                $createdData = [
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'role' => $data['role'],
                    'is_active' => true,
                ];
                $user = $this->userRepository->create($createdData);
                if (!$user) {
                    throw new ServiceException('Đăng ký thất bại!');
                }
                Logging::userActivity(
                    userId: $user->id,
                    action: 'Đăng ký',
                    description: 'Người dùng ' . $user->username . ' đăng ký tài khoản'
                );

                return ServiceReturn::success($user, 'Đăng ký thành công');
            }
        );
    }
}
