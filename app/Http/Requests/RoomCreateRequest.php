<?php

namespace App\Http\Requests;

use App\Constants\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:rooms,name'],

            'capacity' => ['nullable', 'integer', 'min:0', 'max:255'],

            'note' => ['nullable', 'string'],

            'status' => ['required', Rule::enum(RoomStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            // Room Name
            'name.required' => 'Tên phòng học không được để trống.',
            'name.string' => 'Tên phòng học phải là chuỗi ký tự.',
            'name.max' => 'Tên phòng học không được vượt quá 50 ký tự.',
            'name.unique' => 'Tên phòng học đã tồn tại.',

            // Capacity
            'capacity.integer' => 'Số lượng ghế phải là số.',
            'capacity.min' => 'Số lượng ghế không được nhỏ hơn 0.',
            'capacity.max' => 'Số lượng ghế không được vượt quá 255.',

            // Note
            'note.string' => 'Ghi chú phải là chuỗi ký tự.',

            // Status
            'status.required' => 'Vui lòng chọn trạng thái phòng.',
            'status.enum' => 'Trạng thái phòng không hợp lệ.',
        ];
    }
}
