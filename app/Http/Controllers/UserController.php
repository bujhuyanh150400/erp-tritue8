<?php

namespace App\Http\Controllers;

use App\Core\Controller\BaseController;
use App\Http\Requests\RegisterRequest;
use App\Services\UserService;

class UserController extends BaseController
{
    public function __construct(
        protected UserService $userService
    ) {}
    public function registerView()
    {
        return $this->rendering('register');
    }

    public function registerUser(RegisterRequest $request)
    {
        $data = $request->validated();

        $result = $this->userService->handleRegister($data);

        if ($result->isSuccess()) {
            $this->success('Đăng ký thành công');

            return redirect()->route('login');
        }

        return back()->withErrors([
            'username' => $result->getMessage(),
        ]);
    }
}
