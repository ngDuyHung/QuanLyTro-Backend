<?php

declare(strict_types=1);

namespace App\Http\Requests\Utility;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUtilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'previous_reading' => ['nullable', 'integer', 'min:0'],
            'current_reading' => ['required', 'integer', 'min:0'],
            'reading_date' => ['required', 'date'],
            'meter_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'], // Cờ để xóa ảnh nếu FE truyền lên
        ];
    }
}