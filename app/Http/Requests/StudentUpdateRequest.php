<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'dob' => ['sometimes', 'date'],
            'gender' => ['sometimes', 'integer'],
            'grade_level' => ['sometimes', 'integer'],
            'parent_name' => ['sometimes', 'string', 'max:255'],
            'parent_phone' => ['sometimes', 'string', 'max:20'],
            'address' => ['sometimes', 'string'],
            'zalo_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.string' => 'Họ tên học sinh không hợp lệ.',

            'dob.date' => 'Ngày sinh không hợp lệ.',

            'gender.integer' => 'Giới tính không hợp lệ.',

            'grade_level.integer' => 'Lớp không hợp lệ.',

            'parent_name.string' => 'Tên phụ huynh không hợp lệ.',

            'parent_phone.string' => 'Số điện thoại phụ huynh không hợp lệ.',

            'address.string' => 'Địa chỉ không hợp lệ.',
        ];
    }
}
