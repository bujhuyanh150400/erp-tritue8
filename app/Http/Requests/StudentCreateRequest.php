<?php


namespace App\Http\Requests;

use App\Constants\GradeLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'dob' => ['required', 'date'],
            'gender' => ['required', 'integer'],
            'grade_level' => ['required',Rule::enum(GradeLevel::class)],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'zalo_id' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'User không tồn tại.',
            'full_name.required' => 'Vui lòng nhập họ tên học sinh.',
            'dob.required' => 'Vui lòng nhập ngày sinh.',
            'gender.required' => 'Vui lòng chọn giới tính.',
            'grade_level.required' => 'Vui lòng chọn lớp.',
            'parent_name.required' => 'Vui lòng nhập tên phụ huynh.',
            'parent_phone.required' => 'Vui lòng nhập số điện thoại phụ huynh.',
            'address.required' => 'Vui lòng nhập địa chỉ.',
        ];
    }
}
