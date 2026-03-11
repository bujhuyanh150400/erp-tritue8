<?php

namespace App\Http\Controllers;

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
            $this->success('Đăng nhập thành công');

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['username' => $result->getMessage()]);
    }

    public function logout()
    {
        $this->authService->handleLogout();

        return redirect()->route('login');
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
