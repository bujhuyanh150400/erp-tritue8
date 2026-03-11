<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
//use App\Models\UserRole;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required',
                'string',
                'min:4',
                'max:50',
                Rule::unique('users')
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],

            'role' => [
                'required',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'username.unique' => 'Tên đăng nhập đã tồn tại.',
            'username.min' => 'Tên đăng nhập phải có ít nhất 4 ký tự.',
            'username.max' => 'Tên đăng nhập không được vượt quá 50 ký tự.',

            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.mixed_case' => 'Mật khẩu phải có chữ hoa và chữ thường.',
            'password.numbers' => 'Mật khẩu phải có ít nhất 1 số.',
            'password.symbols' => 'Mật khẩu phải có ít nhất 1 ký tự đặc biệt.',

            'role.required' => 'Vui lòng chọn vai trò.',
            'role.in' => 'Vai trò không hợp lệ.',
        ];
    }
}
