<?php

declare(strict_types=1);

namespace App\Http\Requests\Property;

use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_type' => ['sometimes', 'required', 'string', new Enum(PropertyType::class)],
            'name'        => ['sometimes', 'required', 'string', 'max:100'],
            'address'     => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'property_type.required' => 'Loại khu nhà không được để trống.',
            'property_type.enum'     => 'Loại khu nhà không hợp lệ.',
            'name.required'    => 'Tên khu nhà không được để trống.',
            'name.max'         => 'Tên khu nhà không vượt quá 100 ký tự.',
            'address.required' => 'Địa chỉ không được để trống.',
            'address.max'      => 'Địa chỉ không vượt quá 255 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'        => 'tên khu nhà',
            'address'     => 'địa chỉ',
            'description' => 'mô tả',
            'property_type' => 'loại khu nhà',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('name')) {
            $data['name'] = trim($this->name ?? '');
        }

        if ($this->has('address')) {
            $data['address'] = trim($this->address ?? '');
        }

        // Nếu có trường property_type, chuẩn hóa về lowercase để tránh lỗi enum do case-sensitive
        if ($this->has('property_type')) {
            $data['property_type'] = strtolower($this->property_type ?? '');
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }
}
