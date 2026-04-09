<?php

declare(strict_types=1);

namespace App\Http\Requests\MeterReading;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeterReadingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'current_reading' => ['sometimes', 'integer', 'min:0'],
            'meter_image'     => ['nullable', 'image', 'max:2048'],
            'reading_date'    => ['sometimes', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_reading.integer' => 'Chỉ số mới phải là số nguyên.',
            'current_reading.min'     => 'Chỉ số mới phải lớn hơn hoặc bằng 0.',
            'meter_image.image'       => 'Ảnh chỉ số phải là một file hình ảnh.',
            'meter_image.max'         => 'Ảnh chỉ số không được vượt quá 2MB.',
            'reading_date.date'       => 'Ngày đọc chỉ số không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'current_reading' => 'chỉ số mới',
            'meter_image'     => 'ảnh chỉ số',
            'reading_date'    => 'ngày đọc chỉ số',
        ];
    }
}
