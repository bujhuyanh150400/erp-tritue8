<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_account_holder' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'integer'],
            'joined_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Vui lòng chọn người dùng.',
            'user_id.exists' => 'Người dùng không tồn tại trong hệ thống.',

            'full_name.required' => 'Vui lòng nhập họ tên giáo viên.',
            'full_name.string' => 'Họ tên giáo viên không hợp lệ.',
            'full_name.max' => 'Họ tên giáo viên tối đa 255 ký tự.',

            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.string' => 'Số điện thoại không hợp lệ.',
            'phone.max' => 'Số điện thoại tối đa 20 ký tự.',

            'email.email' => 'Email không đúng định dạng.',
            'email.max' => 'Email tối đa 255 ký tự.',

            'bank_name.max' => 'Tên ngân hàng tối đa 100 ký tự.',
            'bank_account_number.max' => 'Số tài khoản tối đa 30 ký tự.',
            'bank_account_holder.max' => 'Tên chủ tài khoản tối đa 100 ký tự.',

            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.integer' => 'Trạng thái không hợp lệ.',

            'joined_at.required' => 'Vui lòng nhập ngày bắt đầu làm việc.',
            'joined_at.date' => 'Ngày bắt đầu làm việc không hợp lệ.',
        ];
    }
}
