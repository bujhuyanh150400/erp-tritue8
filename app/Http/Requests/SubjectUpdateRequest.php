<?php


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubjectUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên môn học không được để trống.',
            'name.string' => 'Tên môn học phải là chuỗi ký tự.',
            'name.max' => 'Tên môn học không được vượt quá 50 ký tự.',

            'description.string' => 'Mô tả phải là chuỗi ký tự.',

            'is_active.required' => 'Vui lòng chọn trạng thái hoạt động.',
            'is_active.boolean' => 'Trạng thái hoạt động không hợp lệ.',
        ];
    }
}
