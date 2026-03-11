<?php

namespace App\Http\Controllers;

use App\Constants\UserRole;
use App\Core\Controller\BaseController;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use App\Http\Requests\RegisterRequest;

class AuthController extends BaseController
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function loginView()
    {
        return $this->rendering('login');
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $result = $this->authService->handleLogin($data['username'], $data['password']);

        if ($result->isSuccess()) {
            $user = $result->getData();

            if ($user->role === UserRole::Admin) {
                return redirect()->intended('/admin/dashboard');
            } elseif ($user->role === UserRole::Teacher) {
                return redirect()->intended('/teacher/dashboard');
            } elseif ($user->role === UserRole::Staff) {
                return redirect()->intended('/staff/dashboard');
            } else {
                return redirect()->intended('/student/dashboard');
            }
        }

        return back()->withErrors(['username' => $result->getMessage()]);
    }

    public function logout()
    {
        $this->authService->handleLogout();

        return redirect('/login');
    }

    public function registerView()
    {
        return $this->rendering('register');
    }

    public function registerUser(RegisterRequest $request)
    {
        $data = $request->validated();

        $result = $this->authService->handleRegister($data);

        if ($result->isSuccess()) {
            return redirect()->intended('/login')
                ->with('success', 'Đăng ký thành công');
        }

        return back()->withErrors([
            'username' => $result->getMessage()
        ]);
    }
}
