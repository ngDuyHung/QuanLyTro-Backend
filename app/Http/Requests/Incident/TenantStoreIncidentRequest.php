<?php

declare(strict_types=1);

namespace App\Http\Requests\Incident;

use App\Enums\Incident\IncidentCategory;
use App\Enums\Incident\IncidentPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TenantStoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', new Enum(IncidentCategory::class)],
            'priority' => ['required', new Enum(IncidentPriority::class)],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Tiêu đề sự cố không được để trống.',
            'title.max' => 'Tiêu đề không vượt quá 255 ký tự.',
            'category.required' => 'Vui lòng chọn loại sự cố.',
            'priority.required' => 'Vui lòng chọn mức độ ưu tiên.',
            'images.max' => 'Chỉ được tải lên tối đa 5 ảnh.',
            'images.*.image' => 'File tải lên phải là hình ảnh.',
            'images.*.max' => 'Kích thước ảnh không vượt quá 5MB.',
        ];
    }
}