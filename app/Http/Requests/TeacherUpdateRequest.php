<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email'],
            'address' => ['sometimes', 'nullable', 'string'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'bank_account_holder' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'integer'],
            'joined_at' => ['sometimes', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.string' => 'Họ tên giáo viên không hợp lệ.',
            'full_name.max' => 'Họ tên giáo viên tối đa 255 ký tự.',

            'phone.string' => 'Số điện thoại không hợp lệ.',
            'phone.max' => 'Số điện thoại tối đa 20 ký tự.',

            'email.email' => 'Email không đúng định dạng.',

            'address.string' => 'Địa chỉ không hợp lệ.',

            'bank_name.string' => 'Tên ngân hàng không hợp lệ.',
            'bank_name.max' => 'Tên ngân hàng tối đa 100 ký tự.',

            'bank_account_number.string' => 'Số tài khoản ngân hàng không hợp lệ.',
            'bank_account_number.max' => 'Số tài khoản tối đa 30 ký tự.',

            'bank_account_holder.string' => 'Tên chủ tài khoản không hợp lệ.',
            'bank_account_holder.max' => 'Tên chủ tài khoản tối đa 100 ký tự.',

            'status.integer' => 'Trạng thái không hợp lệ.',

            'joined_at.date' => 'Ngày bắt đầu làm việc không hợp lệ.',
        ];
    }
}
