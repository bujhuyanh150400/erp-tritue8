<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StaffCreateRequest extends FormRequest
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
            'role_type' => ['required', 'integer'],
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

            'full_name.required' => 'Vui lòng nhập họ tên nhân viên.',
            'full_name.string' => 'Họ tên nhân viên không hợp lệ.',
            'full_name.max' => 'Họ tên nhân viên tối đa 255 ký tự.',

            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.string' => 'Số điện thoại không hợp lệ.',
            'phone.max' => 'Số điện thoại tối đa 20 ký tự.',

            'role_type.required' => 'Vui lòng chọn chức vụ.',
            'role_type.integer' => 'Chức vụ không hợp lệ.',

            'bank_name.string' => 'Tên ngân hàng không hợp lệ.',
            'bank_name.max' => 'Tên ngân hàng tối đa 100 ký tự.',

            'bank_account_number.string' => 'Số tài khoản ngân hàng không hợp lệ.',
            'bank_account_number.max' => 'Số tài khoản tối đa 30 ký tự.',

            'bank_account_holder.string' => 'Tên chủ tài khoản không hợp lệ.',
            'bank_account_holder.max' => 'Tên chủ tài khoản tối đa 100 ký tự.',

            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.integer' => 'Trạng thái không hợp lệ.',

            'joined_at.required' => 'Vui lòng nhập ngày bắt đầu làm việc.',
            'joined_at.date' => 'Ngày bắt đầu làm việc không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'người dùng',
            'full_name' => 'họ tên nhân viên',
            'phone' => 'số điện thoại',
            'role_type' => 'chức vụ',
            'bank_name' => 'tên ngân hàng',
            'bank_account_number' => 'số tài khoản',
            'bank_account_holder' => 'chủ tài khoản',
            'status' => 'trạng thái',
            'joined_at' => 'ngày bắt đầu làm việc',
        ];
    }
}
