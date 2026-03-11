<?php

namespace App\Http\Controllers;

use App\Constants\UserRole;
use App\Core\Controller\BaseController;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;

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
            return redirect()->intended('/admin/dashboard');
        }

        return back()->withErrors(['username' => $result->getMessage()]);
    }

    public function logout()
    {
        $this->authService->handleLogout();

        return redirect('/login');
    }
}
