<?php

namespace App\Services;

use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceReturn;
use App\Repositories\UserLogRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthService extends BaseService
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    /**
     * Xử lý đăng nhập người dùng.
     */
    public function handleLogin(string $username, string $password): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($username, $password) {
                $user = $this->userRepository->findByUsername($username);
                if (! $user || ! Hash::check($password, $user->password)) {
                    return ServiceReturn::error('Tên đăng nhập hoặc mật khẩu không đúng.');
                }

                // Check if user is active
                if (! $user->is_active) {
                    return ServiceReturn::error('Tài khoản đã bị khóa, liên hệ admin.');
                }

                // Attempt login session
                Auth::login($user);
                // Log user activity
                Logging::userActivity(
                    userId: $user->id,
                    action: 'Đăng nhập',
                    description: 'Người dùng đăng nhập vào hệ thống thành công'
                );

                return ServiceReturn::success($user, 'Đăng nhập thành công');
            },
        );

    }

    /**
     * Xử lý đăng xuất người dùng.
     */
    public function handleLogout(): ServiceReturn
    {
        return $this->execute(function () {
            if (Auth::check()) {
                Logging::userActivity(
                    userId: Auth::id(),
                    action: 'Đăng xuất',
                    description: 'Người dùng đăng xuất khỏi hệ thống'
                );
            }

            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return ServiceReturn::success(null, 'Đăng xuất thành công');
        });
    }

    public function handleRegister(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {

                $exists = $this->userRepository->findByUsername($data['username']);
                if ($exists) {
                    return ServiceReturn::error('Tên đăng nhập đã tồn tại.');
                }
                $createdData = [
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'role' => $data['role'],
                    'is_active' => true,
                ];
                $user = $this->userRepository->create($createdData);
                if(!$user){
                    return ServiceReturn::error('Đăng ký thất bại!');
                }
                Logging::userActivity(
                    userId: $user->id,
                    action: 'Đăng ký',
                    description: 'Người dùng đăng ký tài khoản'
                );

                return ServiceReturn::success($user, 'Đăng ký thành công');
            }
        );
    }
}
