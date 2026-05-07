<?php

declare(strict_types=1);

namespace App\Http\Requests\MeterReading;

use App\Enums\MeterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreMeterReadingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type'            => ['required', new Enum(MeterType::class)],
            'current_reading' => ['required', 'integer', 'min:0'],
            'meter_image'     => ['nullable', 'image', 'max:2048'],
            'reading_date'    => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'            => 'Vui lòng chọn loại chỉ số.',
            'current_reading.required' => 'Vui lòng nhập chỉ số mới.',
            'current_reading.integer'  => 'Chỉ số mới phải là số nguyên.',
            'current_reading.min'      => 'Chỉ số mới phải lớn hơn hoặc bằng 0.',
            'meter_image.image'        => 'Ảnh chỉ số phải là một file hình ảnh.',
            'meter_image.max'          => 'Ảnh chỉ số không được vượt quá 2MB.',
            'reading_date.required'    => 'Vui lòng nhập ngày đọc chỉ số.',
            'reading_date.date'        => 'Ngày đọc chỉ số không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'type'            => 'loại chỉ số',
            'current_reading' => 'chỉ số mới',
            'meter_image'     => 'ảnh chỉ số',
            'reading_date'    => 'ngày đọc chỉ số',
        ];
    }
}
