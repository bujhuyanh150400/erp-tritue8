<?php

namespace App\Http\Requests;

use App\Constants\Gender;
use App\Constants\GradeLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => [
                'nullable',
                'string',
                \Illuminate\Validation\Rules\Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'dob' => ['required', 'date'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // Password
            'password.min' => 'Mật khẩu phải có ít nhất :min ký tự.',
            'password.mixed_case' => 'Mật khẩu phải bao gồm cả chữ hoa và chữ thường.',
            'password.numbers' => 'Mật khẩu phải chứa ít nhất một chữ số.',
            'password.symbols' => 'Mật khẩu phải chứa ít nhất một ký tự đặc biệt.',

            // Full Name
            'full_name.required' => 'Họ và tên không được để trống.',
            'full_name.max' => 'Họ và tên không được vượt quá 255 ký tự.',

            // Date of Birth
            'dob.required' => 'Vui lòng chọn ngày sinh.',
            'dob.date' => 'Ngày sinh không đúng định dạng ngày tháng.',

            // Enum: Gender & Grade Level
            'gender.required' => 'Vui lòng chọn giới tính.',
            'gender.enum' => 'Giới tính đã chọn không hợp lệ.',

            'grade_level.required' => 'Vui lòng chọn khối học.',
            'grade_level.enum' => 'Khối học đã chọn không hợp lệ.',

            // Parent Info
            'parent_name.required' => 'Tên phụ huynh không được để trống.',
            'parent_phone.required' => 'Số điện thoại phụ huynh không được để trống.',
            'parent_phone.max' => 'Số điện thoại không được dài quá 20 ký tự.',

            // Address
            'address.required' => 'Địa chỉ không được để trống.',
        ];
    }
}
