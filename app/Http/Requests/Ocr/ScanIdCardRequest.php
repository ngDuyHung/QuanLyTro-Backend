<?php

declare(strict_types=1);

namespace App\Http\Requests\Ocr;

use Illuminate\Foundation\Http\FormRequest;

class ScanIdCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // Tối đa 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Vui lòng tải lên hình ảnh CCCD.',
            'image.image' => 'File tải lên phải là hình ảnh.',
            'image.mimes' => 'Ảnh chỉ chấp nhận định dạng: jpg, jpeg, png, webp.',
            'image.max' => 'Dung lượng ảnh không được vượt quá 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'image' => 'hình ảnh CCCD',
        ];
    }
}
