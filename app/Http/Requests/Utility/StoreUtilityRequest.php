<?php

declare(strict_types=1);

namespace App\Http\Requests\Utility;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUtilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lease_id' => ['required', 'integer', 'exists:leases,id'],
            'type' => ['required', Rule::in(['electricity', 'water'])],
            'current_reading' => ['required', 'integer', 'min:0'],
            'reading_date' => ['required', 'date'],
            'meter_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'lease_id.required' => 'Vui lòng chọn hợp đồng (phòng).',
            'type.required' => 'Vui lòng chọn loại chỉ số (điện/nước).',
            'current_reading.required' => 'Chỉ số mới không được để trống.',
            'current_reading.min' => 'Chỉ số mới không được âm.',
            'reading_date.required' => 'Ngày chốt số không được để trống.',
            'meter_image.image' => 'Ảnh đồng hồ phải là định dạng hình ảnh.',
            'meter_image.max' => 'Ảnh đồng hồ không được vượt quá 4MB.',
            'note.max' => 'Ghi chú không được vượt quá 255 ký tự.',
        ];
    }
}